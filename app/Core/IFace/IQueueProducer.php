<?php

namespace Gino\Jobs\Core\IFace;

/**
 * 队列生产者
 * @author GinoHuang <binsuper@126.com>
 */
interface IQueueProducer {

    /**
     * 往队列中投递消息
     * @param string $body
     * @return bool
     */
    public function push(string $body, ?string $key = null): bool;
}
