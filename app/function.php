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
    $error = '错误类型：' . get_class($exception) . PHP_EOL;
    $error .= '错误代码：' . $exception->getCode() . PHP_EOL;
    $error .= '错误信息：' . $exception->getMessage() . PHP_EOL;
    $error .= '错误堆栈：' . $exception->getTraceAsString() . PHP_EOL;
    $logger->log($error, \Gino\Jobs\Core\Logger::LEVEL_ERROR, 'error');
}
