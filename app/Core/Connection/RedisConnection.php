<?php

namespace Gino\Jobs\Core\Connection;

use Closure;
use Gino\Jobs\Core\Exception\ConnectionException;
use Gino\Jobs\Core\IFace\IConnection;

/**
 * @mixin \Redis
 */
class RedisConnection implements IConnection {

    private $host;
    private $port;
    private $auth;
    private $db;
    private $connect_timeout;

    /** @var bool 长连接 */
    private $persists = false;

    private $connect_options = [];

    private $options = [];

    /** @var \Redis */
    private $conn;

    public function __destruct() {
        try {
            $this->close();
        } catch (\Throwable $ex) {
            // nothing
        }
    }

    public function __call($method, $arguments) {
        if (method_exists($this->conn, $method)) {
            return call_user_func([$this->conn, $method], ...$arguments);
        }
        throw new \RuntimeException("undefined method {$method} for object " . static::class);
    }

    /**
     * @inheritDoc
     */
    public function setOptions(array $options) {
        $this->options         = $options;
        $this->host            = $options['host'] ?? '127.0.0.1';
        $this->port            = $options['port'] ?? 6379;
        $this->db              = $options['db'] ?? 0;
        $this->auth            = $options['pass'] ?? '';
        $this->connect_timeout = $options['timeout'] ?? 3;
        $this->connect_options = $options['options'] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function getOptions() {
        return $this->options;
    }

    /**
     * @inheritDoc
     * @throws ConnectionException
     */
    public function connect(): void {
        // 已连接
        if ($this->isConnected()) {
            return;
        }

        $method = 'connect';
        if ($this->persists) {
            $method = 'p' . $method;
        }

        // 连接
        $conn  = new \Redis();
        $state = $conn->{$method}($this->host, $this->port, $this->connect_timeout);

        if (!$state) {
            throw new ConnectionException($conn->getLastError());
        }

        if (!empty($this->auth) && !$conn->auth($this->auth)) {
            throw new ConnectionException($conn->getLastError());
        }

        if (!empty($this->db) && !$conn->select($this->db)) {
            throw new ConnectionException($conn->getLastError());
        }

        $this->conn = $conn;
    }

    /**
     * @inheritDoc
     * @throws ConnectionException
     */
    public function reconnect(): void {
        if ($this->isConnected()) {
            $this->close();
        }
        $this->connect();
    }

    /**
     * @inheritDoc
     */
    public function isConnected(): bool {
        return $this->conn && $this->conn->isConnected();
    }

    /**
     * @inheritDoc
     */
    public function close(): void {
        if ($this->conn) {
            $this->conn->close();
        }
        $this->conn = null;
    }

    /**
     * @return \Redis
     */
    public function getConnection() {
        return $this->conn;
    }

    /**
     * @inheritDoc
     * @throws \RedisException|ConnectionException
     */
    public function retry(Closure $command, int $max, int $interval = 0) {
        try {
            return call_user_func($command);
        } catch (\RedisException | ConnectionException $ex) {
            // 非连接异常，阻断
            if ($this->isConnected()) {
                throw $ex;
            }

            //尝试重连
            while ($max--) {
                try {
                    $this->reconnect();
                    // 重新执行
                    return call_user_func($command);
                } catch (ConnectionException $e) {
                    $interval > 0 && sleep($interval);
                }
            }

            throw $ex;
        }
    }

}