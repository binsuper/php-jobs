<?php

namespace Gino\Jobs\Core\Connection;

use Gino\Jobs\Core\Exception\ConnectionException;
use Gino\Jobs\Core\IFace\IConnection;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitmqConnection implements IConnection {

    private $options = [];

    private $host;
    private $port;
    private $user;
    private $password;
    private $vhost;

    /**
     * 如果不为false，将使用AMQPSSLConnection方式连接
     *
     * @var array
     */
    private $ssl = [];

    /**
     * 连接参数
     *
     * @var array
     */
    private $connect_options = [];

    /** @var AMQPStreamConnection|AMQPSSLConnection */
    private $conn;

    /**
     * @inheritDoc
     */
    public function setOptions(array $options) {
        $this->options         = $options;
        $this->host            = $options['host'] ?? '127.0.0.1';
        $this->port            = $options['port'] ?? 5672;
        $this->user            = $options['user'] ?? '';
        $this->password        = $options['pass'] ?? '';
        $this->vhost           = $options['vhost'] ?? '/';
        $this->ssl             = $options['ssl'] ?? [];
        $this->connect_options = $options['options'] ?? false;
    }

    /**
     * @inheritDoc
     */
    public function getOptions() {
        return $this->options;
    }

    /**
     * @inheritDoc
     */
    public function connect(): void {
        // 已连接
        if ($this->isConnected()) {
            return;
        }

        //开始新的连接
        if (empty($this->ssl)) {
            $this->conn = new AMQPStreamConnection($this->host, $this->port, $this->user, $this->password, $this->vhost,
                $this->connect_options['insist'] ?? false,
                $this->connect_options['login_method'] ?? 'AMQPLAIN',
                $this->connect_options['login_response'] ?? null,
                $this->connect_options['locale'] ?? 'en_US',
                $this->connect_options['connection_timeout'] ?? 3,
                $this->connect_options['read_write_timeout'] ?? 3,
                null,
                $this->connect_options['keepalive'] ?? false,
                $this->connect_options['heartbeat'] ?? 0
            );
        } else {
            $this->conn = new AMQPSSLConnection($this->host, $this->port, $this->user, $this->password, $this->vhost, $this->ssl, $this->connect_options);
        }

    }

    /**
     * @inheritDoc
     */
    public function reconnect(): void {
        if ($this->conn) {
            $this->conn->reconnect();
            return;
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
    }

    /**
     * @return AMQPSSLConnection|AMQPStreamConnection
     */
    public function getConnection() {
        return $this->conn;
    }


    /**
     * @inheritDoc
     */
    public function retry(\Closure $command, int $max, int $interval = 0) {
        try {
            return call_user_func($command);
        } catch (ConnectionException $ex) {
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