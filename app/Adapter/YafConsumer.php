<?php

namespace Gino\Jobs\Adapter;

use Gino\Jobs\Core\IFace\IConsumer;
use \Gino\Jobs\Core\IFace\IQueueMessage;

/**
 * yaf框架 - 消费者
 * 
 * @author Gino Huang <binsuper@126.com>
 */
abstract class YafConsumer implements IConsumer {

    public function consume(IQueueMessage $msg): bool {
        $result = false;
        \Yaf\Application::app()->bootstrap()->execute(function() use(&$result, $msg) {
            $result = $this->onConsume($msg);
        });
        return $result;
    }

    /**
     * 消费消息
     * 
     * @param IQueueMessage $msg
     * @return bool 执行成功返回true, 执行失败返回false
     */
    public function onConsume(IQueueMessage $msg): bool;
}
