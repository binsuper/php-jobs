<?php

namespace Gino\Jobs\Core;

use Gino\Jobs\Core\IFace\ILogger;

/**
 * 日志管理
 *
 * @author GinoHuang <binsuper@126.com>
 */
class Logger implements ILogger {

    const LEVEL_INFO       = 'info';
    const LEVEL_ERROR      = 'error';
    const LEVEL_WARNING    = 'warning';
    const LEVEL_NOTICE     = 'notice';
    const LEVEL_DEBUG      = 'debug';
    const LEVEL_SCOPE      = [self::LEVEL_DEBUG => 1, self::LEVEL_NOTICE => 2, self::LEVEL_WARNING => 3, self::LEVEL_INFO => 4, self::LEVEL_ERROR => 5];
    const MAX_LOG_BUF_SIZE = 100; //缓冲区最大数量

    private static $__instance        = [];
    private        $__log_dir         = ''; //日志目录
    private        $__log_file        = 'application.log'; //日志默认输出的文件
    private        $__log_level       = 'debug'; // 日志输出级别
    private        $__log_category    = '__DEFAULT__'; //日志默认分类
    protected      $_log_buf          = []; //缓冲区
    protected      $_log_buf_size     = 0; //缓冲区日志数量
    public         $logfile_max_size  = 100; //日志文件大小限制,单位(mb)
    public         $logfile_max_count = 5; //日志文件数量限制
    private        $__sw_locks        = []; //文件锁

    public function __construct(string $log_dir, string $log_file = '', string $log_level = '') {
        if (empty($log_dir)) {
            die('[Logger] argunents#1<log_dir> is null' . PHP_EOL);
        }
        $this->__log_dir = $log_dir;
        if (!empty($log_file)) {
            $this->__log_file = $log_file;
        }
        if (!empty($log_level)) {
            $this->__log_level = $log_level;
        }
        Utils::mkdir($this->__log_dir);
    }

    /**
     * 获取实例
     *
     * @param string $name 实例名称
     * @return $this
     */
    public static function getLogger(string $name = '__MAIN__') {
        return static::$__instance[$name] ?? null;
    }

    /**
     * 注册日志实例
     *
     * @param string $log_dir 日志目录
     * @param string $log_file 默认的日志文件
     * @param string $name 实例名称
     * @return $this
     */
    public static function regist(string $log_dir, string $log_file = '', string $name = '__MAIN__', string $level = '') {
        if (!isset(static::$__instance[$name]) || static::$__instance[$name] == null) {
            static::$__instance[$name] = new static($log_dir, $log_file, $level);
        }
        return static::$__instance[$name];
    }

    /**
     * 获取默认日志分类
     *
     * @return string
     */
    public function getDefaultCategory(): string {
        return $this->__log_category;
    }

    /**
     * 获取日志缓冲区大小
     *
     * @return int
     */
    public function getMaxBufSize(): int {
        return static::MAX_LOG_BUF_SIZE;
    }

    /**
     * 满足日志等级要求
     *
     * @param $level
     */
    protected function isPower($level) {
        return (self::LEVEL_SCOPE[$this->__log_level] ?? 0) <= (self::LEVEL_SCOPE[$level] ?? 100);
    }

    /**
     * 格式化日志信息
     *
     * @param string $msg
     * @param string $level
     * @param int|float $time
     * @return string
     */
    protected function _formatLog(string $msg, string $level, $time) {
        return sprintf("[%s%s] [%s] [PID:%d] %s\n", date('Y/m/d H:i:s', $time), strstr($time, '.'), $level, getmypid(), $msg);
    }

    /**
     * 记录日志
     *
     * @param string $msg 信息
     * @param string $level 级别
     * @param string $category 日志分类，不同的分类存储到不同的日志文件
     * @param bool $flush 立即刷新缓冲区
     * @return $this
     */
    public function log(string $msg, string $level = self::LEVEL_INFO, string $category = '', bool $flush = false) {
        try {
            // 日志级别
            if (!$this->isPower($level)) {
                return $this;
            }
            // 默认分类
            if (!$category) {
                $category = $this->getDefaultCategory();
            }
            $this->_log_buf[$category][] = $this->_formatLog($msg, $level, microtime(true));
            $this->_log_buf_size++;

            //刷新缓冲区
            if ($flush || $this->_log_buf_size >= $this->getMaxBufSize()) {
                $this->flush();
            }
        } catch (\Exception $e) {
            echo 'something bad happened when logging message' . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
        } catch (\Throwable $e) {
            echo 'something bad happened when logging message' . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
        }
        return $this;
    }

