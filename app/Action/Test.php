<?php

namespace Gino\Jobs\Action;

use Gino\Jobs\Core\IFace\IQueueMessage;
use Gino\Jobs\Core\IFace\IJob;
use Gino\Jobs\Core\Logger;

/**
 *
 * @author GinoHuang <binsuper@126.com>
 */
class Test implements IJob {

    /**
     * 收到消息时执行
     * 
     * @param IQueueMessage $msg
     * @return bool 执行成功返回true, 执行失败返回false
     */
    public function consume(IQueueMessage $msg): bool {
        Logger::getLogger()->log('job: ' . $msg->getBody());
        return true;
    }

}
