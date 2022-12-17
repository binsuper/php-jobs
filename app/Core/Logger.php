<?php

namespace Gino\Jobs\Core;

use Gino\Jobs\Core\IFace\ILogger;

use \Gino\Phplib\Log\Logger as ActuallyLogger;
use \Gino\Phplib\Log\Executor;

/**
 * 日志管理
 *
 * @author GinoHuang <binsuper@126.com>
 */
class Logger implements ILogger {

    const LEVEL_INFO    = 'info';
    const LEVEL_ERROR   = 'error';
    const LEVEL_WARNING = 'warning';
    const LEVEL_NOTICE  = 'notice';
    const LEVEL_DEBUG   = 'debug';

    private static $__instance = null;

    /** @var ActuallyLogger */
    private $__logger = null;

    /** deprecated */
    private $__log_dir = ''; //日志目录
    /** @deprecated */
    const LEVEL_SCOPE = [self::LEVEL_DEBUG => 1, self::LEVEL_NOTICE => 2, self::LEVEL_WARNING => 3, self::LEVEL_INFO => 4, self::LEVEL_ERROR => 5];
    /** @deprecated */
    const MAX_LOG_BUF_SIZE = 100; //缓冲区最大数量
    /** @deprecated */
    private $__log_file = 'application.log'; //日志默认输出的文件
    /** @deprecated */
    private $__log_level = 'debug'; // 日志输出级别
    /** @deprecated */
    private $__log_category = '__DEFAULT__'; //日志默认分类
    /** @deprecated */
    protected $_log_buf = []; //缓冲区
    /** @deprecated */
    protected $_log_buf_size = 0; //缓冲区日志数量
    /** @deprecated */
    public $logfile_max_size = 100; //日志文件大小限制,单位(mb)
    /** @deprecated */
    public $logfile_max_count = 5; //日志文件数量限制
    /** @deprecated */
    private $__sw_locks = []; //文件锁

    public function __construct(string $log_dir = '', string $log_file = '') {
        $this->__logger = new ActuallyLogger(Config::get('log'));
    }

    /**
     * 获取实例
     *
     * @return static
     */
    public static function getLogger() {
        if (static::$__instance === null) {
            static::$__instance = new static();
        }
        return static::$__instance;
    }

    /**
     * 注册日志实例
     *
     * @param string $log_dir 日志目录
     * @param string $log_file 默认的日志文件
     * @param string $name 实例名称
     * @return $this
     * @deprecated
     */
    public static function regist(string $log_dir, string $log_file = '', string $name = '__MAIN__', string $level = '') {
        if (static::$__instance === null) {
            static::$__instance = new static($log_dir, $log_file);
        }
        return static::$__instance;
    }

    /**
     * 初始化分类配置
     *
     * @param string $category
     * @return $this
     */
    protected function initCategory(string $category) {
        if ($category == '') {
            return $this;
        }

        $logger = $this->__logger;

        $channel = 'channels.' . $category;
        if ($logger->getConfig()->has($channel)) {
            return $this;
        }

        $log_dir = dirname($logger->getConfig()->get('channels.' . $logger->getConfig()->get('default') . '.path'));
        $options = [
                'path' => $log_dir . DIRECTORY_SEPARATOR . strtolower($category) . DIRECTORY_SEPARATOR . $category . '.log'
            ] + $logger->getConfig()->get('channels.' . $logger->getConfig()->get('default'));
        $logger->getConfig()->set($channel, $options);
        return $this;
    }

    /**
     * 格式化日志信息
     *
     * @param string $msg
     * @return string
     * @deprecated
     */
    protected function _formatLog(string $msg) {
        return sprintf('[PID:%d] %s', getmypid(), $msg);
    }

    /**
     * 记录日志
     *
     * @param string $msg 信息
     * @param string $level 级别
     * @param string $category 日志分类，不同的分类存储到不同的日志文件
     * @param bool $flush 立即刷新缓冲区
     * @return $this
     * @deprecated
     */
    public function log(string $msg, string $level = self::LEVEL_INFO, string $category = '', bool $flush = false) {
        $this->initCategory($category);
        static::channel($category)->log($level, $msg);
        return $this;
    }

    /**
     * 记录info级别的日志
     *
     * @param string $msg 信息
     * @param string $category 日志分类，不同的分类存储到不同的日志文件
     * @param bool $flush 立即刷新缓冲区
     * @return $this
     * @deprecated
     */
    public function info(string $msg, string $category = '', bool $flush = false) {
        return $this->log($msg, static::LEVEL_INFO, $category);
    }

    /**
     * 记录error级别的日志
     *
     * @param string $msg 信息
     * @param string $category 日志分类，不同的分类存储到不同的日志文件
     * @param bool $flush 立即刷新缓冲区
     * @return $this
     * @deprecated
     */
    public function error(string $msg, string $category = '', bool $flush = false) {
        return $this->log($msg, static::LEVEL_ERROR, $category);
    }

    /**
     * 记录warning级别的日志
     *
     * @param string $msg 信息
     * @param string $category 日志分类，不同的分类存储到不同的日志文件
     * @param bool $flush 立即刷新缓冲区
     * @return $this
     * @deprecated
     */
    public function warning(string $msg, string $category = '', bool $flush = false) {
        return $this->log($msg, static::LEVEL_WARNING, $category);
    }

    /**
     * 记录notice级别的日志
     *
     * @param string $msg 信息
     * @param string $category 日志分类，不同的分类存储到不同的日志文件
     * @param bool $flush 立即刷新缓冲区
     * @return $this
     * @deprecated
     */
    public function notice(string $msg, string $category = '', bool $flush = false) {
        return $this->log($msg, static::LEVEL_NOTICE, $category);
    }

    /**
     * @inheritDoc
     * @deprecated
     */
    public function flush() {
        return true;
    }

    /**
     * 获取默认日志分类
     *
     * @return string
     * @deprecated
     */
    public function getDefaultCategory(): string {
        return $this->__log_category;
    }

    /**
     * 获取日志缓冲区大小
     *
     * @return int
     * @deprecated
     */
    public function getMaxBufSize(): int {
        return static::MAX_LOG_BUF_SIZE;
    }

    /**
     * @return ActuallyLogger
     */
    private function logger(): ActuallyLogger {
        return $this->__logger;
    }

    /**
     * 返回日志通道
     *
     * @param string $channel
     * @return Executor
     */
    public static function channel(string $channel = ''): Executor {
        return static::getLogger()->initCategory($channel)->logger()->channel($channel);
    }

}
