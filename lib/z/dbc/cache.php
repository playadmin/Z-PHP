<?php
declare(strict_types=1);
namespace lib\z\dbc;

use lib\z\cache as c;

trait cache
{
    function GetCache (string $table, string $key, string $dbname = '', string $mod = '')
    {
        $mod || $mod = $this->DB_CONFIG['cache_mod'];
        $dbname || $dbname = $this->DB_CONFIG['dbname'];
        return match ($mod) {
            'file'=>$this->getFileCache($this->cacheDir($dbname, $table), $key),
            'redis'=>c::R("DB:{$dbname}:{$table}:{$key}"),
            'memcached'=>c::M("DB:{$dbname}:{$table}:{$key}"),
        };
    }

    function SetCache (string $table, string $key, $data, int $expire = 0, string $dbname = '', string $mod = '')
    {   
        $mod || $mod = $this->DB_CONFIG['cache_mod'];
        $dbname || $dbname = $this->DB_CONFIG['dbname'];
        return match ($mod) {
            'file'=>$this->setFileCache($this->cacheDir($dbname, $table), $key, $data, $expire),
            'redis'=>c::R("DB:{$dbname}:{$table}:{$key}", $data, $expire),
            'memcached'=>c::M("DB:{$dbname}:{$table}:{$key}", $data, $expire),
        };
    }

    protected function cacheDir (string $table, string $dbname = '')
    {
        $dbname || $dbname = $this->DB_CONFIG['dbname'];
        return P_CACHE . "{$this->DB_CONFIG['cache_dir']}/{$dbname}/{$table}";
    }
    function CleanCache (string $table, string $mod = '', string $dbname = ''): int
    {
        $mod || $mod = $this->DB_CONFIG['cache_mod'];
        $dbname || $dbname = $this->DB_CONFIG['dbname'];
        switch ($mod) {
            case 'redis':
                return $this->delRedis($dbname, $table);
                break;
            case 'memcached':
                return $this->delMemcached($dbname, $table);
                break;
            default:
                return (int)($this->delFile($dbname, $table));
                break;
        }
    }
    private function delFile(string $table, string $dbname = ''): int | false
    {
        $dbname || $dbname = $this->DB_CONFIG['dbname'];
        $dir = $this->cacheDir($dbname, $table);
        return DelDir($dir, true);
    }
    protected function delRedis (string $table, string $dbname = ''): int
    {
        $dbname || $dbname = $this->DB_CONFIG['dbname'];
        $path = "DB:{$dbname}:";
        $table && $path .= "{$table}:";
        $redis = cache::Redis();
        if ($keys = $redis->keys("{$path}*")) {
            $redis->pipeline();
            foreach ($keys as $key) {
                $redis->del($key);
            }
            $result = $redis->exec();
            $result && $result = (int)array_sum($result);
        } else {
            $result = 0;
        }
        return $result;
    }
    protected function delMemcached(string $table, string $dbname = ''): int
    {
        $dbname || $dbname = $this->DB_CONFIG['dbname'];
        $path = "DB:{$dbname}:";
        $table && $path .= "{$table}:";
        $preg = "/^{$path}.+$/i";
        $mem = cache::Memcached();
        $n = 0;
        if ($keys = $mem->getAllKeys()) {
            foreach ($keys as $key) {
                if (preg_match($preg, $key)) {
                    $n += $mem->delete($key);
                }
            }
        }
        return $n;
    }

    protected function getFileCache (string $dir, string $key)
    {
        $file = "{$dir}/{$key}.php";
        list($data, $expire) = c::GetExpireFileCache($file, c::CODE_PHP_ARR);
        if ($expire && $expire < TIME) {
            return null;
        }
        return $data;
    }
    protected function setFileCache (string $dir, string $key, callable|array $data, int $expire = 0)
    {
        $file = "{$dir}/{$key}.php";
        $expire || $expire = 60;
        return c::SetFileCache($file, $data, c::CODE_PHP_ARR, $expire + TIME);
    }
}
