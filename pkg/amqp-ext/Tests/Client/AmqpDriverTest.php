<?php
namespace Enqueue\AmqpExt\Tests\Client;

use Enqueue\AmqpExt\AmqpContext;
use Enqueue\AmqpExt\AmqpMessage;
use Enqueue\AmqpExt\AmqpQueue;
use Enqueue\AmqpExt\AmqpTopic;
use Enqueue\AmqpExt\Client\AmqpDriver;
use Enqueue\Psr\Producer;
use Enqueue\Client\Config;
use Enqueue\Client\DriverInterface;
use Enqueue\Client\Message;
use Enqueue\Client\MessagePriority;
use Enqueue\Client\Meta\QueueMetaRegistry;
use Enqueue\Test\ClassExtensionTrait;

class AmqpDriverTest extends \PHPUnit_Framework_TestCase
{
    use ClassExtensionTrait;

    public function testShouldImplementsDriverInterface()
    {
        $this->assertClassImplements(DriverInterface::class, AmqpDriver::class);
    }

    public function testCouldBeConstructedWithRequiredArguments()
    {
        new AmqpDriver(
            $this->createPsrContextMock(),
            new Config('', '', '', '', '', ''),
            $this->createQueueMetaRegistryMock()
        );
    }

    public function testShouldReturnConfigObject()
    {
        $config = new Config('', '', '', '', '', '');

        $driver = new AmqpDriver($this->createPsrContextMock(), $config, $this->createQueueMetaRegistryMock());

        $this->assertSame($config, $driver->getConfig());
    }

    public function testShouldCreateAndReturnQueueInstance()
    {
        $expectedQueue = new AmqpQueue('queue-name');

        $context = $this->createPsrContextMock();
        $context
            ->expects($this->once())
            ->method('createQueue')
            ->with('name')
            ->will($this->returnValue($expectedQueue))
        ;

        $driver = new AmqpDriver($context, new Config('', '', '', '', '', ''), $this->createQueueMetaRegistryMock());

        $queue = $driver->createQueue('name');

        $this->assertSame($expectedQueue, $queue);
        $this->assertSame('queue-name', $queue->getQueueName());
        $this->assertSame(['x-max-priority' => 4], $queue->getArguments());
        $this->assertSame(2, $queue->getFlags());
        $this->assertNull($queue->getConsumerTag());
        $this->assertSame([], $queue->getBindArguments());
    }

    public function testShouldConvertTransportMessageToClientMessage()
    {
        $transportMessage = new AmqpMessage();
        $transportMessage->setBody('body');
        $transportMessage->setHeaders(['hkey' => 'hval']);
        $transportMessage->setProperties(['key' => 'val']);
        $transportMessage->setHeader('content_type', 'ContentType');
        $transportMessage->setHeader('expiration', '12345000');
        $transportMessage->setHeader('priority', 3);
        $transportMessage->setMessageId('MessageId');
        $transportMessage->setTimestamp(1000);

        $driver = new AmqpDriver(
            $this->createPsrContextMock(),
            new Config('', '', '', '', '', ''),
            $this->createQueueMetaRegistryMock()
        );

        $clientMessage = $driver->createClientMessage($transportMessage);

        $this->assertInstanceOf(Message::class, $clientMessage);
        $this->assertSame('body', $clientMessage->getBody());
        $this->assertSame([
            'hkey' => 'hval',
            'content_type' => 'ContentType',
            'expiration' => '12345000',
            'priority' => 3,
            'message_id' => 'MessageId',
            'timestamp' => 1000,
        ], $clientMessage->getHeaders());
        $this->assertSame([
            'key' => 'val',
        ], $clientMessage->getProperties());
        $this->assertSame('MessageId', $clientMessage->getMessageId());
        $this->assertSame(12345, $clientMessage->getExpire());
        $this->assertSame('ContentType', $clientMessage->getContentType());
        $this->assertSame(1000, $clientMessage->getTimestamp());
        $this->assertSame(MessagePriority::HIGH, $clientMessage->getPriority());
    }

