<?php

namespace Gino\Jobs\Core\Queue;

use \Gino\Jobs\Core\IFace\IQueueMessage;

/**
 * 
 * @author GinoHuang <binsuper@126.com>
 */
class RedisQueue implements \Gino\Jobs\Core\IFace\IQueueDriver {

    private $__host;
    private $__port;
    private $__auth;
    private $__db;
    private $__handler;
    private $__queue_name;

    public function __construct(array $config, string $queue_name) {
        $this->__queue_name = $queue_name;
        $this->__host       = $config['host'] ?? '127.0.0.1';
        $this->__port       = $config['port'] ?? 6379;
        $this->__db         = $config['database'] ?? 0;
        $this->__auth       = $config['password'] ?? '';
        $this->__handler    = new Redis();
        $this->__connect();
    }

    /**
     * 连接Redis
     * @throws \RedisException
     */
    private function __connect() {
        try {
            if (empty($this->__host) || empty($this->__port)) {
                throw new \RedisException('redis host or port is empty');
            }
            $this->__handler->connect($this->__host, $this->__port, 3);
            if (!empty($this->__auth)) {
                $this->__handler->auth($this->__auth);
            }
            if (!empty($this->__db)) {
                $this->__handler->select($this->__db);
            }
        } catch (\RedisException $ex) {
            throw $ex;
        }
    }

    /**
     * @return bool
     */
    public function isConntected(): bool {
        return $this->__handler->isConnected();
    }

    /**
     * 重连
     */
    public function reconnect() {
        if (!$this->isConnected()) {
            $this->__connect();
        }
    }

    /**
     * 返回当前队列长度
     * @return int
     */
    public function size(): int {
        return $this->__handler->lLen();
    }

    public function pop() {
        $data = $this->__handler->rPop();
        if ($data === NULL) {
            return NULL;
        }
        $msg = new RedisMessage($this, $data);
    }

}
