<?php

/**
 * 创建目录
 * @param string $dir
 * @return bool
 */
function mkdirs(string $dir): bool {
    return is_dir($dir) || (mkdirs(dirname($dir)) && @mkdir($dir, 0755));
}

/**
 * 捕捉错误和异常
 * @param \Gino\Jobs\Core\Logger $logger
 * @param Exception $exception
 */
function catchError(\Gino\Jobs\Core\Logger $logger, \Exception $exception) {
    $error = 'Error Type：' . get_class($exception) . PHP_EOL;
    $error .= 'Error Code：' . $exception->getCode() . PHP_EOL;
    $error .= 'Error Msg：' . $exception->getMessage() . PHP_EOL;
    $error .= 'Error Strace：' . $exception->getTraceAsString() . PHP_EOL;
    $logger->log($error, \Gino\Jobs\Core\Logger::LEVEL_ERROR, 'error');
}
