<?php

namespace Gino\Jobs\Kit\Producer;

use http\Exception\InvalidArgumentException;

/**
 * 消息队列 队列消息投递
 *
 * @author GinoHuang <binsuper@126.com>
 */
class RedisDeliverer extends Deliverer {

    private $__host;
    private $__port;
    private $__auth;
    private $__db;

    /**
     * @var \Redis
     */
    protected $_conn;

    public function __construct(array $config) {
        $this->__host = $config['host'] ?? '127.0.0.1';
        $this->__port = $config['port'] ?? 6379;
        $this->__db   = $config['db'] ?? 0;
        $this->__auth = $config['pass'] ?? '';
        $this->_conn  = new \Redis();

        $this->connect();
    }

    /**
     * 连接
     */
    public function connect() {
        try {
            if (empty($this->__host) || empty($this->__port)) {
                throw new \Exception('redis host or port is empty');
            }
            @$this->_conn->connect($this->__host, $this->__port, 3);
            if (!empty($this->__auth)) {
                $this->_conn->auth($this->__auth);
            }
            if (!empty($this->__db)) {
                $this->_conn->select($this->__db);
            }
        } catch (\RedisException $ex) {
            throw $ex;
        }
    }

    /**
     * 关闭连接
     *
     * @return bool
     */
    public function close(): bool {
        if ($this->_conn) {
            $this->_conn->close();
            $this->_conn = null;
        }
        return true;
    }

    /**
     * 投递消息
     *
     * @return bool
     */
    public function send(): bool {
        $msg   = $this->data('message');
        $queue = $this->data('queue');
        $delay = $this->data('delay', 0);

        if ($delay > 0) {
            $data  = [
                'payload' => $msg,
                'target'  => $queue,
                'delay'   => $delay
            ];
            $msg   = json_encode($data, JSON_UNESCAPED_UNICODE);
            $queue = $this->getDelayQueueName();
            if (empty($queue)) {
                throw new \InvalidArgumentException('"delay_queue" is missing');
            }
        }
        return (bool)$this->_conn->lPush($queue, $msg);
    }

    /**
     * 获取延迟队列名称
     *
     * @return string
     */
    public function getDelayQueueName(): string {
        return $this->data('delay_queue', '');
    }

}

