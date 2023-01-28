<?php
declare(strict_types=1);
namespace lib\z\dbc;

use lib\z\cache as c;

trait cache
{
    function GetCache (string $mode, string $table, string $key)
    {
        return match ($mode) {
            'file'=>$this->getFileCache($table, $key),
            'redis'=>c::R("{$table}.{$key}"),
            'memcached'=>c::M("{$table}.{$key}"),
        };
    }

    function SetCache (string $mode, string $table, string $key, $data, int $expire = 0)
    {   return match ($mode) {
            'file'=>$this->setFileCache($table, $key, $data, $expire),
            'redis'=>c::R("{$table}.{$key}", $data, $expire),
            'memcached'=>c::M("{$table}.{$key}", $data, $expire),
        };
    }

    protected function getFileCache (string $table, string $key)
    {
        $file = $this->CacheDir() . "{$table}/{$key}.php";
        list($data, $expire) = c::GetFileCacheHasPrefix($file, c::CODE_PHP_ARR, true);
        if ($expire && $expire < TIME) {
            return null;
        }
        return $data;
    }
    protected function setFileCache (string $table, string $key, callable|array $data, int $expire = 0)
    {
        $expire && $expire < TIME && $expire += TIME;
        $file = $this->CacheDir() . "{$table}/{$key}.php";
        $ret = c::SetFileCache($file, $data, c::CODE_PHP_ARR, $expire);
        return $ret;
    }
}
