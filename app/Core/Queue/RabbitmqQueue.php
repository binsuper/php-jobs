<?php

namespace Gino\Jobs\Core\Queue;

use \Gino\Jobs\Core\IFace\IQueueMessage;
use \Gino\Jobs\Core\IFace\{
    IQueueDriver,
    IQueueProducer
};
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use \Gino\Jobs\Core\Utils;
use \Gino\Jobs\Core\Logger;
use \Gino\Jobs\Core\Exception\ConnectionException;
use PhpAmqpLib\Exception\{
    AMQPTimeoutException,
    AMQPProtocolChannelException
};
use PhpAmqpLib\Wire\AMQPAbstractCollection;
use think\Exception;

/**
 *
 * @author GinoHuang <binsuper@126.com>
 */
class RabbitmqQueue implements IQueueDriver, IQueueProducer {

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
     *
     * @var string 交换器名称
     */
    private $__exchange_name;

    /**
     *
     * @var string 队列名称
     */
    private $__queue_name;

    /**
     *
     * @var string 路由名
     */
    private $__binding_key;

    /**
     * 数据队列
     *
     * @var array
     */
    private $__data_queue = [];

    /**
     * 已经尝试重连的次数
     *
     * @var int
     */
    private $__retry_times;

    /**
     * 下一次尝试重连的时间戳
     *
     * @var int
     */
    private $__next_reconnect_time;

    /**
     * 是否设置消费者
     *
     * @var bool
     */
    private $__is_consumer = false;

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
     * 获取连接
     *
     * @param array $config
     * @param string $queue_name
     * @param array $topic_config
     *
     * @return IQueueDriver 失败返回false
     * @throws \Exception
     */
    public static function getConnection(array $config, string $queue_name, array $topic_config = []) {
        $exchange_name = $topic_config['exchange'] ?? '';
        if (empty($exchange_name)) {
            throw new \Exception('exchange must be set');
        }

        //isset($more_config['dlx']) && ($config['dlx'] = $more_config['dlx']);
        //isset($more_config['dlrk']) && ($config['dlrk'] = $more_config['dlrk']);
        $config = array_merge($config, $topic_config);

        return new self($config, $queue_name, $exchange_name);
    }

    private function __construct(array $config, string $routing_key, string $exchange_name) {
        $this->__binding_key   = $config['routing_key'] ?? $routing_key;
        $this->__exchange_name = $exchange_name;
        $this->__queue_name    = $exchange_name . '.' . $routing_key;
        $this->__host          = $config['host'] ?? '127.0.0.1';
        $this->__port          = $config['port'] ?? 5672;
        $this->__user          = $config['user'] ?? '';
        $this->__pass          = $config['pass'] ?? '';
        $this->__vhost         = $config['vhost'] ?? '/';
        $this->__qos           = $config['qos'] ?? 0;
        $this->__is_consumer   = $config['is_consumer'] ?? false;
        $this->__dlx           = $config['dlx'] ?? '';
        $this->__dlrk          = $config['dlrk'] ?? '';
        $this->__ssl           = $config['ssl'] ?? [];
        $this->__options       = $config['options'] ?? false;

        if ((!empty($this->__dlx) ^ !empty($this->__dlrk)) != 0) {
            if (empty($this->__dlx) || empty($this->__dlrk)) {
                throw new \InvalidArgumentException('param "dlx" and "dlrk" must be setting together');
            }
        }

        $this->__connect();
    }

    public function __destruct() {
        try {
            $this->close();
        } catch (\Exception $ex) {

        }
    }

