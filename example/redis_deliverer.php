<?php

require_once 'vendor/autoload.php';

use Gino\Jobs\Kit\Producer\IDeliverer;
use Gino\Jobs\Kit\Producer\RedisProducer;

class RedisDeliverer implements IDeliverer {

    private $__redis;

    public function __construct() {
        $this->__redis = new Redis();
        $this->__redis->connect('127.0.0.1', 6379);
    }

    /**
     * 投递
     * @param string $key
     * @param string $msg
     * @return bool
     */
    public function send(string $key, string $msg): bool {
        return $this->__redis->lPush($key, $msg) ? true : false;
    }

}

$producer = new RedisProducer(new RedisDeliverer(), 'test');
for ($i = 0; $i != 100000; $i++) {
    $producer->setBody('no.' . $i)->delay(rand(5, 180), 'php-jobs-delay')->deliver();
}
