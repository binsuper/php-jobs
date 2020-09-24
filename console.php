<?php

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
