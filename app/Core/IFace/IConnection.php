<?php

namespace Gino\Jobs\Core\IFace;

use Closure;

interface IConnection {

    /**
     * 设置参数
     *
     * @param array $options
     * @return mixed
     */
    public function setOptions(array $options);

    /**
     * 获取参数
     *
     * @return mixed
     */
    public function getOptions();

    /**
     * 连接
     */
    public function connect(): void;

    /**
     * 重新连接
     */
    public function reconnect(): void;

    /**
     * @return bool
     */
    public function isConnected(): bool;

    /**
     * 关闭连接
     */
    public function close(): void;

    /**
     * 获取连接实例
     *
     * @return mixed
     */
    public function getConnection();

    /**
     * 重试
     *
     * @param Closure $command
     * @param int $times 重试次数
     * @param int $interval 重试间隔（秒）
     * @return mixed
     */
    public function retry(Closure $command, int $times, int $interval = 0);

}