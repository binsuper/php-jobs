<?php

namespace Gino\Jobs;

use Gino\Jobs\Core\IFace\ILogger;
use Gino\Jobs\Core\Logger;
use Gino\Jobs\Core\Config;

/**
 * 控制台
 * 
 * @author Gino Huang <binsuper@126.com>
 */
class Console {

    /**
     *
     * @var ILogger
     */
    protected $_logger;
    protected $_config = [];

    public function __construct(array $config) {
        //校验配置参数
        if (empty($config['log']) || empty($config['log']['log_dir'])) {
            die('config log.log_dir must be set' . PHP_EOL);
        }
        //配置
        Config::setConfig($config);
        $this->_config = $config;
        Config::getConfig('log_dir', 'log');
        $this->_logger = Logger::getLogger(Config::getConfig('log', 'log_dir'), Config::getConfig('log', 'log_file', ''));
    }

    /**
     *  运行控制台
     * 
     * @global array $argv
     */
    public function run() {
        global $argv;
        $command_args = array_slice($argv, 1);

        $this->_logger->log('11');
        $this->_logger->log('22');
        $this->_logger->log('33');
        $this->_logger->log('44');
        $this->_logger->log('55');
        $this->_logger->log('66');
        $this->_logger->log('77');
        $this->_logger->log('88');
        $this->_logger->flush();
    }

}
