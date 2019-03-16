<?php

namespace Gino\Jobs\Core\IFace;

/**
 * 延迟队列
 * @author GinoHuang <binsuper@126.com>
 */
interface IQueueDelay {

    /**
     * 遍历延迟队列的消息
     * 如果时间到点，则将消息以参数的形式传入到回调函数中
     * @param string $queue
     * @param callable $callback callback($delayMessage)
     */
    public function scanDelayQueue(string $queue, $callback);

    /**
     * 获取延时队列中的消息数目
     */
    public function getDelayQueueSize(string $queue): int;
}
