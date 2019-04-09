<?php

namespace Gino\Jobs\Action;

use Gino\Jobs\Core\IFace\IQueueMessage;
use Gino\Jobs\Core\IFace\IConsumer;
use Gino\Jobs\Core\Logger;

/**
 *
 * @author GinoHuang <binsuper@126.com>
 */
class Test implements IConsumer {

    /**
     * 收到消息时执行
     * 
     * @param IQueueMessage $msg
     * @return bool 执行成功返回true, 执行失败返回false
     */
    public function consume(IQueueMessage $msg): bool {
        //usleep(10000);
        Logger::getLogger()->log('receive msg： ' . $msg->getBody());
        if (rand(0, 2) == 2) {
            return false;
        }
        if (rand(0, 1) == 1) {
            $msg->reject(false);
        } else {
            $msg->ack();
        }
        return true;
    }

}