    public function testShouldThrowExceptionIfExpirationIsNotNumeric()
    {
        $transportMessage = new AmqpMessage();
        $transportMessage->setHeader('expiration', 'is-not-numeric');

        $driver = new AmqpDriver(
            $this->createPsrContextMock(),
            new Config('', '', '', '', '', ''),
            $this->createQueueMetaRegistryMock()
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('expiration header is not numeric. "is-not-numeric"');

        $driver->createClientMessage($transportMessage);
    }

    public function testShouldThrowExceptionIfCantConvertTransportPriorityToClientPriority()
    {
        $transportMessage = new AmqpMessage();
        $transportMessage->setHeader('priority', 'unknown');

        $driver = new AmqpDriver(
            $this->createPsrContextMock(),
            new Config('', '', '', '', '', ''),
            $this->createQueueMetaRegistryMock()
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cant convert transport priority to client: "unknown"');

        $driver->createClientMessage($transportMessage);
    }

    public function testShouldThrowExceptionIfCantConvertClientPriorityToTransportPriority()
    {
        $clientMessage = new Message();
        $clientMessage->setPriority('unknown');

        $driver = new AmqpDriver(
            $this->createPsrContextMock(),
            new Config('', '', '', '', '', ''),
            $this->createQueueMetaRegistryMock()
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Given priority could not be converted to client\'s one. Got: unknown');

        $driver->createTransportMessage($clientMessage);
    }

    public function testShouldConvertClientMessageToTransportMessage()
    {
        $clientMessage = new Message();
        $clientMessage->setBody('body');
        $clientMessage->setHeaders(['hkey' => 'hval']);
        $clientMessage->setProperties(['key' => 'val']);
        $clientMessage->setContentType('ContentType');
        $clientMessage->setExpire(123);
        $clientMessage->setPriority(MessagePriority::VERY_HIGH);
        $clientMessage->setMessageId('MessageId');
        $clientMessage->setTimestamp(1000);

        $context = $this->createPsrContextMock();
        $context
            ->expects($this->once())
            ->method('createMessage')
            ->willReturn(new AmqpMessage())
        ;

        $driver = new AmqpDriver(
            $context,
            new Config('', '', '', '', '', ''),
            $this->createQueueMetaRegistryMock()
        );

        $transportMessage = $driver->createTransportMessage($clientMessage);

        $this->assertInstanceOf(AmqpMessage::class, $transportMessage);
        $this->assertSame('body', $transportMessage->getBody());
        $this->assertSame([
            'hkey' => 'hval',
            'content_type' => 'ContentType',
            'expiration' => '123000',
            'priority' => 4,
            'delivery_mode' => 2,
            'message_id' => 'MessageId',
            'timestamp' => 1000,
        ], $transportMessage->getHeaders());
        $this->assertSame([
            'key' => 'val',
        ], $transportMessage->getProperties());
        $this->assertSame('MessageId', $transportMessage->getMessageId());
        $this->assertSame(1000, $transportMessage->getTimestamp());
    }

    public function testShouldSendMessageToRouter()
    {
        $topic = new AmqpTopic('');
        $transportMessage = new AmqpMessage();

        $producer = $this->createPsrProducerMock();
        $producer
            ->expects($this->once())
            ->method('send')
            ->with($this->identicalTo($topic), $this->identicalTo($transportMessage))
        ;
        $context = $this->createPsrContextMock();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($topic)
        ;
        $context
            ->expects($this->once())
            ->method('createProducer')
            ->willReturn($producer)
        ;
        $context
            ->expects($this->once())
            ->method('createMessage')
            ->willReturn($transportMessage)
        ;

        $driver = new AmqpDriver(
            $context,
            new Config('', '', '', '', '', ''),
            $this->createQueueMetaRegistryMock()
        );

        $message = new Message();
        $message->setProperty(Config::PARAMETER_TOPIC_NAME, 'topic');

        $driver->sendToRouter($message);
    }

    public function testShouldThrowExceptionIfTopicParameterIsNotSet()
    {
        $driver = new AmqpDriver(
            $this->createPsrContextMock(),
            new Config('', '', '', '', '', ''),
            $this->createQueueMetaRegistryMock()
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Topic name parameter is required but is not set');

        $driver->sendToRouter(new Message());
    }

    public function testShouldSendMessageToProcessor()
    {
        $queue = new AmqpQueue('');
        $transportMessage = new AmqpMessage();

        $producer = $this->createPsrProducerMock();
        $producer
            ->expects($this->once())
            ->method('send')
            ->with($this->identicalTo($queue), $this->identicalTo($transportMessage))
        ;
        $context = $this->createPsrContextMock();
        $context
            ->expects($this->once())
            ->method('createQueue')
            ->willReturn($queue)
        ;
        $context
            ->expects($this->once())
            ->method('createProducer')
            ->willReturn($producer)
        ;
        $context
            ->expects($this->once())
            ->method('createMessage')
            ->willReturn($transportMessage)
        ;

        $driver = new AmqpDriver(
            $context,
            new Config('', '', '', '', '', ''),
            $this->createQueueMetaRegistryMock()
        );

        $message = new Message();
        $message->setProperty(Config::PARAMETER_PROCESSOR_NAME, 'processor');
        $message->setProperty(Config::PARAMETER_PROCESSOR_QUEUE_NAME, 'queue');

        $driver->sendToProcessor($message);
    }

    public function testShouldThrowExceptionIfProcessorNameParameterIsNotSet()
    {
        $driver = new AmqpDriver(
            $this->createPsrContextMock(),
            new Config('', '', '', '', '', ''),
            $this->createQueueMetaRegistryMock()
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Processor name parameter is required but is not set');

        $driver->sendToProcessor(new Message());
    }

    public function testShouldThrowExceptionIfProcessorQueueNameParameterIsNotSet()
    {
        $driver = new AmqpDriver(
            $this->createPsrContextMock(),
            new Config('', '', '', '', '', ''),
            $this->createQueueMetaRegistryMock()
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Queue name parameter is required but is not set');

        $message = new Message();
        $message->setProperty(Config::PARAMETER_PROCESSOR_NAME, 'processor');

        $driver->sendToProcessor($message);
    }

    public function testShouldSetupBroker()
    {
        $routerTopic = new AmqpTopic('');
        $routerQueue = new AmqpQueue('');

        $processorQueue = new AmqpQueue('');

        $context = $this->createPsrContextMock();
        // setup router
        $context
            ->expects($this->at(0))
            ->method('createTopic')
            ->willReturn($routerTopic)
        ;
        $context
            ->expects($this->at(1))
            ->method('createQueue')
            ->willReturn($routerQueue)
        ;
        $context
            ->expects($this->at(2))
            ->method('declareTopic')
            ->with($this->identicalTo($routerTopic))
        ;
        $context
            ->expects($this->at(3))
            ->method('declareQueue')
            ->with($this->identicalTo($routerQueue))
        ;
        $context
            ->expects($this->at(4))
            ->method('bind')
            ->with($this->identicalTo($routerTopic), $this->identicalTo($routerQueue))
        ;
        // setup processor queue
        $context
            ->expects($this->at(5))
            ->method('createQueue')
            ->willReturn($processorQueue)
        ;
        $context
            ->expects($this->at(6))
            ->method('declareQueue')
            ->with($this->identicalTo($processorQueue))
        ;

        $meta = new QueueMetaRegistry(new Config('', '', '', '', '', ''), [
            'default' => [],
        ], 'default');

        $driver = new AmqpDriver(
            $context,
            new Config('', '', '', '', '', ''),
            $meta
        );

        $driver->setupBroker();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|AmqpContext
     */
    private function createPsrContextMock()
    {
        return $this->createMock(AmqpContext::class);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|Producer
     */
    private function createPsrProducerMock()
    {
        return $this->createMock(Producer::class);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|QueueMetaRegistry
     */
    private function createQueueMetaRegistryMock()
    {
        return $this->createMock(QueueMetaRegistry::class);
    }
}
