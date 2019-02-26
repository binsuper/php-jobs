<?php

namespace Gino\Jobs\Core\Queue;

use \Gino\Jobs\Core\IFace\IQueueMessage;
use Gino\Jobs\Core\IFace\IQueueDriver;

/**
 * 队列数据基类
 * @author GinoHuang <binsuper@126.com>
 */
abstract class BaseQueueMessage implements IQueueMessage {

    protected $_driver;
    protected $_msg;

    public function __construct(IQueueDriver $driver, $msg) {
        $this->_driver = $driver;
        $this->_msg    = $msg;
    }

}
