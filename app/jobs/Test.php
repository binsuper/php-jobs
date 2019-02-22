<?php

namespace Gino\Jobs\Jobs;

/**
 *
 * @author GinoHuang <binsuper@126.com>
 */
class Test implements \Gino\Jobs\Core\IFace\IJob {

    public function exec() {
        \Gino\Jobs\Core\Logger::getLogger()->log('exec job: test-' . microtime(true));
        return true;
    }

}