    /**
     * 进行连接
     *
     * @param int $max_retry_tiems 尝试重连次数
     *
     * @return bool 是否连接成功
     * @throws \Exception
     */
    private function __connect(int $max_retry_tiems = 0) {
        //先关闭之前的连接
        try {
            if ($this->__channel instanceof AMQPChannel) {
                $this->__channel->close();
            }
            if ($this->__conn instanceof AMQPStreamConnection) {
                $this->__conn->close();
            }
        } catch (\Exception $ex) {

        }

        //开始新的连接
        try {
            $this->__retry_times++;
            $max_retry_tiems      = $max_retry_tiems ?: 1;
            $conntection_attempts = 0;
            $conntection_found    = true;
            while ($conntection_found && $conntection_attempts < $max_retry_tiems) {
                $conntection_attempts++;
                try {
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
                    }else {
                        $this->__conn = new AMQPSSLConnection($this->__host, $this->__port, $this->__user, $this->__pass, $this->__vhost, $this->__ssl, $this->__options);
                    }

                    try {
                        $this->__channel = $this->__conn->channel();

                        $queue_arguments = [];

                        // 声明死信交换器
                        if (!empty($this->__dlx)) {
                            $queue_arguments['x-dead-letter-exchange']    = [AMQPAbstractCollection::getSymbolForDataType(AMQPAbstractCollection::T_STRING_LONG), $this->__dlx];
                            $queue_arguments['x-dead-letter-routing-key'] = [AMQPAbstractCollection::getSymbolForDataType(AMQPAbstractCollection::T_STRING_LONG), $this->__dlrk];

                            $this->__channel->exchange_declare($this->__dlx, 'topic', false, true, false);
                        }

                        // 声明交换器
                        $this->__channel->exchange_declare($this->__exchange_name, 'topic', false, true, false);

                        // 申明队列
                        if (empty($this->__queue_name)) {
                            list($this->__queue_name, ,) = $this->__channel->queue_declare('', false, true, true, false, false, $queue_arguments);
                        } else {
                            $this->__channel->queue_declare($this->__queue_name, false, true, false, false, false, $queue_arguments);
                        }
                        // 队列绑定交换器
                        $this->__channel->queue_bind($this->__queue_name, $this->__exchange_name, $this->__binding_key);

                        //设置消费者
                        if ($this->__is_consumer) {
                            $callback = function (AMQPMessage $msg) {
                                array_unshift($this->__data_queue, $msg);
                            };
                            if ($this->__qos > 0) {
                                $this->__channel->basic_qos(null, $this->__qos, null);
                            }
                            $this->__channel->basic_consume($this->__queue_name, '', false, false, false, false, $callback);
                        }
                        $conntection_found   = false;
                        $this->__retry_times = 0;
                    } catch (\Exception $ex) {
                        if ($conntection_attempts >= $max_retry_tiems) {
                            $this->__conn = null;
                            sleep(1); //控制尝试间隔
                        } else {
                            throw ($ex);
                        }
                    }
                } catch (\Exception $ex) {
                    if ($conntection_attempts >= $max_retry_tiems) {
                        $this->__conn = null;
                        sleep(1); //控制尝试间隔
                    } else {
                        throw ($ex);
                    }
                }
            }
        } catch (\Exception $ex) {
            $this->__next_reconnect_time = time() + pow(2, $this->__retry_times > 8 ? 8 : $this->__retry_times);
            throw $ex;
        }
    }

    /**
     * 关闭连接
     *
     * @return mixed|null
     */
    public function close() {
        if ($this->__channel instanceof AMQPChannel) {
            $this->__channel->close();
        }
        if ($this->__conn instanceof AMQPStreamConnection) {
            $this->__conn->close();
        }
    }

    /**
     * 执行命令
     *
     * @param callable $callback
     *
     * @return mixed
     */
    private function __command($callback) {
        $retry               = false;
        $connection_required = false;
        do {
            $retry = false;
            try {
                if ($this->__next_reconnect_time <= time()) {
                    if ($connection_required || $this->__conn === null) {
                        $this->__connect(2);
                    }
                }
                $connection_required = false;
                try {
                    return call_user_func($callback);
                } catch (ConnectionException $ex) {
                    $connection_required = true;
                    if ($this->__next_reconnect_time <= time()) {
                        $retry = true;
                    }
                }
            } catch (\Exception $ex) {
                throw $ex;
            }
        } while ($retry);
    }

    /**
     * @return bool
     */
    public function isConntected(): bool {
        return $this->__conn->isConnected() ? true : false;
    }

    /**
     * 从队列中弹出一条消息
     *
     * @return IQueueMessage 没有数据时返回NULL
     */
    public function pop() {
        try {
            $data = $this->__command(function () {
                if (!$this->__channel) {
                    throw new ConnectionException();
                }
                try {
                    $this->__channel->wait(null, true, 3);
                    return array_pop($this->__data_queue);
                } catch (AMQPTimeoutException $ex) { //超时，不做处理
                } catch (AMQPProtocolChannelException $ex) {

                } catch (\Exception $ex) {
                    Utils::catchError(Logger::getLogger(), $ex);
                    throw new ConnectionException($ex->getMessage());
                } catch (\Throwable $ex) {
                    Utils::catchError(Logger::getLogger(), $ex);
                    throw new ConnectionException($ex->getMessage());
                }
            });
        } catch (\Exception $ex) {
            Utils::catchError(Logger::getLogger(), $ex);
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
     * 获取指定队列的长度
     *
     * @param string $queue_name
     *
     * @return int
     */
    public function getQueueSize(string $queue_name): int {
        try {
            $queue_name   = $this->__exchange_name . '.' . $queue_name;
            $declare_info = $this->__command(function () use ($queue_name) {
                if (!$this->__channel) {
                    throw new ConnectionException();
                }
                return $this->__channel->queue_declare($queue_name, true);
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
    public function push(string $body): bool {
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
            Utils::catchError(Logger::getLogger(), $ex);
            return false;
        }
    }

    public function getQueueName(): string {
        return $this->__queue_name;
    }

    /**
     * @inheritDoc
     */
    public function tpo(): int {
        return 1;
    }

}
