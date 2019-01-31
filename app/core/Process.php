<?php

namespace Gino\Jobs\Core;

use Gino\Jobs\Core\Logger;
use Gino\Jobs\Core\Config;

/**
 * 进程管理
 * 
 * @author GinoHuang <binsuper@126.com>
 */
class Process {

    const STATUS_RUNNING = 'running';   //运行中
    const STATUS_WAITALL = 'wait-all';  //等待所有子进程平滑结束

    protected $_logger;
    private $__pid_dir;
    private $__pid_file      = 'master.pid';
    private $__pid_info_file = 'master.info';
    private $__pid;
    private $__status; //进程状态

    public function __construct() {
        $config = Config::getConfig('process');
        if (empty($config) || empty($config['data_dir'])) {
            die('config process.data_dir must be set' . PHP_EOL);
        }

        $this->_logger         = Logger::getLogger();
        $this->__pid_dir       = $config['data_dir'];
        $this->__pid_file      = $this->__pid_dir . DIRECTORY_SEPARATOR . $this->__pid_file;
        $this->__pid_info_file = $this->__pid_dir . DIRECTORY_SEPARATOR . $this->__pid_info_file;
        mkdirs($this->__pid_dir);

        //运行
        $this->_run();
    }

    /**
     * 启动进程管理
     */
    protected function _run() {

        //启动进程
        \Swoole\Process::daemon(); //使当前进程蜕变为一个守护进程
        $this->__pid = getmypid();
        if (!$this->__pid) {
            throw new \RuntimeException('can not get the master process id');
        }
        $this->__status = self::STATUS_RUNNING;

        $data = [
            'pid'    => $this->__pid,
            'status' => $this->__status
        ];

        $this->__setPidFile();
        $this->__setMasterInfo($data);
        $this->__setProcessName('php-jobs master');

    }

    /**
     * 退出主进程
     */
    protected function _exit() {
        @unlink($this->__pid_file);
        @unlink($this->__pid_info_file);
        $this->_logger->log('master process exit');
        exit();
    }

    /**
     * 设置pid文件
     * @throws \RuntimeException
     */
    private function __setPidFile() {
        if (!file_put_contents($this->__pid_file, $this->__pid)) {
            throw new \RuntimeException('can not save pid file with pid:' . $this->__pid);
        }
    }

    /**
     * 设置主进程的信息
     * @param array $data
     */
    private function __setMasterInfo(array $data) {
        if (!file_put_contents($this->__pid_info_file, serialize($data))) {
            throw new \RuntimeException('can not save master-info file with pid:' . $this->__pid);
        }
    }

    /**
     * 获取主进程的信息
     * @return array
     */
    private function __getMasterInfo(string $key = ''): array {
        $data = file_get_contents($this->__pid_info_file);
        if (false === $data) {
            throw new \RuntimeException('can not read master-info file with pid:' . $this->__pid);
        }
        $data = unserialize($data);
        if ($key === '') {
            return $data[$key] ?? null;
        }
        return $data;
    }

    /**
     * 设置进程名称
     * @param string $process_name
     */
    private function __setProcessName(string $process_name) {
        swoole_set_process_name($process_name);
    }

    public function start() {
        
    }

}
