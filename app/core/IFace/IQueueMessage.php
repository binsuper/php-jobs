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
     */
    public function ack(): bool;

    /**
     * 拒绝消息
     * 
     * @param bool $back true表示将消息重新入队列，false则丢弃该消息
     */
    public function reject(bool $back): bool;
}
