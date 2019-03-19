<?php

namespace Gino\Jobs\Kit\Producer;

/**
 * 投递者接口
 * @author GinoHuang <binsuper@126.com>
 */
interface IDeliverer {

    /**
     * 投递
     * @param string $key
     * @param string $msg
     * @return bool
     */
    public function send(string $key, string $msg): bool;
}
