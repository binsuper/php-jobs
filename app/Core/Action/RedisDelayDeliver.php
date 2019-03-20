<?php

namespace Gino\Jobs\Core\Action;

use Gino\Jobs\Core\IFace\IQueueMessage;
use Gino\Jobs\Core\IFace\IConsumer;
use Gino\Jobs\Core\Queue\Queue;
use Gino\Jobs\Core\Logger;
use \Gino\Jobs\Core\Utils;
use Gino\Jobs\Core\IFace\IQueueDelay;

/**
 * 分派延时队列任务
 * @author GinoHuang <binsuper@126.com>
 */
class RedisDelayDeliver implements IConsumer {

    /**
     * 收到消息时执行
     * 
     * @param IQueueMessage $msg
     * @return bool 执行成功返回true, 执行失败返回false
     */
    public function consume(IQueueMessage $msg): bool {
        $logger = Logger::getLogger();
        try {
            $data = json_decode($msg->getBody(), true);
            if (!$data) {
                //无法解析的消息直接丢弃
                $logger->log('unresolved data. msg: ' . (string) $msg, Logger::LEVEL_ERROR, 'delay');
                $msg->reject(false);
                return false;
            }
            if (empty($data['target']) || empty($data['delay']) || empty($data['payload'])) {
                //异常的消息直接丢弃
                $logger->log('incomplete data. msg: ' . (string) $msg, Logger::LEVEL_ERROR, 'delay');
                $msg->reject(false);
                return false;
            }
            //投递到延时队列
            $queue = Queue::getDelayQueue();
            if (!($queue instanceof IQueueDelay)) { //不支持延时队列
                $msg->reject(false);
                return false;
            }
            if (!$queue->pushDelay($data['target'], $data['payload'], intval($data['delay']))) {
                $msg->reject(true);
                return false;
            }
            $msg->ack();
        } catch (\Throwable $ex) {
            Utils::catchError($logger, $ex);
            $msg->reject(true);
            return false;
        }
        return true;
    }

}
