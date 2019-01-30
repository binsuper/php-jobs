<?php

namespace Gino\Jobs\Core;

/**
 * 配置信息
 *
 * GinoHuang <binsuper@126.com>
 */
class Config {

    private static $__cfg = [];

    /**
     * 设置配置信息
     * @param array $config
     */
    public static function setConfig(array $config) {
        self::$__cfg = $config;
    }

    /**
     * 获取配置信息
     * @param string $section 区域
     * @param string $key 配置项名称，不填时默认返回全部配置信息
     * @param mixed $default 默认返回值，当配置项名称不存在时返回该参数值
     * @return mixed
     */
    public static function getConfig(string $section = '', string $key = '', $default = null) {
        if ($section === '') {
            if ($key === '') {
                if ($section === '') {
                    return self::$__cfg;
                }
            }
            return self::$__cfg[$key] ?? $default;
        } else {
            if ($key === '') {
                return self::$__cfg[$section] ?? $default;
            }
            return isset(self::$__cfg[$section]) ? (self::$__cfg[$section][$key] ?? $default) : $default;
        }
    }

}
