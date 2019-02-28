<?php

namespace Gino\Jobs\Core\Queue;

use \Gino\Jobs\Core\IFace\IQueueMessage;
use \Gino\Jobs\Core\IFace\IQueueDriver;
use \Gino\Jobs\Core\Utils;
use \Gino\Jobs\Core\Logger;

/**
 * 
 * @author GinoHuang <binsuper@126.com>
 */
class RedisQueue implements IQueueDriver {

    private $__host;
    private $__port;
    private $__auth;
    private $__db;
    private $__handler;
    private $__queue_name;

    /**
     * 获取连接
     * @param array $config
     * @param string $topic_name
     * @return IQueueDriver 失败返回false
     */
    public static function getConnection(array $config, string $topic_name, array $topic_config = []) {
        return new self($config, $topic_name);
    }

    private function __construct(array $config, string $queue_name) {
        $this->__queue_name = $queue_name;
        $this->__host       = $config['host'] ?? '127.0.0.1';
        $this->__port       = $config['port'] ?? 6379;
        $this->__db         = $config['db'] ?? 0;
        $this->__auth       = $config['pass'] ?? '';
        $this->__handler    = new \Redis();
        $this->__connect();
    }

    public function __destruct() {
        try {
            $this->close();
        } catch (\Exception $ex) {
            
        }
    }

    /**
     * 连接Redis
     * @throws \RedisException
     */
    private function __connect() {
        try {
            if (empty($this->__host) || empty($this->__port)) {
                throw new \Exception('redis host or port is empty');
            }
            $this->__handler->pconnect($this->__host, $this->__port, 3);
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
        $this->__connect();
    }

    /**
     * 返回当前队列长度
     * @return int
     */
    public function size(): int {
        $len = $this->__command(function() {
            return $this->__handler->lLen($this->__queue_name);
        });
        return $len ?: 0;
    }

    /**
     * 执行命令
     * @param callable $callback
     * @return mixed
     */
    private function __command($callback) {
        try {
            return call_user_func($callback);
        } catch (\RedisException $ex) {
            if ($this->isConntected()) {
                throw $ex;
            }
            $try_times = 0; //尝试3次重连执行
            $last_ex   = null;
            do {
                //失败后重连
                if ($try_times == 1) {
                    sleep(1);
                } else if ($try_times == 2) {
                    sleep(5);
                }
                //尝试重连
                try {
                    if ($this->reconnect()) {
                        return call_user_func($callback);
                    }
                } catch (\RedisException $ex) {
                    $last_ex = $ex;
                }
                $try_times++;
            } while ($try_times <= 3);
            if ($last_ex) {
                throw $last_ex;
            }
        }
    }

    /**
     * 从队列中弹出一条消息
     * @return IQueueMessage 没有数据时返回NULL
     */
    public function pop() {
        try {
            $ret = $this->__command(function() {
                return $this->__handler->brPop($this->__queue_name, 1);
            });
            if (empty($ret)) {
                return NULL;
            }
            $data = $ret[1];
            $msg  = new RedisMessage($this, $data);
            return $msg;
        } catch (\Exception $ex) {
            Utils::catchError(Logger::getLogger(), $ex);
            return null;
        }
    }

    /**
     * 将消息重新加入到队列中
     * @param IQueueMessage $msg
     * @return bool
     */
    public function repush(IQueueMessage $msg): bool {
        $ret = $this->__command(function() use($msg) {
            return $this->__handler->lPush($this->__queue_name, $msg->getBody());
        });
        if ($ret) {
            return true;
        }
        return false;
    }

    /**
     * 关闭
     */
    public function close() {
        $this->__handler->close();
    }

}
