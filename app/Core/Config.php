<?php

namespace Gino\Jobs\Core;

use Gino\Phplib\ArrayObject;

/**
 * 配置信息
 *
 * GinoHuang <binsuper@126.com>
 */
class Config {

    /**
     * @var ArrayObject
     */
    private static $__cfg = null;

    /**
     * 设置配置信息
     *
     * @param array $config
     */
    public static function setConfig(array $config) {
        self::$__cfg = new ArrayObject($config);
    }

    /**
     * 获取配置信息
     *
     * @param string $section 区域
     * @param string $key 配置项名称，不填时默认返回全部配置信息
     * @param mixed $default 默认返回值，当配置项名称不存在时返回该参数值
     * @return mixed
     */
    public static function getConfig(string $key = '', $default = null) {
        return static::get($key, $default);
    }

    /**
     * 获取配置信息
     *
     * @param string $key 配置项名称，不填时默认返回全部配置信息
     * @param mixed $default 默认返回值，当配置项名称不存在时返回该参数值
     * @return mixed
     */
    public static function get(string $key = '', $default = null) {
        if (is_null(static::$__cfg)) {
            static::$__cfg = new ArrayObject();
        }
        if($key == ''){
            return static::$__cfg->all();
        }
        return static::$__cfg->get($key, $default);
    }

}
