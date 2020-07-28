<?php

namespace Gino\Jobs\Core\IFace;

/**
 * 队列数据接口
 *
 * @author GinoHuang <binsuper@126.com>
 */
interface IQueueMessage {

    public function __toString();

    /**
     * 消息实体
     *
     * @return mixed
     */
    public function getBody();

    /**
     * 正确应答
     *
     * @return bool
     */
    public function ack(): bool;

    /**
     * 拒绝消息
     *
     * @param bool $requeue true表示将消息重新入队列，false则丢弃该消息
     * @return bool
     */
    public function reject(bool $requeue): bool;

    /**
     * 是否已正确应答消息
     *
     * @return bool
     */
    public function isAck(): bool;

    /**
     * 是否已拒绝消息
     *
     * @return bool
     */
    public function isReject(): bool;

    /**
     * 获取队列名称
     *
     * @return string
     */
    public function getQueueName(): string;

    /**
     * 获取队列驱动
     *
     * @return IQueueDriver
     */
    public function getQueueDriver(): IQueueDriver;

}
