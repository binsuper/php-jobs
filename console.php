<?php

use Gino\Jobs\Core\Logger;
use Gino\Jobs\Core\Utils;

define('GINO_JOBS_ROOT_PATH', __DIR__);
define('GINO_JOBS_CONFIG_PATH', GINO_JOBS_ROOT_PATH . '/conf/config.php');

require_once GINO_JOBS_ROOT_PATH . '/vendor/autoload.php';

$config  = include(GINO_JOBS_CONFIG_PATH);
$console = new Gino\Jobs\Console($config);

$console->process()->onStart(function () {
    echo 'start' . PHP_EOL;
});

$console->process()->onStop(function () {
    echo 'stop' . PHP_EOL;
});

$console->process()->onWorkerStart(function () {
    echo 'worker start' . PHP_EOL;
});

$console->process()->onWorkerStop(function () {
    echo 'worker stop' . PHP_EOL;
});

$console->run();

// swoole4.6 以上版本，接入laravel框架时需要加入下面的异常处理，才不会导致主进程挂掉, Swoole\Event::rshutdown(): Event::wait() in shutdown function is deprecated
/*
set_error_handler(function ($level, $message, $file = '', $line = 0, $context = []) {
    Utils::catchError(Logger::getLogger(), new \ErrorException($message, 0, $level, $file, $line));
});
*/