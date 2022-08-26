<?php

namespace Gino\Jobs\Kit\Producer;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPAbstractCollection;

/**
 * Rabbitmq 队列消息投递
 *
 * @author GinoHuang <binsuper@126.com>
 */
class RabbitmqDeliverer extends Deliverer {

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
    private $__mq_channel;

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

    /**
     * @var array
     */
    private $__exchanges = [];

    /**
     * @var string
     */
    private $__exchange_name;

    public function __construct($config) {
        $this->__host    = $config['host'] ?? '127.0.0.1';
        $this->__port    = $config['port'] ?? 5672;
        $this->__user    = $config['user'] ?? '';
        $this->__pass    = $config['pass'] ?? '';
        $this->__vhost   = $config['vhost'] ?? '/';
        $this->__ssl     = $config['ssl'] ?? [];
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
        $this->__mq_channel = $this->__conn->channel();
    }

    /**
     * 关闭连接
     *
     * @return bool
     */
    public function close(): bool {
        if ($this->__conn instanceof AMQPStreamConnection) {
            $this->__conn->close();
        }
        return true;
    }

    /**
     * @param $exchange
     * @return $this
     */
    public function exchange($exchange) {
        return $this->channel($exchange);
    }

    /**
     * 消息通道
     *
     * @param string|null $exchange
     * @return $this
     */
    public function channel(string $exchange = '') {
        parent::channel($exchange);
        if (empty($this->__exchanges[$this->_channel])) {
            //声明exchange
            $channel = $this->__conn->channel();
            $channel->exchange_declare($this->_channel, 'topic', false, true, false);
            $this->__exchanges[$this->_channel] = $channel;
        }
        $this->__mq_channel    = $this->__exchanges[$this->_channel];
        $this->__exchange_name = $this->_channel;
        return $this;
    }


    /**
     * 投递消息
     *
     * @param string $key
     * @param string $msg
     * @return bool
     */
    public function send(): bool {
        $msg      = $this->data('message');
        $exchange = $this->data('channel');
        $queue    = $this->data('queue');
        $delay    = $this->data('delay', 0);

        $properties = [];
        if ($delay > 0) {
            $properties['expiration'] = $delay;
            list($exchange, $queue) = $this->delayChange();
        }

        $this->__mq_channel->basic_publish(new AMQPMessage($msg, $properties), $exchange, $queue);
        return true;
    }

    public function delayChange() {
        $dlx_exchange      = $this->data('channel');
        $dlx_routingkey    = $this->data('queue');
        $delay             = $this->data('delay', 0);
        $delay_exchange    = $dlx_exchange . '#job-delay';
        $delay_routing_key = $delay;
        $delay_queue       = $delay_exchange . '#' . $delay_routing_key;

        $queue_arguments = [
            'x-dead-letter-exchange'    => [AMQPAbstractCollection::getSymbolForDataType(AMQPAbstractCollection::T_STRING_LONG), $dlx_exchange],
            'x-dead-letter-routing-key' => [AMQPAbstractCollection::getSymbolForDataType(AMQPAbstractCollection::T_STRING_LONG), $dlx_routingkey]
        ];

        // 声明延迟交换器
        $this->__mq_channel->exchange_declare($delay_exchange, 'topic', false, true, false);
        // 声明延迟队列
        $this->__mq_channel->queue_declare($delay_queue, false, true, false, false, false, $queue_arguments);
        $this->__mq_channel->queue_bind($delay_queue, $delay_exchange, $delay_routing_key);

        return [$delay_exchange, $delay_routing_key];
    }

}

