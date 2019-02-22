<?php

namespace Gino\Jobs\Core\Queue;

use \Gino\Jobs\Core\IFace\IQueueDriver;
use \Gino\Jobs\Core\IFace\IQueueMessage;

/**
 * 队列消息
 * @author GinoHuang <binsuper@126.com>
 */
class RedisMessage implements IQueueMessage {

    private $__driver;
    private $__data;

    public function __construct(IQueueDriver $driver, $data) {
        $this->__driver = $driver;
        $this->__data   = $data;
    }

    public function __toString() {
        return $this->__data;
    }

}
