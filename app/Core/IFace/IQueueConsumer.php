<?php

namespace Gino\Jobs\Core\IFace;

/**
 * 队列驱动
 * @author GinoHuang <binsuper@126.com>
 */
interface IQueueConsumer {

    public function consume($callback);
}
