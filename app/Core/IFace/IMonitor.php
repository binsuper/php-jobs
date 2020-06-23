<?php


namespace Gino\Jobs\Core\IFace;

use Gino\Jobs\Core\Topic;

/**
 * 监视器接口
 *
 * @author GinoHuang <binsuper@126.com>
 */
interface IMonitor {

    /**
     * 开始监控
     *
     * @return mixed
     */
    public function start();

    /**
     * 监控
     *
     * @param int $pid
     * @param Topic $topic
     * @param array $info
     * @return mixed
     */
    public function processing(int $pid, Topic $topic, array $info);

    /**
     * 完成
     *
     * @return mixed
     */
    public function finish();

}