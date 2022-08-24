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
     * @param array $config
     * @param string $queue_name
     * @return IQueueDriver 失败返回false
     */
    public static function getConnection(array $config, string $queue_name, array $topic_config = []);

    /**
     * 获得队列名称
     */
    public function getQueueName(): string;

    /**
     * 队列是否连接
     *
     * @return bool
     */
    public function isConntected(): bool;

    /**
     * 获取当前队列的长度
     *
     * @return int
     */
    public function size(): int;

    /**
     * 获取指定队列的长度
     *
     * @param string $queue_name
     * @return int
     */
    public function getQueueSize(string $queue_name): int;

    /**
     * 从队列中弹出一条消息
     *
     * @return IQueueMessage 没有数据时返回NULL
     */
    public function pop();

    /**
     * 清除数据
     *
     * @return bool
     */
    public function clear(): bool;

    /**
     * 关闭
     */
    public function close();

    /**
     * 单次处理的消息数量上限
     *
     * @return int 0代表无限制
     */
    public function tpo(): int;

}
