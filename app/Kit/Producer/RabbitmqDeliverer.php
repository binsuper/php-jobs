<?php

namespace Gino\Jobs\Kit\Producer;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitmqDeliverer implements IDeliverer {

    private $__host;
    private $__port;
    private $__user;
    private $__pass;
    private $__vhost;

    /**
     * prefetch count
     *
     * @var int
     */
    private $__qos;

    /**
     *
     * @var AMQPStreamConnection | AMQPSSLConnection
     */
    private $__conn;

    /**
     *
     * @var AMQPChannel
     */
    private $__channel;

    /**
     * dead letter exchange
     * 死信的交换器
     *
     * @var string
     */
    private $__dlx = false;

    /**
     * Dead letter routing key
     * 死信的routing key， 配合dead letter exchange使用，必须一起设置
     *
     * @var string
     */
    private $__dlrk = false;

    /**
     * 如果不为false，将使用AMQPSSLConnection方式连接
     *
     * @var array
     */
    private $__ssl = [];

    /**
     * 连接参数
     *
     * @var array
     */
    private $__options = [];

    private $__exchanges = [];

    private $__exchange_name;

    public function __construct($config) {
        $this->__host = $config['host'] ?? '127.0.0.1';
        $this->__port = $config['port'] ?? 5672;
        $this->__user = $config['user'] ?? '';
        $this->__pass = $config['pass'] ?? '';
        $this->__vhost = $config['vhost'] ?? '/';
        $this->__ssl = $config['ssl'] ?? [];
        $this->__options = $config['options'] ?? false;

        $this->connect();
    }

    /**
     * 连接
     */
    public function connect() {
        if (empty($this->__ssl)) {
            $this->__conn = new AMQPStreamConnection($this->__host, $this->__port, $this->__user, $this->__pass, $this->__vhost,
                $this->__options['insist'] ?? false,
                $this->__options['login_method'] ?? 'AMQPLAIN',
                $this->__options['login_response'] ?? null,
                $this->__options['locale'] ?? 'en_US',
                $this->__options['connection_timeout'] ?? 3,
                $this->__options['read_write_timeout'] ?? 3,
                null,
                $this->__options['keepalive'] ?? false,
                $this->__options['heartbeat'] ?? 0
            );
        } else {
            $this->__conn = new AMQPSSLConnection($this->__host, $this->__port, $this->__user, $this->__pass, $this->__vhost, $this->__ssl, $this->__options);
        }
        $this->__channel = $this->__conn->channel();
    }

    /**
     * 关闭连接
     *
     * @return mixed|null
     */
    public function close() {
        if ($this->__conn instanceof AMQPStreamConnection) {
            $this->__conn->close();
        }
    }

    /**
     * @param $exchange
     * @return $this
     */
    public function exchange($exchange) {
        if (empty($this->__exchanges[$exchange])) {
            //声明exchange
            $channel = $this->__conn->channel();
            $channel->exchange_declare($exchange, 'topic', false, true, false);
            $this->__exchanges[$exchange] = $channel;
        }
        $this->__channel = $this->__exchanges[$exchange];
        $this->__exchange_name = $exchange;
        return $this;
    }

    /**
     * 投递消息
     *
     * @param string $key
     * @param string $msg
     * @return bool
     */
    public function send(string $key, string $msg): bool {
        $this->__channel->basic_publish(new AMQPMessage($msg), $this->__exchange_name, $key);
        return true;
    }

}

