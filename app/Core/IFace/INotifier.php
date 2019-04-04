<?php

namespace Gino\Jobs\Core\IFace;

/**
 * 消息通知接口
 * @author GinoHuang <binsuper@126.com>
 */
interface INotifier {

    public function __construct(array $params);

    /**
     * 消息通知
     * 
     * @param string $msg
     */
    public function notify(string $msg);
}
