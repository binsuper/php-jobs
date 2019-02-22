<?php

namespace Gino\Jobs\Core;

/**
 * 辅助
 * 
 * @author Gino Huang <binsuper@126.com>
 */
class Utils {

    /**
     * 创建目录
     * @param string $dir
     * @return bool
     */
    public static function mkdir(string $dir): bool {
        return is_dir($dir) || (mkdirs(dirname($dir)) && @mkdir($dir, 0755));
    }

    /**
     * 捕捉错误和异常
     * @param \Gino\Jobs\Core\Logger $logger
     * @param Exception|Error $ex
     */
    public static function catchError(\Gino\Jobs\Core\Logger $logger, $ex) {
        $error = 'Error Type：' . get_class($ex) . PHP_EOL;
        $error .= 'Error Code：' . $ex->getCode() . PHP_EOL;
        $error .= 'Error Msg：' . $ex->getMessage() . PHP_EOL;
        $error .= 'Error Strace：' . $ex->getTraceAsString() . PHP_EOL;
        $logger->log($error, \Gino\Jobs\Core\Logger::LEVEL_ERROR, 'error');
    }

}
