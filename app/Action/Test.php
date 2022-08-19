<?php

namespace Gino\Jobs\Action;

use Gino\Jobs\Core\IFace\ICommand;
use Gino\Jobs\Core\IFace\IQueueMessage;
use Gino\Jobs\Core\IFace\IConsumer;
use Gino\Jobs\Core\Logger;
use Gino\Jobs\Core\Queue\QueueMsgGroup;

/**
 *
 * @author GinoHuang <binsuper@126.com>
 */
class Test implements IConsumer, ICommand {

    /**
     * 收到消息时执行q
     *
     * @param IQueueMessage $msg
     * @return bool 执行成功返回true, 执行失败返回false
     */
    public function consume(IQueueMessage $msg): bool {
        if ($msg instanceof QueueMsgGroup) {

            foreach ($msg as $m) {
                /**
                 * @var $m IQueueMessage
                 */
                Logger::getLogger()->log('receive msgs： ' . $m->getBody());
                $m->ack();
            }
            $msg->acks(count($msg));
            return true;
        } else {
            Logger::getLogger()->log('receive msg： ' . $msg->getBody());
            if (rand(0, 1) == 1) {
                $msg->reject(false);
            } else {
                $msg->ack();
            }
            return true;
        }
    }

    /**
     * @inheritDoc
     */
    public function execute(array $params) {
        var_dump($params);
    }

}
