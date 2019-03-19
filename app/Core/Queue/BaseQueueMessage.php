<?php

namespace Gino\Jobs\Core\Queue;

use \Gino\Jobs\Core\IFace\IQueueMessage;
use Gino\Jobs\Core\IFace\IQueueDriver;

/**
 * 队列数据基类
 * @author GinoHuang <binsuper@126.com>
 */
abstract class BaseQueueMessage implements IQueueMessage {

    const FB_ACK    = 1;
    const FB_REJECT = 2;

    protected $_driver;
    protected $_msg;
    private $__feedback = 0;

    public function __construct(IQueueDriver $driver, $msg) {
        $this->_driver = $driver;
        $this->_msg    = $msg;
    }

    public function ack(): bool {
        if ($this->__feedback != 0) {
            if ($this->__feedback === self::FB_ACK) {
                return true;
            }
            return false;
        }
        $ret = $this->_ack();
        if ($ret) {
            $this->__feedback = true;
        }
        return $ret;
    }

    public function reject(bool $requeue): bool {
        if ($this->__feedback != 0) {
            if ($this->__feedback === self::FB_REJECT) {
                return true;
            }
            return false;
        }
        $ret = $this->_reject($requeue);
        if ($ret) {
            $this->__feedback = true;
        }
        return $ret;
    }

    /**
     * 获取队列名称
     * @return string
     */
    public function getQueueName(): string {
        return $this->_driver->getQueueName();
    }

    abstract protected function _ack(): bool;

    abstract protected function _reject(bool $requeue): bool;
}
