<?php

require_once 'vendor/autoload.php';

if (!defined('SOCKET_EAGAIN')) {
    define('SOCKET_EAGAIN', 11);
}

use Gino\Jobs\Kit\Producer\IDeliverer;
use Gino\Jobs\Kit\Producer\RabbitmqProducer;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitmqDeliverer implements IDeliverer {

    private $__connection;
    private $__channel;

    public function __construct($exchange) {
        $this->__connection = new AMQPStreamConnection('127.0.0.1', 5672, 'admin', '123456');
        $this->__channel    = $this->__connection->channel();
        //声明exchange
        $this->__channel->exchange_declare($exchange, 'topic', false, true, false);
    }

    /**
     * 投递
     * @param string $key
     * @param string $msg
     * @return bool
     */
    public function send(string $key, string $msg): bool {
        try {
            $this->__channel->basic_publish(new AMQPMessage($msg), $key);
            return true;
        } catch (\Throwable $ex) {
            return false;
        }
    }

}

$producer = new RabbitmqProducer(new RabbitmqDeliverer('phpjob'), 'phpjob');
for ($i = 0; $i != 10000; $i++) {
    $producer->setBody('no.' . $i)->deliver();
}
