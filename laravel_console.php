<?php

use Gino\Jobs\Adapter\Laravel\Kernel;
use Gino\Jobs\Core\Logger;
use Gino\Jobs\Core\Utils;

define('LARAVEL_START', microtime(true));
define('GINO_JOBS_ROOT_PATH', __DIR__);
define('GINO_JOBS_CONFIG_PATH', GINO_JOBS_ROOT_PATH . '/conf/config.php');

require_once GINO_JOBS_ROOT_PATH . '/vendor/autoload.php';

$config  = include(GINO_JOBS_CONFIG_PATH);
$console = new Gino\Jobs\Console($config);


$console->process()->onWorkerInit(function () {
    $app = require __DIR__ . '/bootstrap/app.php';
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
});

$console->run();

// swoole4.6 以上版本，接入laravel框架时需要加入下面的异常处理，才不会导致主进程挂掉, Swoole\Event::rshutdown(): Event::wait() in shutdown function is deprecated
set_error_handler(function ($level, $message, $file = '', $line = 0, $context = []) {
    Utils::catchError(Logger::getLogger(), new \ErrorException($message, 0, $level, $file, $line));
});
