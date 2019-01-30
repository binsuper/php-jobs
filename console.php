<?php

define('PHP_JOBS_ROOT_PATH', __DIR__);


require_once PHP_JOBS_ROOT_PATH . '/vender/autoload.php';

$console = new Gino\Jobs\Console();
$console->run();