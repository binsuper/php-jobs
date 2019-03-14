<?php

namespace Gino\Jobs\Core\Queue;

use \Gino\Jobs\Core\IFace\IQueueMessage;
use \Gino\Jobs\Core\IFace\IQueueDriver;
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

    /**
     * prefetch count
     * @var int
     */
    private $__qos;

    /**
     *
     * @var AMQPStreamConnection 
     */
    private $__conn;

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
     * 数据队列
     * @var array 
     */
    private $__data_queue = [];

    /**
     * 已经尝试重连的次数
     * @var int 
     */
    private $__retry_times;

    /**
     * 下一次尝试重连的时间戳
     * @var int
     */
    private $__next_reconnect_time;

    /**
     * 是否设置消费者
     * @var bool
     */
    private $__is_consumer = false;

    /**
     * 获取连接
     * @param array $config
     * @param string $topic_name
     * @param string $more_config
     * @return IQueueDriver 失败返回false
     * @throws Exception
     */
    public static function getConnection(array $config, string $topic_name, array $more_config = []) {
        $exchange_name = $more_config['exchange'] ?? '';
        if (empty($exchange_name)) {
            throw new Exception('exchange must be set');
        }
        return new self($config, $topic_name, $exchange_name);
    }

    private function __construct(array $config, string $binding_key, string $exchange_name) {
        $this->__binding_key   = $binding_key;
        $this->__exchange_name = $exchange_name;
        $this->__queue_name    = $exchange_name . '.' . $binding_key;
        $this->__host          = $config['host'] ?? '127.0.0.1';
        $this->__port          = $config['port'] ?? 5672;
        $this->__user          = $config['user'] ?? '';
        $this->__pass          = $config['pass'] ?? '';
        $this->__vhost         = $config['vhost'] ?? '/';
        $this->__qos           = $config['qos'] ?? 0;
        $this->__is_consumer   = $config['is_consumer'] ?? false;
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
     * @param int $max_retry_tiems 尝试重连次数
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
                    $this->__conn = new AMQPStreamConnection($this->__host, $this->__port, $this->__user, $this->__pass, $this->__vhost);

                    try {
                        $this->__channel = $this->__conn->channel();
                        $this->__channel->exchange_declare($this->__exchange_name, 'topic', false, true, false);
                        if (empty($this->__queue_name)) {
                            list($this->__queue_name,, ) = $this->__channel->queue_declare('', false, true, true, false);
                        } else {
                            $this->__channel->queue_declare($this->__queue_name, false, true, false, false);
                        }
                        $this->__channel->queue_bind($this->__queue_name, $this->__exchange_name, $this->__binding_key);

                        //设置消费者
                        if ($this->__is_consumer) {
                            $callback = function(AMQPMessage $msg) {
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
     * @param callable $callback
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
     * @return IQueueMessage 没有数据时返回NULL
     */
    public function pop() {
        try {
            $data = $this->__command(function() {
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
     * @return int
     */
    public function size(): int {
        try {
            $declare_info = $this->__command(function() {
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

}
