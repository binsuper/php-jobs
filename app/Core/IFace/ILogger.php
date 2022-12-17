<?php

namespace Gino\Jobs\Core\IFace;

/**
 *
 * @author GinoHuang <binsuper@126.com>
 */
interface ILogger {

    public function __construct(string $log_dir, string $log_file = '');

    /**
     * 记录日志
     *
     * @param string $msg 信息
     * @param string $level 级别
     * @param string $category 日志分类
     * @param bool $flush 立即刷新缓冲区
     */
    public function log(string $msg, string $level = 'info', string $category = '', bool $flush = false);

    /**
     * 刷新日志缓冲区
     *
     * @deprecated
     */
    public function flush();

}
