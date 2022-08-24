<?php

namespace Gino\Jobs\Core\IFace;

use Gino\Jobs\Core\Topic;

/**
 *
 * @author GinoHuang <binsuper@126.com>
 */
interface IHandler {

    /**
     * 获取 topic
     *
     * @return Topic
     */
    public function getTopic(): Topic;

    /**
     * 获取参数
     *
     * @return array
     */
    public function getParams(): array;

    /**
     * 执行
     *
     * @return mixed
     */
    public function run();

}
