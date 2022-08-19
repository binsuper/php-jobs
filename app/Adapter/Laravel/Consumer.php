<?php

namespace Gino\Jobs\Adapter\Laravel;

use Gino\Jobs\Adapter\Laravel\Kernel;
use Gino\Jobs\Core\IFace\IConsumer;
use Gino\Jobs\Core\IFace\IQueueMessage;
use Gino\Jobs\Core\Logger;
use Gino\Jobs\Core\Utils;

/**
 * Laravel框架 - 消费者
 *
 * @author Gino Huang <binsuper@126.com>
 */
abstract class Consumer implements IConsumer {

    public function consume(IQueueMessage $msg): bool {
        return $this->onConsume($msg);
    }

    /**
     * 消费消息
     *
     * @param IQueueMessage $msg
     * @return bool 执行成功返回true, 执行失败返回false
     */
    public abstract function onConsume(IQueueMessage $msg): bool;

}