<?php

namespace Gino\Jobs\Core\IFace;

/**
 * 队列数据接口
 * @author GinoHuang <binsuper@126.com>
 */
interface IQueueMessage {

    public function __toString();

    /**
     * 消息实体
     */
    public function getBody();

    /**
     * 正确应答
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
     * 获取队列名称
     */
    public function getQueueName(): string;
}
