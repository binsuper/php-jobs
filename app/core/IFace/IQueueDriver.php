<?php

namespace Gino\Jobs\Core\IFace;

/**
 * 队列驱动
 * @author GinoHuang <binsuper@126.com>
 */
interface IQueueDriver {

    /**
     * 获取当前队列的长度
     * @return int
     */
    public function size(): int;

    /**
     * 从队列中弹出一条消息
     * @return IQueueMessage 没有数据时返回NULL
     */
    public function pop();

    /**
     * 队列是否连接
     * @return bool
     */
    public function isConntected(): bool;

    /**
     * 重连
     */
    public function reconnect();
}
