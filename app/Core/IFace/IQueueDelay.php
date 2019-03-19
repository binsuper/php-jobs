<?php

namespace Gino\Jobs\Core\IFace;

/**
 * 延迟队列
 * @author GinoHuang <binsuper@126.com>
 */
interface IQueueDelay {

    /**
     * 遍历延迟队列的消息
     * 如果可以处理，则将消息体传入到回调函数中
     * @param callable $callback callback($delayMessage)
     */
    public function scanDelayQueue($callback);

    /**
     * 获取延时队列中的消息数目
     */
    public function getDelayQueueSize(): int;

    /**
     * 将消息推送到延时队列
     * @param string $target_queue_name 目标队列
     * @param string $msg 消息体
     * @param int $delay 延迟时间
     * @return bool
     */
    public function pushDelay(string $target_queue_name, string $msg, int $delay): bool;

    /**
     * 将延时消息推送至目标队列
     * @param string $target_queue_name
     * @return bool
     */
    public function pushTarget(string $target_queue_name, string $msg): bool;
}
