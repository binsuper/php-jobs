<?php


namespace Gino\Jobs\Core\Queue;


use Gino\Jobs\Core\Exception\UnsupportException;
use Gino\Jobs\Core\IFace\IQueueDriver;
use Gino\Jobs\Core\IFace\IQueueMessage;

class QueueMsgGroup extends \ArrayObject implements IQueueMessage {

    private $__ack_count    = 0;
    private $__reject_count = 0;

    public function __toString() {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getBody() {
        throw new UnsupportException();
    }

    /**
     * @inheritDoc
     */
    public function ack(): bool {
        $this->__ack_count++;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function reject(bool $requeue): bool {
        $this->__reject_count++;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isAck(): bool {
        throw new UnsupportException();
    }

    /**
     * @inheritDoc
     */
    public function isReject(): bool {
        throw new UnsupportException();
    }

    /**
     * @inheritDoc
     */
    public function getQueueName(): string {
        throw new UnsupportException();
    }

    /**
     * @param int $count
     * @return int
     */
    public function acks(int $count = 0): int {
        $count > 0 && ($this->__ack_count += $count);
        return $this->__ack_count;
    }

    /**
     * @param int $count
     * @return int
     */
    public function rejects(int $count = 0): int {
        $count > 0 && ($this->__reject_count += $count);
        return $this->__reject_count;
    }

    /**
     * @inheritDoc
     */
    public function getQueueDriver(): IQueueDriver {
        throw new UnsupportException();
    }

}