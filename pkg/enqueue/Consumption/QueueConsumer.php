<?php
namespace Enqueue\Consumption;

use Enqueue\Psr\Consumer;
use Enqueue\Psr\Context as PsrContext;
use Enqueue\Psr\Queue;
use Enqueue\Consumption\Exception\ConsumptionInterruptedException;
use Enqueue\Consumption\Exception\InvalidArgumentException;
use Enqueue\Consumption\Exception\LogicException;
use Enqueue\Util\VarExport;
use Psr\Log\NullLogger;

class QueueConsumer
{
    /**
     * @var PsrContext
     */
    private $psrContext;

    /**
     * @var ExtensionInterface|ChainExtension|null
     */
    private $extension;

    /**
     * [
     *   [Queue, MessageProcessorInterface],
     * ].
     *
     * @var array
     */
    private $boundMessageProcessors;

    /**
     * @var int
     */
    private $idleMicroseconds;

    /**
     * @param PsrContext                             $psrContext
     * @param ExtensionInterface|ChainExtension|null $extension
     * @param int                                    $idleMicroseconds 100ms by default
     */
    public function __construct(
        PsrContext $psrContext,
        ExtensionInterface $extension = null,
        $idleMicroseconds = 100000
    ) {
        $this->psrContext = $psrContext;
        $this->extension = $extension;
        $this->idleMicroseconds = $idleMicroseconds;

        $this->boundMessageProcessors = [];
    }

    /**
     * @return PsrContext
     */
    public function getPsrContext()
    {
        return $this->psrContext;
    }

    /**
     * @param Queue|string                       $queue
     * @param MessageProcessorInterface|callable $messageProcessor
     *
     * @return QueueConsumer
     */
    public function bind($queue, $messageProcessor)
    {
        if (is_string($queue)) {
            $queue = $this->psrContext->createQueue($queue);
        }
        if (is_callable($messageProcessor)) {
            $messageProcessor = new CallbackMessageProcessor($messageProcessor);
        }

        InvalidArgumentException::assertInstanceOf($queue, Queue::class);
        InvalidArgumentException::assertInstanceOf($messageProcessor, MessageProcessorInterface::class);

        if (empty($queue->getQueueName())) {
            throw new LogicException('The queue name must be not empty.');
        }
        if (array_key_exists($queue->getQueueName(), $this->boundMessageProcessors)) {
            throw new LogicException(sprintf('The queue was already bound. Queue: %s', $queue->getQueueName()));
        }

        $this->boundMessageProcessors[$queue->getQueueName()] = [$queue, $messageProcessor];

        return $this;
    }

    /**
     * Runtime extension - is an extension or a collection of extensions which could be set on runtime.
     * Here's a good example: @see LimitsExtensionsCommandTrait.
     *
     * @param ExtensionInterface|ChainExtension|null $runtimeExtension
     *
     * @throws \Exception
     */
    public function consume(ExtensionInterface $runtimeExtension = null)
    {
        /** @var Consumer[] $messageConsumers */
        $messageConsumers = [];
        /** @var \Enqueue\Psr\Queue $queue */
        foreach ($this->boundMessageProcessors as list($queue, $messageProcessor)) {
            $messageConsumers[$queue->getQueueName()] = $this->psrContext->createConsumer($queue);
        }

        $extension = $this->extension ?: new ChainExtension([]);
        if ($runtimeExtension) {
            $extension = new ChainExtension([$extension, $runtimeExtension]);
        }

        $context = new Context($this->psrContext);
        $extension->onStart($context);

        $logger = $context->getLogger() ?: new NullLogger();
        $logger->info('Start consuming');

        while (true) {
            try {
                /** @var Queue $queue */
                foreach ($this->boundMessageProcessors as list($queue, $messageProcessor)) {
                    $logger->debug(sprintf('Switch to a queue %s', $queue->getQueueName()));

                    $messageConsumer = $messageConsumers[$queue->getQueueName()];

                    $context = new Context($this->psrContext);
                    $context->setLogger($logger);
                    $context->setPsrQueue($queue);
                    $context->setPsrConsumer($messageConsumer);
                    $context->setMessageProcessor($messageProcessor);

                    $this->doConsume($extension, $context);
                }
            } catch (ConsumptionInterruptedException $e) {
                $logger->info(sprintf('Consuming interrupted'));

                $context->setExecutionInterrupted(true);

                $extension->onInterrupted($context);
                $this->psrContext->close();

                return;
            } catch (\Exception $exception) {
                $context->setExecutionInterrupted(true);
                $context->setException($exception);

                try {
                    $this->onInterruptionByException($extension, $context);
                    $this->psrContext->close();
                } catch (\Exception $e) {
                    // for some reason finally does not work here on php5.5
                    $this->psrContext->close();

                    throw $e;
                }
            }
        }
    }

    /**
     * @param ExtensionInterface $extension
     * @param Context            $context
     *
     * @throws ConsumptionInterruptedException
     *
     * @return bool
     */
    protected function doConsume(ExtensionInterface $extension, Context $context)
    {
        $messageProcessor = $context->getMessageProcessor();
        $messageConsumer = $context->getPsrConsumer();
        $logger = $context->getLogger();

        $extension->onBeforeReceive($context);

        if ($context->isExecutionInterrupted()) {
            throw new ConsumptionInterruptedException();
        }

        if ($message = $messageConsumer->receive($timeout = 1)) {
            $logger->info('Message received');
            $logger->debug('Headers: {headers}', ['headers' => new VarExport($message->getHeaders())]);
            $logger->debug('Properties: {properties}', ['properties' => new VarExport($message->getProperties())]);
            $logger->debug('Payload: {payload}', ['payload' => new VarExport($message->getBody())]);

            $context->setPsrMessage($message);

            $extension->onPreReceived($context);
            if (!$context->getResult()) {
                $result = $messageProcessor->process($message, $this->psrContext);
                $context->setResult($result);
            }

            switch ($context->getResult()) {
                case Result::ACK:
                    $messageConsumer->acknowledge($message);
                    break;
                case Result::REJECT:
                    $messageConsumer->reject($message, false);
                    break;
                case Result::REQUEUE:
                    $messageConsumer->reject($message, true);
                    break;
                default:
                    throw new \LogicException(sprintf('Status is not supported: %s', $context->getResult()));
            }

            $logger->info(sprintf('Message processed: %s', $context->getResult()));

            $extension->onPostReceived($context);
        } else {
            $logger->info(sprintf('Idle'));

            usleep($this->idleMicroseconds);
            $extension->onIdle($context);
        }

        if ($context->isExecutionInterrupted()) {
            throw new ConsumptionInterruptedException();
        }
    }

    /**
     * @param ExtensionInterface $extension
     * @param Context            $context
     *
     * @throws \Exception
     */
    protected function onInterruptionByException(ExtensionInterface $extension, Context $context)
    {
        $logger = $context->getLogger();
        $logger->error(sprintf('Consuming interrupted by exception'));

        $exception = $context->getException();

        try {
            $extension->onInterrupted($context);
        } catch (\Exception $e) {
            // logic is similar to one in Symfony's ExceptionListener::onKernelException
            $logger->error(sprintf(
                'Exception thrown when handling an exception (%s: %s at %s line %s)',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));

            $wrapper = $e;
            while ($prev = $wrapper->getPrevious()) {
                if ($exception === $wrapper = $prev) {
                    throw $e;
                }
            }

            $prev = new \ReflectionProperty('Exception', 'previous');
            $prev->setAccessible(true);
            $prev->setValue($wrapper, $exception);

            throw $e;
        }

        throw $exception;
    }
}
