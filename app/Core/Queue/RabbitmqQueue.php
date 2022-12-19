<?php

namespace Gino\Jobs\Core\Queue;

use Gino\Jobs\Core\Connection\RabbitmqConnection;
use \Gino\Jobs\Core\IFace\IQueueMessage;
use Gino\Jobs\Core\IFace\{IConnection, IQueueDriver, IQueueProducer};
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use \Gino\Jobs\Core\Utils;
use \Gino\Jobs\Core\Exception\ConnectionException;
use PhpAmqpLib\Exception\{
    AMQPTimeoutException,
    AMQPProtocolChannelException
};
use PhpAmqpLib\Wire\AMQPAbstractCollection;

/**
 *
 * @author GinoHuang <binsuper@126.com>
 */
class RabbitmqQueue implements IQueueDriver, IQueueProducer {

    /** @var int prefetch count */
    private $__qos;

    /** @var AMQPChannel */
    private $__channel;

    /** @var string 交换器名称 */
    private $__exchange_name;

    /** @var string 队列名称 */
    private $__queue_name;

    /** @var string 路由名 */
    private $__binding_key;

    /** @var array 数据队列 */
    private $__data_queue = [];

    /** @var string dead letter exchange */
    private $__dlx = false;

    /** @var string Dead letter routing key，死信的routing key， 配合dead letter exchange使用，必须一起设置 */
    private $__dlrk = false;

    /** @var array 队列信息 */
    private $__queue_options = [];

    /** @var array 延迟队列信息 */
    private $__delay_options = [];

    /** @var bool 消费者 */
    private $is_consumer = false;

    /** @var RabbitmqConnection */
    protected $conn;

    /**
     * @inheritDoc
     */
    public static function make(string $queue_name, IConnection $conn, array $options = []): IQueueDriver {
        return new self($queue_name, $options, $conn);
    }

    private function __construct(string $routing_key, array $options, RabbitmqConnection $conn) {
        $exchange_name = $options['exchange'] ?? '';
        if (!$exchange_name) {
            throw new \InvalidArgumentException('exchange must be set');
        }

        $this->__binding_key   = $options['routing_key'] ?? $routing_key;
        $this->__exchange_name = $exchange_name;
        $this->__queue_name    = $exchange_name . '.' . $routing_key;

        $this->__dlx           = $options['dlx'] ?? '';
        $this->__dlrk          = $options['dlrk'] ?? '';
        $this->__queue_options = $options['options'] ?? [];
        $this->__delay_options = $options['rabbitmq_delay'] ?? [];
        $this->__qos           = $this->__queue_options['qos'] ?? 1;

        $this->conn = $conn;

        if ((!empty($this->__dlx) ^ !empty($this->__dlrk)) != 0) {
            if (empty($this->__dlx) || empty($this->__dlrk)) {
                throw new \InvalidArgumentException('param "dlx" and "dlrk" must be setting together');
            }
        }

        $this->init();
    }

    public function __destruct() {
        if ($this->conn && $this->__channel && $this->conn->isConnected()) {
            $this->__channel->close();
        }
    }

    /**
     * 初始化
     */
    public function init() {
        $this->declareDeleyQueue();
        $this->declareQueue();
    }

    /**
     * 执行命令
     *
     * @param callable $callback
     *
     * @return mixed
     */
    private function __command($callback) {
        return $this->conn->retry($callback, 3, 1);
    }

    /**
     * 从队列中弹出一条消息
     *
     * @return IQueueMessage 没有数据时返回NULL
     */
    public function pop() {
        try {
            $this->setConsumer();
            $data = $this->__command(function () {
                if (!$this->__channel) {
                    throw new ConnectionException();
                }
                try {
                    $this->__channel->wait(null, false, 0.001);
                    return array_pop($this->__data_queue);
                } catch (AMQPTimeoutException $ex) { //超时，不做处理
                } catch (AMQPProtocolChannelException $ex) {

                } catch (\Exception $ex) {
                    Utils::catchError($ex);
                    throw new ConnectionException($ex->getMessage());
                } catch (\Throwable $ex) {
                    Utils::catchError($ex);
                    throw new ConnectionException($ex->getMessage());
                }
            });
        } catch (\Exception $ex) {
            Utils::catchError($ex);
            return null;
        }
        if (empty($data)) {
            return NULL;
        }
        $msg = new RabbitmqMessage($this, $data);
        return $msg;
    }


