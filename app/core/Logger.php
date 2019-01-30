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
    const MAX_LOG_BUF_SIZE = 1000; //缓冲区最大数量

    private static $__instalce = [];
    private $__log_dir         = ''; //日志目录
    private $__log_file        = 'application.log'; //日志默认输出的文件
    private $__log_category    = '__DEFAULT__'; //日志默认分类
    protected $_log_buf        = []; //缓冲区
    protected $_log_buf_size   = 0; //缓冲区日志数量
    public $logfile_max_size   = 100; //日志文件大小限制,单位(mb)
    public $logfile_max_count  = 5; //日志文件数量限制

    public function __construct(string $log_dir, string $log_file = '') {
        if (empty($log_dir)) {
            die('[Logger] argunents#1<log_dir> is null' . PHP_EOL);
        }
        $this->__log_dir = $log_dir;
        if (empty($log_file)) {
            $this->__log_file = $log_file;
        }
        $this->_mkdir($this->__log_dir);
    }

    /**
     * 获取实例
     * 
     * @param string $log_dir 日志目录
     * @param string $log_file 默认的日志文件
     * @return Logger
     */
    public static function getLogger(string $log_dir, string $log_file = '') {
        $hash = $log_dir . '/' . $log_dir;
        if (!isset(self::$__instalce[$hash]) || self::$__instalce[$hash] == null) {
            self::$__instalce[$hash] = new self($log_dir, $log_file);
        }
        return self::$__instalce[$hash];
    }

    /**
     * 格式化日志信息
     * 
     * @param string $msg
     * @param string $level
     * @param string $category
     * @param int|float $time
     */
    protected function _formatLog(string $msg, string $level, $time) {
        return sprintf("%s%s [%s] [PID:%d]\n%s\n", date('Y/m/d H:i:s', $time), strstr($time, '.'), $level, getmypid(), $msg);
    }

    /**
     * 创建目录
     * @param string $dir
     * @return bool
     */
    protected function _mkdir(string $dir): bool {
        return is_dir($dir) || ($this->_mkdir(dirname($dir)) && @mkdir($dir, 0755));
    }

    /**
     * 记录日志
     * 
     * @param string $msg 信息
     * @param string $level 级别
     * @param string $category 日志分类，不同的分类存储到不同的日志文件
     * @param bool $flush 立即刷新缓冲区
     */
    public function log(string $msg, string $level = self::LEVEL_INFO, string $category = '', bool $flush = false) {
        if (!$category) {
            $category = $this->__log_category;
        }
        $this->_log_buf[$category][] = $this->_formatLog($msg, $level, microtime(true));
        $this->_log_buf_size++;

        //刷新缓冲区
        if ($flush || $this->_log_buf_size >= self::MAX_LOG_BUF_SIZE) {
            $this->flush();
        }
    }

    /**
     * 刷新日志缓冲区,将缓冲区中的日志输出到文件中
     */
    public function flush() {
        if ($this->_log_buf_size < 1) {
            return;
        }
        $this->_dumpFile();
        $this->_log_buf      = [];
        $this->_log_buf_size = 0;
    }

    /**
     * 将日志输出到文件
     */
    protected function _dumpFile() {
        if (empty($this->_log_buf)) {
            return;
        }
        foreach ($this->_log_buf as $cat => $msg_list) {
            if ($cat === $this->__log_category) {
                $log_dir  = $this->__log_dir;
                $log_file = strtr($this->__log_file, ['.log' => '']);
            } else {
                $log_dir  = $this->__log_dir . DIRECTORY_SEPARATOR . $cat;
                $log_file = $cat;
                $this->_mkdir($log_dir);
            }
            $log_file .= '-' . date('Ymd') . '.log';

            $filename = $log_dir . DIRECTORY_SEPARATOR . $log_file;
            $content  = implode('', $msg_list);

            //文件大小超出限制，则切割日志文件
            if (@filesize($filename) >= ($this->logfile_max_size * 1024 * 1024)) {
                $this->_rotateFiles($filename);
            }

            //输出日志文件
            error_log($content, 3, $filename);
        }
    }

    /**
     * 切割日志文件
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
