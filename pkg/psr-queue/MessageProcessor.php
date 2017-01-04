<?php
namespace Enqueue\Psr;

interface MessageProcessor
{
    /**
     * The method has to return eitherResult::ACK, Result::REJECT, Result::REQUEUE string or instance of Result.
     *
     * @param Message $message
     * @param Context $context
     *
     * @return string|Result
     */
    public function process(Message $message, Context $context);
}