    /**
     * 记录info级别的日志
     *
     * @param string $msg 信息
     * @param string $category 日志分类，不同的分类存储到不同的日志文件
     * @param bool $flush 立即刷新缓冲区
     * @return $this
     */
    public function info(string $msg, string $category = '', bool $flush = false) {
        $this->log($msg, static::LEVEL_INFO, $category, $flush);
        return $this;
    }

    /**
     * 记录error级别的日志
     *
     * @param string $msg 信息
     * @param string $category 日志分类，不同的分类存储到不同的日志文件
     * @param bool $flush 立即刷新缓冲区
     * @return $this
     */
    public function error(string $msg, string $category = '', bool $flush = false) {
        $this->log($msg, static::LEVEL_ERROR, $category, $flush);
        return $this;
    }

    /**
     * 记录warning级别的日志
     *
     * @param string $msg 信息
     * @param string $category 日志分类，不同的分类存储到不同的日志文件
     * @param bool $flush 立即刷新缓冲区
     * @return $this
     */
    public function warning(string $msg, string $category = '', bool $flush = false) {
        $this->log($msg, static::LEVEL_WARNING, $category, $flush);
        return $this;
    }

    /**
     * 记录notice级别的日志
     *
     * @param string $msg 信息
     * @param string $category 日志分类，不同的分类存储到不同的日志文件
     * @param bool $flush 立即刷新缓冲区
     * @return $this
     */
    public function notice(string $msg, string $category = '', bool $flush = false) {
        $this->log($msg, static::LEVEL_NOTICE, $category, $flush);
        return $this;
    }

    /**
     * 刷新日志缓冲区,将缓冲区中的日志输出到文件中
     */
    public function flush() {
        if ($this->_log_buf_size < 1) {
            return;
        }
        $this->_dumpFile();
        $this->_log_buf = [];
        $this->_log_buf_size = 0;
    }

    /**
     * 将日志输出到文件
     */
    protected function _dumpFile() {
        if (empty($this->_log_buf)) {
            return;
        }
        $log_buf = $this->_log_buf;
        $last_lock = null;
        foreach ($log_buf as $cat => $msg_list) {
            try {
                if ($cat === $this->__log_category) {
                    $log_dir = $this->__log_dir;
                    $log_file = strtr($this->__log_file, ['.log' => '']);
                } else {
                    $log_dir = $this->__log_dir . DIRECTORY_SEPARATOR . $cat;
                    $log_file = $cat;
                    Utils::mkdir($log_dir);
                }
                $log_file .= '-' . date('Ymd') . '.log';

                $filename = $log_dir . DIRECTORY_SEPARATOR . $log_file;
                $content = implode('', $msg_list);

                //文件加锁
                if (!isset($this->__sw_locks[$filename])) {
                    $this->__sw_locks[$filename] = new \Swoole\Lock(SWOOLE_MUTEX, $filename);
                }
                $last_lock = $this->__sw_locks[$filename];
                if (!$this->__sw_locks[$filename]->trylock()) {
                    continue;
                }
                //文件大小超出限制，则切割日志文件
                clearstatcache(true, $filename);
                if (file_exists($filename) && @filesize($filename) >= ($this->logfile_max_size * 1024 * 1024)) {
                    $this->_rotateFiles($filename);
                }
                //解锁
                $this->__sw_locks[$filename]->unlock();
                //输出日志文件
                error_log($content, 3, $filename);
            } catch (\Exception $e) {
                $this->log('something bad happened when dumping the log files' . PHP_EOL . $e->getTraceAsString(), static::LEVEL_ERROR, 'error');
            } catch (\Throwable $e) {
                $this->log('something bad happened when dumping the log files' . PHP_EOL . $e->getTraceAsString(), static::LEVEL_ERROR, 'error');
            } finally {
                if ($last_lock) {
                    $last_lock->unlock();
                }
            }
        }
        unset($log_buf);
    }

    /**
     * 切割日志文件
     *
     * @param string $filename
     */
    protected function _rotateFiles(string $filename) {
        for ($i = $this->logfile_max_count; $i >= 0; $i--) {
            $rotate_name = $filename . ($i == 0 ? '' : '.' . $i);
            if (!is_file($rotate_name)) {
                continue;
            }
            if ($i == $this->logfile_max_count) {
                @unlink($rotate_name);
            } else {
                @rename($rotate_name, $filename . '.' . ($i + 1));
            }
        }
    }

}
