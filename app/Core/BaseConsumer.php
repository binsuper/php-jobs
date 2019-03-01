<?php

namespace Gino\Jobs\Core;

use IFace\IConsumer;
use IFace\IQueueMessage;

/**
 * 
 * @author Gino Huang <binsuper@126.com>
 */
abstract class BaseConsumer implements IConsumer {

    public function consume(IFace\IQueueMessage $msg): bool {
        
    }

}
