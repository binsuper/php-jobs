<?php


namespace Gino\Jobs\Core\IFace;

/**
 * Interface ICommand
 * 执行脚本
 *
 * @author GinoHuang <binsuper@126.com>
 */
interface ICommand {

    /**
     * 命令行脚本执行
     *
     * @param array $params
     * @return mixed
     */
    public function execute(array $params);

}