<?php

namespace Gino\Jobs\Core\IFace;

/**
 * 队列驱动
 *
 * @author GinoHuang <binsuper@126.com>
 */
interface IQueueDriver {

    /**
     * 获取连接
     *
     * @param string $queue_name
     * @param IConnection $conn
     * @param array $options
     * @return IQueueDriver 失败返回false
     */
    public static function make(string $queue_name, IConnection $conn, array $options = []): IQueueDriver;

    /**
     * 获取队列名称
     *
     * @return string
     */
    public function getQueueName(): string;

    /**
     * 获取当前队列的长度
     *
     * @return int
     */
    public function size(): int;

    /**
     * 从队列中弹出一条消息
     *
     * @return IQueueMessage 没有数据时返回NULL
     */
    public function pop();

    /**
     * 清除数据
     *
     * @param string $queue_name
     * @return bool
     */
    public function clear(string $queue_name = ''): bool;

    /**
     * 单次处理的消息数量上限
     *
     * @return int 0代表无限制
     */
    public function tpo(): int;

}
