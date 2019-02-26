<?php

namespace Gino\Jobs\Core\Queue;

use \Gino\Jobs\Core\IFace\IQueueMessage;
use \Gino\Jobs\Core\IFace\IQueueDriver;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;

/**
 * 
 * @author GinoHuang <binsuper@126.com>
 */
class RabbitmqQueue implements IQueueDriver {

    private $__host;
    private $__port;
    private $__user;
    private $__pass;
    private $__vhost;
    private $__qos;

    /**
     *
     * @var AMQPStreamConnection 
     */
    private $__handler;

    /**
     *
     * @var AMQPChannel 
     */
    private $__channel;

    /**
     * 
     * @var 交换器名称 
     */
    private $__exchange_name;

    /**
     * 
     * @var 队列名称 
     */
    private $__queue_name;

    /**
     * 
     * @var 路由名 
     */
    private $__binding_key;

    /**
     * 获取连接
     * @param array $config
     * @param string $topic_name
     * @return IQueueDriver 失败返回false
     */
    public static function getConnection(array $config, string $topic_name, array $topic_config = []) {
        $exchange_name = $topic_config['exchange'] ?? '';
        if (empty($exchange_name)) {
            throw new Exception('exchange must be set');
        }
        return new self($config, $topic_name, $exchange_name);
    }

    private function __construct(array $config, string $binding_key, string $exchange_name) {
        $this->__binding_key   = $binding_key;
        $this->__exchange_name = $exchange_name;
        $this->__host          = $config['host'] ?? '127.0.0.1';
        $this->__port          = $config['port'] ?? 5672;
        $this->__user          = $config['user'] ?? '';
        $this->__pass          = $config['pass'] ?? '';
        $this->__vhost         = $config['vhost'] ?? '/';
        $this->__qos           = $config['qos'] ?? 1;
        $this->__connect();
    }

    public function __destruct() {
        try {
            $this->close();
        } catch (\Exception $ex) {
            
        }
    }

    /**
     * 连接
     */
    private function __conntect() {
        try {
            $this->__handler = new AMQPStreamConnection($this->__host, $this->__port, $this->__user, $this->__pass, $this->__vhost);

            try {
                $this->__channel = $this->__handler->channel();
                $this->__channel->exchange_declare($this->__exchange_name, 'topic', false, true, false);
                if (empty($this->__queue_name)) {
                    list($this->__queue_name,, ) = $this->__channel->queue_declare('', false, false, true, false);
                } else {
                    $this->__channel->queue_declare($this->__queue_name, false, false, false, false);
                }
                $this->__channel->queue_bind($this->__queue_name, $this->__exchange_name, $this->__binding_key);

                $this->__channel->basic_qos(null, $this->__qos, null);
                $this->__channel->basic_consume($this->__queue_name, '', false, false, false, false, $callback);
            } catch (\Exception $ex) {
                // Failed to get channel
                // Best practice is to catch the specific exceptions and handle accordingly.
                // Either handle the message (and exit) or retry
            }
        } catch (\Exception $e) {
            // Failed to get connection. 
            // Best practice is to catch the specific exceptions and handle accordingly.
            // Either handle the message (and exit) or retry

            if ($YouWantToRetry) {
                sleep(5);  // Time should greacefully decrade based on "connectionAttempts"
            } elseif ($YouCanHandleTheErrorAndWantToExitGraceully) {
                $connectionRequired = false;
            } elseif ($YouCannotHandleTheErrorAndWantToGetOutOfHere) {
                throw ($e);
            }
        }
    }

    public function reconnect() {
        
    }

    /**
     * 关闭连接
     * @return mixed|null
     */
    public function close() {
        return $this->__handler->close();
    }

    /**
     * 执行命令
     * @param callable $callback
     * @return mixed
     */
    private function __command($callback) {
        try {
            return call_user_func($callback);
        } catch (\Exception $ex) {
            
        }
    }

    /**
     * @return bool
     */
    public function isConntected(): bool {
        return $this->__handler->isConnected() ? true : false;
    }

    public function pop(): IQueueMessage {
        
    }

    /**
     * 返回当前队列长度
     * @return int
     */
    public function size(): int {
        $channel = $conn->channel();

        // queue_declare第二个参数$passive需要为true
        $declare_info = $channel->queue_declare($queue_name, true);

        $message_count = $declare_info[1];
        return $message_count;
    }

}
