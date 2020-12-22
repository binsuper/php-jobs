<?php

namespace Gino\Jobs\Core\Queue;

use \Gino\Jobs\Core\IFace\{
    IQueueMessage,
    IQueueDriver,
    IQueueProducer,
    IQueueDelay
};
use \Gino\Jobs\Core\Utils;
use \Gino\Jobs\Core\Logger;

/**
 *
 * @author GinoHuang <binsuper@126.com>
 */
class RedisQueue implements IQueueDriver, IQueueProducer, IQueueDelay {

    private $__host;
    private $__port;
    private $__auth;
    private $__db;

    /**
     * @var \Redis
     */
    private $__handler;
    private $__queue_name;

    /**
     * 获取连接
     *
     * @param array $config
     * @param string $queue_name
     * @return IQueueDriver 失败返回false
     */
    public static function getConnection(array $config, string $queue_name, array $topic_config = []) {
        return new self($config, $queue_name);
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
     *
     * @throws \RedisException
     */
    private function __connect() {
        try {
            if (empty($this->__host) || empty($this->__port)) {
                throw new \Exception('redis host or port is empty');
            }
            @$this->__handler->connect($this->__host, $this->__port, 3);
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
     * 返回当前队列长度
     *
     * @return int
     */
    public function size(): int {
        try {
            $len = $this->__command(function () {
                return $this->__handler->lLen($this->__queue_name);
            });
            if (!$len) {
                return 0;
            }
            return $len ?: 0;
        } catch (\Exception $ex) {
            return 0;
        }
    }

    /**
     * 获取指定队列的长度
     *
     * @param string $queue_name
     * @return int
     */
    public function getQueueSize(string $queue_name): int {
        try {
            $len = $this->__command(function () use ($queue_name) {
                return $this->__handler->lLen($queue_name);
            });
            if (!$len) {
                return 0;
            }
            return $len ?: 0;
        } catch (\Exception $ex) {
            return 0;
        }
    }

    /**
     * 执行命令
     *
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
                    if ($this->__connect()) {
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
     *
     * @return IQueueMessage 没有数据时返回NULL
     */
    public function pop() {
        try {
            $ret = $this->__command(function () {
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
     * 往队列中投递消息
     *
     * @param string $body
     * @return bool
     */
    public function push(string $body): bool {
        try {
            $ret = $this->__command(function () use ($body) {
                return $this->__handler->lPush($this->__queue_name, $body);
            });
            if ($ret) {
                return true;
            }
            return false;
        } catch (\Exception $ex) {
            return false;
        }
    }

    /**
     * 将消息重新加入到队列中
     *
     * @param IQueueMessage $msg
     * @return bool
     */
    public function repush(IQueueMessage $msg): bool {
        return $this->push($msg->getBody());
    }

    /**
     * 关闭
     */
    public function close() {
        if ($this->__handler) {
            $this->__handler->close();
            $this->__handler = null;
        }
    }

    public function getQueueName(): string {
        return $this->__queue_name;
    }

    /**
     * 获取延迟队列消息的数量
     *
     * @param string $queue
     * @return int
     */
    public function getDelayQueueSize(): int {
        try {
            $count = $this->__command(function () {
                $slots = 60;
                $count = 0;
                while (--$slots >= 0) {
                    $count += $this->__handler->lLen($this->__queue_name . '#' . $slots);
                }
                return $count;
            });
            if (!$count) {
                return 0;
            }
            return $count ?: 0;
        } catch (\Exception $ex) {
            return 0;
        }
    }

    /**
     * @inheritDoc
     */
    public function scanDelayQueue($callback, $break_callback) {
        $slot_key = $this->__queue_name . '#slot';
        try {
            if (!isset($this->delay_slot)) {
                $this->delay_slot = $this->__command(function () use ($slot_key) {
                    return $this->__handler->get($slot_key) ?: 0;
                });
                $this->delay_slot = intval($this->delay_slot) % 60;
            }
        } catch (\Throwable $ex) {
            Utils::catchError(Logger::getLogger(), $ex);
            return;
        }
        //更新slot
        try {
            $this->__command(function () use ($slot_key) {
                return $this->__handler->incr($slot_key);
            });
        } catch (\Throwable $ex) {
            Utils::catchError(Logger::getLogger(), $ex);
            return;
        }
        $slot             = $this->delay_slot;
        $this->delay_slot = $this->delay_slot + 1 >= 60 ? 0 : $this->delay_slot + 1;

        //协程
        \Swoole\Coroutine::create(function () use ($slot, $callback, $break_callback) {
            try {
                $delay_queue = $this->__queue_name . '#' . $slot;
                $count       = $this->__command(function () use ($delay_queue) {
                    return $this->__handler->lLen($delay_queue);
                });
            } catch (\Throwable $ex) {
                Utils::catchError(Logger::getLogger(), $ex);
                return;
            }
            while ($count && $count-- > 0) {
                try {

                    $bodys = $this->__handler->lRange($delay_queue, -2000, -1);
                    if (empty($bodys)) {
                        break;
                    }
                    $this->__handler->lTrim($delay_queue, 0, 0 - count($bodys) - 1);
                    $count -= count($bodys) + 1;

                    $backlist = [];
                    foreach ($bodys as $body) {
                        $msg = new Delay\Message($body);
                        if (!$msg->onTime()) { //没到点，重回队列
                            $msg->roll();
                            $backlist[] = (string)$msg;
                            continue;
                        }

                        //到点了，往目标队列投递消息
                        if (!call_user_func($callback, $msg)) {
                            //失败，回到延迟队列
                            $msg->roll();
                            $backlist[] = (string)$msg;
                        }
                    }

                    if (!empty($backlist)) {
                        $this->__handler->lPush($delay_queue, ...$backlist);
                    }

                    // 返回false，则中断执行
                    if (!call_user_func($break_callback, $count)) {
                        break;
                    }

                } catch (\Throwable $ex) {
                    Utils::catchError(Logger::getLogger(), $ex);
                }
            }
        });
    }

    /**
     * 将消息推送到延时队列
     *
     * @param string $target_queue_name 目标队列
     * @param string $msg 消息体
     * @param int $delay 延迟时间
     * @return bool
     */
    public function pushDelay(string $target_queue_name, string $msg, int $delay): bool {
        $slot_key = $this->__queue_name . '#slot';
        try {
            $slot = $this->__command(function () use ($slot_key) {
                return $this->__handler->get($slot_key) ?: 0;
            });
        } catch (\Throwable $ex) {
            Utils::catchError(Logger::getLogger(), $ex);
            return false;
        }
        $delay++; //往后加一秒，保证任务不会提前触发
        $target_slot  = ($delay + $slot) % 60;
        $target_delay = floor($delay / 60);
        $data         = json_encode([
            'target'  => $target_queue_name,
            'payload' => $msg,
            'delay'   => $target_delay
        ], JSON_UNESCAPED_UNICODE);
        if (!$data) { //数据错误
            return false;
        }
        $delay_queue = $this->__queue_name . '#' . $target_slot;
        return $this->pushTarget($delay_queue, $data) ? true : false;
    }

    /**
     * 将延时消息推送至目标队列
     *
     * @param string $target_queue_name
     * @return bool
     */
    public function pushTarget(string $target_queue_name, string $msg): bool {
        try {
            $ret = $this->__command(function () use ($target_queue_name, $msg) {
                return $this->__handler->lPush($target_queue_name, $msg);
            });
            if ($ret) {
                return true;
            }
            return false;
        } catch (\Exception $ex) {
            Utils::catchError(Logger::getLogger(), $ex);
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function tpo(): int {
        return 0;
    }

}
