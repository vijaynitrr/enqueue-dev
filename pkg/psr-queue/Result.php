<?php
namespace Enqueue\Psr;

class Result
{
    /**
     * Use this constant when the message is processed successfully and the message could be removed from the queue.
     */
    const ACK = 'enqueue.message_queue.consumption.ack';

    /**
     * Use this constant when the message is not valid or could not be processed
     * The message is removed from the queue.
     */
    const REJECT = 'enqueue.message_queue.consumption.reject';

    /**
     * Use this constant when the message is not valid or could not be processed right now but we can try again later
     * The original message is removed from the queue but a copy is publsihed to the queue again.
     */
    const REQUEUE = 'enqueue.message_queue.consumption.requeue';

    /**
     * @var string
     */
    private $status;

    /**
     * @param string $status
     */
    public function __construct($status)
    {
        $this->status = (string) $status;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->status;
    }
}
