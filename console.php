<?php

define('GINO_JOBS_ROOT_PATH', __DIR__);

if (!defined('GINO_JOBS_CONFIG_PATH')) {
    define('GINO_JOBS_CONFIG_PATH', GINO_JOBS_ROOT_PATH . '/conf/config.php');
}

if (!class_exists('Composer\Autoload\ClassLoader')) {
    require_once GINO_JOBS_ROOT_PATH . '/vendor/autoload.php';
}

$config = include(GINO_JOBS_CONFIG_PATH);

$console = new Gino\Jobs\Console($config);
$console->run();