    /**
     * 返回当前队列长度
     *
     * @return int
     */
    public function size(): int {
        try {
            $declare_info = $this->__command(function () {
                if (!$this->__channel) {
                    throw new ConnectionException();
                }
                return $this->__channel->queue_declare($this->__queue_name, true);
            });
            if (!$declare_info) {
                return 0;
            }
            $message_count = $declare_info[1] ?? 0;
            return $message_count;
        } catch (\Exception $ex) {
            return 0;
        }
    }

    /**
     * 往队列中投递消息
     *
     * @param string $body
     *
     * @return bool
     */
    public function push(string $body, ?string $key = null): bool {
        try {
            return $this->__command(function () use ($body) {
                if (!$this->__channel) {
                    throw new ConnectionException();
                }
                $msg = new AMQPMessage($body);
                $this->__channel->basic_publish($msg, $this->__exchange_name, $this->__binding_key);
                return true;
            });
        } catch (\Exception $ex) {
            Utils::catchError($ex);
            return false;
        }
    }


    /**
     * 清除数据
     *
     * @return bool
     */
    public function clear(string $queue_name = ''): bool {
        try {
            return $this->__command(function () use ($queue_name) {
                if (!$this->__channel) {
                    throw new ConnectionException();
                }
                $this->__channel->queue_purge($queue_name ?: $this->__queue_name);
                return true;
            });
        } catch (\Exception $ex) {
            Utils::catchError($ex);
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function getQueueName(): string {
        return $this->__queue_name;
    }

    /**
     * @inheritDoc
     */
    public function tpo(): int {
        return 1;
    }

    /**
     * 声明队列
     */
    public function declareQueue() {
        // 主体队列
        $channel = $this->conn->getConnection()->channel();

        $queue_arguments = $this->__queue_options;

        // 声明死信交换器
        if (!empty($this->__dlx)) {
            $queue_arguments['x-dead-letter-exchange']    = [AMQPAbstractCollection::getSymbolForDataType(AMQPAbstractCollection::T_STRING_LONG), $this->__dlx];
            $queue_arguments['x-dead-letter-routing-key'] = [AMQPAbstractCollection::getSymbolForDataType(AMQPAbstractCollection::T_STRING_LONG), $this->__dlrk];
            $channel->exchange_declare($this->__dlx, 'topic', false, true, false);
        }

        // 声明交换器
        $channel->exchange_declare($this->__exchange_name, 'topic', false, true, false);
        // 申明队列
        $channel->queue_declare($this->__queue_name, false, true, false, false, false, $queue_arguments);
        // 队列绑定交换器
        $channel->queue_bind($this->__queue_name, $this->__exchange_name, $this->__binding_key);

        $this->__channel = $channel;
    }

    /**
     * 声明延迟独队列
     */
    public function declareDeleyQueue() {
        if (empty($this->__delay_options)) {
            return;
        }

        $exchange    = $this->__delay_options['exchange'];
        $routing_key = $this->__delay_options['routing_key'];
        $ttl         = $this->__delay_options['deley'];
        $queue_name  = $exchange . '.' . $routing_key;

        $channel = $this->conn->getConnection()->channel();

        // 声明交换器
        $channel->exchange_declare($exchange, 'topic', false, true, false);

        // 申明队列
        $channel->queue_declare($queue_name, false, true, false, false, false, [
            'x-dead-letter-exchange'    => [AMQPAbstractCollection::getSymbolForDataType(AMQPAbstractCollection::T_STRING_LONG), $this->__exchange_name],
            'x-dead-letter-routing-key' => [AMQPAbstractCollection::getSymbolForDataType(AMQPAbstractCollection::T_STRING_LONG), $this->__binding_key],
            'x-message-ttl'             => [AMQPAbstractCollection::getSymbolForDataType(AMQPAbstractCollection::T_INT_LONG), $ttl]
        ]);

        // 队列绑定交换器
        $channel->queue_bind($queue_name, $exchange, $routing_key);

        $channel->close();
    }

    /**
     * 设置消费者信息
     */
    public function setConsumer() {
        if ($this->is_consumer) {
            return;
        }

        $callback = function (AMQPMessage $msg) {
            array_unshift($this->__data_queue, $msg);
        };
        if ($this->__qos > 0) {
            $this->__channel->basic_qos(null, $this->__qos, null);
        }
        $this->__channel->basic_consume($this->__queue_name, '', false, false, false, false, $callback);
        $this->is_consumer = true;
    }

}
