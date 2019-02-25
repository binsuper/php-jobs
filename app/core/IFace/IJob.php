<?php

namespace Gino\Jobs\Core\IFace;

use Gino\Jobs\Core\IFace\IQueueMessage;

/**
 *
 * @author GinoHuang <binsuper@126.com>
 */
interface IJob {

    /**
     * 收到消息时执行
     * 
     * @param IQueueMessage $msg
     * @return bool 执行成功返回true, 执行失败返回false
     */
    public function onReceive(IQueueMessage $msg): bool;
}