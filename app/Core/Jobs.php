<?php

namespace Gino\Jobs\Core;

use Gino\Jobs\Core\IFace\IQueueDriver;
use Gino\Jobs\Core\IFace\IConsumer;
use Gino\Jobs\Core\Queue\QueueMsgGroup;

/**
 *
 * @author GinoHuang <binsuper@126.com>
 */
class Jobs {

    private $__name;

    /**
     * 最后一次执行的时间
     *
     * @var float
     */
    private $__last_busy_time = 0;

    /**
     * 已完成的任务数
     *
     * @var int
     */
    private $__done_count = 0;

    /**
     * 失败的任务数
     *
     * @var int
     */
    private $__failed_count = 0;

    /**
     * 正确应答的任务数
     *
     * @var int
     */
    private $__ack_count = 0;

    /**
     * 拒绝的任务数
     *
     * @var int
     */
    private $__reject_count = 0;

    /**
     *
     * @var IConsumer
     */
    private $__job;

    /**
     *
     * @var IQueueDriver
     */
    private $__queue;

    /**
     * 任务总的执行时长
     *
     * @var float
     */
    private $__cost_time;

    /**
     * @var float 最大执行时长
     */
    private $__max_time;

    /**
     * @var float 最小执行时长
     */
    private $__min_time;

    /**
     * @var int
     */
    private $__tpo = [];

    public function __construct(IQueueDriver $queue, IConsumer $job, int $tpo = 1) {
        $this->__queue          = $queue;
        $this->__job            = $job;
        $this->__last_busy_time = microtime(true);

        if ($this->__queue->tpo() === 0 || $this->__queue->tpo() >= $tpo) {
            $this->__tpo = $tpo;
        } else {
            $this->__tpo = $this->__queue->tpo();
        }
    }

    /**
     * 得到任务空闲的时长
     *
     * @param bool $format
     * @return string|float 空闲时长(整数为秒)
     */
    public function idleTime(bool $format = false) {
        $time = bcsub(microtime(true), $this->__last_busy_time, 10);
        return $format ? $this->formatTime($time) : $time;
    }

    /**
     * 处理成功的任务数
     */
    public function doneCount() {
        return $this->__done_count;
    }

    /**
     * 处理失败的任务数
     */
    public function failedCount() {
        return $this->__failed_count;
    }

    /**
     * 正确应答的消息数
     */
    public function ackCount() {
        return $this->__ack_count;
    }

    /**
     * 拒绝的消息数
     */
    public function rejectCount() {
        return $this->__reject_count;
    }

    /**
     * 任务的平均用时
     *
     * @param bool $format
     * @return string|float
     */
    public function avgTime(bool $format = false) {
        if ($this->__done_count == 0 || $this->__cost_time == 0) {
            return 0 . 'ms';
        }
        $avg = bcdiv($this->__cost_time, $this->__done_count, 6);
        return $format ? $this->formatTime($avg) : $avg;
    }

    /**
     * 最大执行时间
     *
     * @return string
     * @return string|float
     */
    public function maxTime(bool $format = false) {
        return $format ? $this->formatTime($this->__max_time) : $this->__max_time;
    }

    /**
     * 最小执行时间
     *
     * @return string
     * @return string|float
     */
    public function minTime(bool $format = false) {
        return $format ? $this->formatTime($this->__min_time) : $this->__min_time;
    }

    /**
     * @param $time
     * @return string
     */
    public function formatTime($time) {
        if (bccomp($this->__max_time, 10) > 0) {
            return round($this->__max_time, 2) . 's';
        }
        return round(($this->__max_time * 1000), 1) . 'ms';
    }

    /**
     * 执行
     */
    public function run(): int {
        $is_group = false;
        $count    = 0;
        if ($this->__tpo == 1) {
            $msg = $this->__queue->pop();
            if (null === $msg) {
                return 0;
            }
            $count = 1;
        } else {
            $is_group = true;
            $msg      = new QueueMsgGroup();
            for ($i = 0; $i < $this->__tpo; $i++) {
                $o_msg = $this->__queue->pop();
                if (null !== $o_msg) {
                    $msg->append($o_msg);
                } else {
                    if (count($msg) == 0) {
                        unset($msg);
                        return 0;
                    }
                    break;
                }
            }
            $count = count($msg);
            if ($count == 0) {
                unset($msg);
                return 0;
            }
        }

        try {
            $before_time = microtime(true);
            //消费消息
            if ($this->__job->consume($msg)) {
                $this->__done_count++;
                $this->__last_busy_time = microtime(true);
                $duration               = bcsub($this->__last_busy_time, $before_time, 10);
                $this->__cost_time      = bcadd($this->__cost_time, $duration, 10);
                $this->__max_time       = max($this->__max_time, $duration);
                $this->__min_time       = $this->__min_time > 0 ? min($this->__min_time, $duration) : $duration;
            } else {
                $this->__failed_count++;
            }

            if (!$is_group) {
                if ($msg->isAck()) {
                    $this->__ack_count++;
                } else {
                    $this->__reject_count++;
                }
            } else {
                $this->__ack_count    += $msg->acks();
                $this->__reject_count += $msg->rejects();
            }
        } catch (\Throwable $ex) {
            //消费时发生错误
            Utils::catchError($ex);
            $this->__failed_count++;
        }
        return $count;
    }

}
