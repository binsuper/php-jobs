<?php

namespace Gino\Jobs\Core\Queue;

use Gino\Jobs\Core\Queue\RedisQueue;

/**
 * 队列消息
 * @author GinoHuang <binsuper@126.com>
 */
class RedisMessage extends BaseQueueMessage {

    protected $_body;

    public function __construct(RedisQueue $driver, $msg) {
        parent::__construct($driver, $msg);
        $this->_body = $this->_msg;
    }

    public function __toString() {
        return $this->_body;
    }

    /**
     * 消息实体
     */
    public function getBody() {
        return $this->_body;
    }

    /**
     * 正确应答
     * @return boolean
     */
    protected function _ack(): bool {
        return true;
    }

    /**
     * 拒绝消息
     * @param bool $requeue true表示将消息重新入队列，false则丢弃该消息
     * @return boolean
     */
    protected function _reject(bool $requeue): bool {
        if ($requeue) {
            //将消息重新入队列
            return $this->_driver->repush($this);
        }
        return true;
    }

}
