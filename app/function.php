<?php

/**
 * 创建目录
 * @param string $dir
 * @return bool
 */
function mkdirs(string $dir): bool {
    return is_dir($dir) || (mkdirs(dirname($dir)) && @mkdir($dir, 0755));
}
