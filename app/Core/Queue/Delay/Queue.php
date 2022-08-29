<?php

namespace Gino\Jobs\Core\Queue\Delay;

use Gino\Jobs\Core\IFace\IQueueDelay;
use Gino\Jobs\Core\Logger;
use Gino\Jobs\Core\Queue\RedisQueue;
use Gino\Jobs\Core\Utils;

class Queue extends RedisQueue implements IQueueDelay {

    /**
     * 获取延迟队列消息的数量
     *
     * @param string $queue
     * @return int
     */
    public function getDelayQueueSize(): int {
        try {
            $count = $this->_command(function () {
                $slots = 60;
                $count = 0;
                while (--$slots >= 0) {
                    $count += $this->_handler->lLen($this->_queue_name . '#' . $slots);
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
        $slot_key = $this->_queue_name . '#slot';
        try {
            if (!isset($this->delay_slot)) {
                $this->delay_slot = $this->_command(function () use ($slot_key) {
                    return $this->_handler->get($slot_key) ?: 0;
                });
                $this->delay_slot = intval($this->delay_slot) % 60;
            }
        } catch (\Throwable $ex) {
            Utils::catchError(Logger::getLogger(), $ex);
            return;
        }
        //更新slot
        try {
            $this->_command(function () use ($slot_key) {
                return $this->_handler->incr($slot_key);
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
                $delay_queue = $this->_queue_name . '#' . $slot;
                $count       = $this->_command(function () use ($delay_queue) {
                    return $this->_handler->lLen($delay_queue);
                });
            } catch (\Throwable $ex) {
                Utils::catchError(Logger::getLogger(), $ex);
                return;
            }
            while ($count && $count-- > 0) {
                try {

                    $bodys = $this->_handler->lRange($delay_queue, -2000, -1);
                    if (empty($bodys)) {
                        break;
                    }
                    $this->_handler->lTrim($delay_queue, 0, 0 - count($bodys) - 1);
                    $count -= count($bodys) + 1;

                    $backlist = [];
                    foreach ($bodys as $body) {
                        $msg = new Message($body);
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
                        $this->_handler->lPush($delay_queue, ...$backlist);
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
        $slot_key = $this->_queue_name . '#slot';
        try {
            $slot = $this->_command(function () use ($slot_key) {
                return $this->_handler->get($slot_key) ?: 0;
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
        $delay_queue = $this->_queue_name . '#' . $target_slot;
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
            $ret = $this->_command(function () use ($target_queue_name, $msg) {
                return $this->_handler->lPush($target_queue_name, $msg);
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

}