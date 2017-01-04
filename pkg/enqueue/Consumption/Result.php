<?php
namespace Enqueue\Consumption;

use Enqueue\Psr\Message as PsrMessage;
use Enqueue\Psr\Result as PsrResult;

class Result extends PsrResult
{
    /**
     * @var string
     */
    private $reason;

    /**
     * @var PsrMessage|null
     */
    private $reply;

    /**
     * @return PsrMessage|null
     */
    public function getReply()
    {
        return $this->reply;
    }

    /**
     * @param PsrMessage|null $reply
     */
    public function setReply(PsrMessage $reply = null)
    {
        $this->reply = $reply;
    }

    /**
     * @param string $status
     * @param string $reason
     */
    public function __construct($status, $reason = '')
    {
        parent::__construct($status);

        $this->reason = (string) $reason;
    }

    /**
     * @return string
     */
    public function getReason()
    {
        return $this->reason;
    }

    /**
     * @param string $reason
     *
     * @return Result
     */
    public static function ack($reason = '')
    {
        return new static(self::ACK, $reason);
    }

    /**
     * @param string $reason
     *
     * @return Result
     */
    public static function reject($reason)
    {
        return new static(self::REJECT, $reason);
    }

    /**
     * @param string $reason
     *
     * @return Result
     */
    public static function requeue($reason = '')
    {
        return new static(self::REQUEUE, $reason);
    }

    /**
     * @param PsrMessage  $replyMessage
     * @param string|null $reason
     *
     * @return Result
     */
    public static function reply(PsrMessage $replyMessage, $reason = '')
    {
        $result = static::ack($reason);
        $result->setReply($replyMessage);

        return $result;
    }
}
