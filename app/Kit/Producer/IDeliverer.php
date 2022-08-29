<?php

namespace Gino\Jobs\Kit\Producer;

/**
 * 投递者接口
 *
 * @author GinoHuang <binsuper@126.com>
 */
interface IDeliverer {

    /**
     * 消息通道
     *
     * @param string|null $channel
     * @return $this
     */
    public function channel(string $channel = '');

    /**
     * 消息队列
     *
     * @param string $queue
     * @return $this
     */
    public function queue(string $queue);

    /**
     * 延迟消息
     *
     * @param int $second 延迟时间，单位-秒
     * @param string $delay_queue 延迟队列名称
     * @return $this
     */
    public function delay(int $second, string $delay_queue = '');

    /**
     * 设置消息体
     *
     * @param string $msg
     * @return $this
     */
    public function message(string $msg);

    /**
     * 投递
     *
     * @return bool
     */
    public function send(): bool;

    /**
     * 关闭投递
     *
     * @return bool
     */
    public function close(): bool;

}
