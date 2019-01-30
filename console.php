<?php

define('GINO_JOBS_ROOT_PATH', __DIR__);


require_once GINO_JOBS_ROOT_PATH . '/vendor/autoload.php';

$config = include(GINO_JOBS_ROOT_PATH . '/conf/config.php');

$console = new Gino\Jobs\Console($config);
$console->run();