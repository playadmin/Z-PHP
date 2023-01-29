<?php
declare(strict_types=1);
namespace lib\z;

use Exception;

use Redis;

class cache
{
    const CODE_JSON = 1;
    const CODE_SERI = 2;
    const CODE_PHP = 3;
    const CODE_PHP_ARR = 4;
    const LOCK_EXPIRE = 30; // 获取缓存锁的超时时间(秒)
    const LOCK_SLEEP = 1000; // 获取缓存锁的重试间隔(微秒)
    const LOCK_KEY_PREFIX = 'z-php-lock:'; // 缓存锁的键名前缀
    const TRY_USLEEP = 2000; // 尝试读取缓存的间隔(微秒)
    const TRY_EXPIRE = 30000; // 尝试读取缓存的超时时间(毫秒)
    private static $Z_REDIS, $Z_MEMCACHED;
    public static function Redis(array $c = null, bool $new = false): Redis
    {
        $c || $c = $GLOBALS['ZPHP_CONFIG']['REDIS'] ?? null;
        if (!$c) {
            throw new \Exception("没有配置redis连接参数");
        }

        if ($new) {
            $new = new Redis();
            $new->connect($c['host'], $c['port'], $c['timeout'] ?? 1);
            empty($c['pass']) || $new->auth($c['pass']);
            empty($c['db']) || $new->select($c['db']);
            $new->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
            return $new;
        }
        $key = "{$c['host']}:{$c['port']}";
        if (!isset(self::$Z_REDIS[$key])) {
            self::$Z_REDIS[$key] = new Redis();
            self::$Z_REDIS[$key]->connect($c['host'], $c['port'], $c['timeout'] ?? 1);
            empty($c['pass']) || self::$Z_REDIS[$key]->auth($c['pass']);
            empty($c['db']) || self::$Z_REDIS[$key]->select($c['db']);
            self::$Z_REDIS[$key]->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        }
        return self::$Z_REDIS[$key];
    }
    public static function Memcached(array $c = null): \Memcached
    {
        $c || $c = $GLOBALS['ZPHP_CONFIG']['MEMCACHED'] ?? null;
        if (!$c) {
            throw new \Exception("没有配置memcached连接参数");
        }

        $key = md5(serialize($c));
        if (!isset(self::$Z_MEMCACHED[$key])) {
            self::$Z_MEMCACHED[$key] = new \Memcached();
            self::$Z_MEMCACHED[$key]->addServers($c);
        }
        return self::$Z_MEMCACHED[$key];
    }

    /**
     * redis锁
     * @param redis redis 连接实例
     * @param key 键名
     * @param expire 获取锁的超时时间（秒）
     * @return 成功返回锁的键名，否则返回false
     */
    public static function Rlock($redis, string $key, int $expire = 0): string|false
    {
        $lock_key = self::LOCK_KEY_PREFIX . $key;
        if ($expire) {
            if (!$r = $redis->set($lock_key, 1, ['nx', 'ex' => $expire])) {
                $try = (int) $expire * 1000000 / self::LOCK_SLEEP - 1;
                do {
                    usleep(self::LOCK_SLEEP);
                    $r = $redis->set($lock_key, 1, ['nx', 'ex' => $expire]);
                } while (!$r && --$try);
            }
        } else {
            $r = $redis->set($lock_key, 1, ['nx', 'ex' => self::LOCK_EXPIRE]);
        }
        return $r ? $lock_key : false;
    }

    /**
     * memcached锁
     * @param mem memcached 连接实例
     * @param key 键名
     * @param expire 获取锁的超时时间（秒）
     * @return 成功返回锁的键名，否则返回false
     */
    public static function Mlock($mem, string $key, int $expire = 0): string|false
    {
        $lock_key = self::LOCK_KEY_PREFIX . $key;
        if ($expire) {
            if (!$r = $mem->add($lock_key, 1, $expire)) {
                $try = (int) ($expire * 1000000 / self::LOCK_SLEEP) - 1;
                do {
                    usleep(self::LOCK_SLEEP);
                    $r = $mem->add($lock_key, 1, $expire);
                } while (!$r && --$try);
            }
        } else {
            $r = $mem->add($lock_key, 1, self::LOCK_EXPIRE);
        }
        return $r ? $lock_key : false;
    }

    /**
     * Redis缓存操作
     * @param key 缓存 key
     * @param data 待写入的数据：为 null 时表示读取缓存，可以是一个回调函数，只在需要写入时调用
     * @param expire 缓存时间：为假时表示不超时
     * @param lock 并发锁
     * @return 读取或写入的数据
     */
    public static function R(string $key, $data = null, int $expire = null, int $lock = 0)
    {
        $redis = self::Redis();
        isset($expire) || $expire = $GLOBALS['ZPHP_CONFIG']['REDIS']['expire'] ?? 600;
        if (null === $data) {
            $result = $redis->get($key);
            $result && $result = unserialize($result);
        } elseif ($lock) {
            $lock_key = self::LOCK_KEY_PREFIX . $key;
            if ($redis->set($lock_key, '1', ['nx', 'ex' => self::LOCK_EXPIRE])) {
                is_callable($data) && $data = $data() ?: '';
                $r = $expire ? $redis->setex($key, $expire, serialize($data)) : $redis->set($key, serialize($data));
                $redis->del($lock_key);
                $result = $r ? $data : false;
            } else {
                $i = ceil(1000 * self::TRY_EXPIRE / self::TRY_USLEEP);
                do {
                    usleep(self::TRY_USLEEP);
                    $result = $redis->get($key);
                } while (false === $result && --$i);
                $result && $result = unserialize($result);
                return $result;
            }
        } else {
            is_callable($data) && $data = $data() ?: '';
            $r = $expire ? $redis->setex($key, $expire, serialize($data)) : $redis->set($key, serialize($data));
            $result = $r ? $data : false;
        }
        return $result;
    }

    /**
     * Memcached缓存操作
     * @param key 缓存 key
     * @param data 待写入的数据：为 null 时表示读取缓存，可以是一个回调函数，只在需要写入时调用
     * @param expire 缓存时间：为假时表示不超时
     * @param lock 并发锁
     * @return 读取或写入的数据
     */
    public static function M($key, $data = null, $expire = null, $lock = 0)
    {
        $mem = self::Memcached();
        isset($expire) || $expire = $GLOBALS['ZPHP_CONFIG']['MEMCACHED']['expire'] ?? 600;
        if (null === $data) {
            $result = $mem->get($key);
            $result && $result = unserialize($result);
        } elseif ($lock) {
            $lock_key = self::LOCK_KEY_PREFIX . $key;
            if ($mem->add($lock_key, 1, self::LOCK_EXPIRE)) {
                is_callable($data) && $data = $data() ?: '';
                $r = $expire ? $mem->set($key, serialize($data), $expire) : $mem->set($key, serialize($data));
                $mem->delete($lock_key);
                $result = $r ? $data : false;
            } else {
                $i = ceil(1000 * self::TRY_EXPIRE / self::TRY_USLEEP);
                do {
                    usleep(self::TRY_USLEEP);
                    $result = $mem->get($key);
                } while (false === $result && --$i);
                $result && $result = unserialize($result);
                return $result;
            }
        } else {
            is_callable($data) && $data = $data() ?: '';
            $r = $expire ? $mem->set($key, serialize($data), $expire) : $mem->set($key, serialize($data));
            $result = $r ? $data : false;
        }
        return $result;
    }

    /**
     * 写入文件缓存
     * @param file 文件路径
     * @param data 待写入的数据：可以是一个回调函数，只在需要写入时调用
     * @param flag [0: 直接写入, 1: json_encode 编码后写入, 2: serialize 编码后写入, 3: 作为php代码写入 4: 作为php数组写入]
     * @param timeStamp 缓存数据的前缀(时间戳 <=0 表示没有过期时间)
     * @return array [写入字节数或false, 写入的数据]
     * 高并发时只有单个进程可以获取到锁，并写入文件；其它进程将等待写入完成后读取该文件数据并返回
     * 注意：windows 环境下如果同一秒内多次调用，只会写入一次！（不适合对时效要求很高[一秒内]的缓存）
     */
    public static function SetFileCache(string $file, $data, int $flag = 0, int $timeStamp = 0): array
    {
        file_exists($dir = dirname($file)) || MakeDir($dir, 0755, true);
        return IsWindows() ? self::setCacheWindows($file, $data, $flag, $timeStamp) : self::setCacheLinux($file, $data, $flag, $timeStamp);
    }

    /**
     * 写入文件缓存
     * @param file 文件路径
     * @param data 待写入的数据：可以是一个回调函数，只在需要写入时调用
     * @param flag [0: 直接写入, 1: json_encode 编码后写入, 2: serialize 编码后写入, 3: 作为php代码写入, 4: 作为php数组写入]
     * @param time 缓存数据的前缀(时间戳 <=0 表示没有过期时间)
     * @return array [写入字节数或false, 写入的数据]
     * 不同于 SetFileCache(), 此方法一定会写入, 并发时会依次写入
     */
    public static function PutFileCache (string $file, $data, int $flag = 2, int $timeStamp = 0): int|false
    {
        file_exists($dir = dirname($file)) || MakeDir($dir, 0755, true);
        $prefix = 0 > $timeStamp ? '' :  pack('N', $timeStamp);
        $str = match ($flag) {
            self::CODE_JSON => $prefix . json_encode($data, JSON_ENCODE_CFG),
            self::CODE_SERI => $prefix . serialize($data),
            self::CODE_PHP => "<?php\n\$__cache_expire__={$timeStamp};\nreturn " . var_export($data, true) . ';',
            self::CODE_PHP_ARR=>"<?php\n\$__cache_expire__={$timeStamp};\nreturn " . ExportArray($data) . ';',
            default => $prefix . $data,
        };
        return file_put_contents($file, $str, LOCK_EX);
    }

    /**
     * 获取无过期时间的缓存数据
     * @param file 文件路径
     * @param flag [0: 获取字符串, 1: json_decode 解码, 2: serialize 解码, 3: php代码, 4: php数组]
     */
    public static function GetFileCache(string $file, int $flag = 2)
    {
        if (!is_file($file)) {
            return null;
        }
        if ((!$h = fopen($file, 'r')) || !flock($h, LOCK_SH)) {
            throw new \Exception('获取文件共享锁失败');
        }
        if (self::CODE_PHP === $flag || self::CODE_PHP_ARR === $flag) {
            is_callable('opcache_invalidate') && opcache_invalidate($file);
            $data = require($file);
        } elseif ($size = filesize($file)) {
            $str = fread($h, $size);
            $data = match ($flag) {
                self::CODE_JSON => $str ? json_decode($str, true) : null,
                self::CODE_SERI => $str ? unserialize($str) : null,
                default => $str,
            };
        } else {
            $data = null;
        }
        flock($h, LOCK_UN);
        fclose($h);
        return $data;
    }

    /**
     * 获取有过期时间的缓存数据
     * @param file 文件路径
     * @param flag [0: 获取字符串, 1: json_decode 解码, 2: serialize 解码, 3: php代码, 4: php数组]
     */
    public static function GetExpireFileCache(string $file, int $flag = 2)
    {
        if (!is_file($file)) {
            return null;
        }
        if ((!$h = fopen($file, 'r')) || !flock($h, LOCK_SH)) {
            throw new \Exception('获取文件共享锁失败');
        }
        if (self::CODE_PHP === $flag || self::CODE_PHP_ARR === $flag) {
            is_callable('opcache_invalidate') && opcache_invalidate($file);
            $data = require($file);
        } else {
            $size = filesize($file) - 4;
            if (0 < $size && ($s = fread($h, 4)) && $bs = unpack('N', $s)) {
                $__cache_expire__ = $bs[1];
            }
            if (1 > $size) {
                $data = null;
            } else {
                $str = fread($h, $size);
                $data = match ($flag) {
                    self::CODE_JSON => $str ? json_decode($str, true) : null,
                    self::CODE_SERI => $str ? unserialize($str) : null,
                    default => $str,
                };
            }
        }
        flock($h, LOCK_UN);
        fclose($h);
        return [$data, $__cache_expire__ ?? 0];
    }

    /**
     * 更新有过期时间缓存的部分字段
     * $patch 要更新的数据
     * $nx 缓存不存在时是否写入
     * $timeStamp 缓存的过期时间 (=0 表示没有过期时间, <0 表示不更新过期时间, >0 表示更新过期时间)
     */
    static function PatchExpireFileCache(string $file, array|callable $patch, int $flag = 0, bool $nx = false, int $timeStamp = -1): array|bool|null
    {
        if (!is_file($file)) {
            file_exists($dir = dirname($file)) || MakeDir($dir, 0755, true);
            if (!$nx || (!$h = fopen($file, 'a+'))) {
                return null;
            }
        }
        isset($h) || $h = fopen($file, 'a+');
        if (flock($h, LOCK_EX)) {
            if (is_callable($patch) && !is_array($patch = $patch())){
                throw new Exception('patch数据必须是数组');
            }
            if (!$patch) {
                flock($h, LOCK_UN);
                fclose($h);
                return false;
            }
            clearstatcache(true, $file);
            $size = filesize($file);
            if (!$size || (-1 < $timeStamp && $size < 4)) {
                $data = $patch;
            } else {
                if (self::CODE_PHP === $flag || self::CODE_PHP_ARR === $flag) {
                    is_callable('opcache_invalidate') && opcache_invalidate($file);
                    $res = require($file);
                } else {
                    if (0 > $timeStamp) {
                        $prefix = fread($h, 4);
                        $size -= 4;
                    }
                    $str = fread($h, $size);
                    $res = match ($flag) {
                        self::CODE_JSON => $str ? json_decode($str, true) : null,
                        self::CODE_SERI => $str ? unserialize($str) : null,
                        default => $str,
                    };
                }
                if (is_array($res)) {
                    $data = $res;
                    foreach ($patch as $k=>$v) {
                        if (null === $v) {
                            unset($patch[$k]);
                            if (isset($data[$k])) {
                                unset($data[$k]);
                            }
                        } else {
                            $data[$k] = $v;
                        }
                    }
                } else {
                    $data = $patch;
                }
            }
            ftruncate($h, 0);

            $prefix ?? $prefix = 0 < $timeStamp ? pack('N', $timeStamp) : '';
            $expire = 0 === $timeStamp ? ($__cache_expire__ ?? 0) : $timeStamp;
            $str = match ($flag) {
                self::CODE_JSON => $prefix . json_encode($data, JSON_ENCODE_CFG),
                self::CODE_SERI => $prefix . serialize($data),
                self::CODE_PHP => "<?php\n\$__cache_expire__={$expire};\nreturn " . var_export($data, true) . ';',
                self::CODE_PHP_ARR=>"<?php\n\$__cache_expire__={$expire};\nreturn " . ExportArray($data) . ';',
                default => $prefix . $data,
            };
            $n = fwrite($h, $str);
            flock($h, LOCK_UN);
            fclose($h);
            if (false === $n) {
                throw new \Exception("写入文件失败,请检查权限: {$file}");
            }
            return $n ? $patch : false;
        } else {
            fclose($h);
            throw new \Exception('获取文件锁失败');
        }
    }

    /**
     * 更新无过期时间缓存的部分字段
     * $patch 要更新的数据
     * $nx 缓存不存在时是否写入
     */
    static function PatchFileCache(string $file, array|callable $patch, int $flag = 0, bool $nx = false): array|bool|null
    {
        if (!is_file($file)) {
            file_exists($dir = dirname($file)) || MakeDir($dir, 0755, true);
            if (!$nx || (!$h = fopen($file, 'a+'))) {
                return null;
            }
        }
        isset($h) || $h = fopen($file, 'a+');
        if (flock($h, LOCK_EX)) {
            if (is_callable($patch) && !is_array($patch = $patch())){
                throw new Exception('patch数据必须是数组');
            }
            if (!$patch) {
                flock($h, LOCK_UN);
                fclose($h);
                return false;
            }
            clearstatcache(true, $file);
            $size = filesize($file);
            if (!$size) {
                $data = $patch;
            } else {
                if (self::CODE_PHP === $flag || self::CODE_PHP_ARR === $flag) {
                    is_callable('opcache_invalidate') && opcache_invalidate($file);
                    $res = require($file);
                } else {
                    $str = fread($h, $size);
                    $res = match ($flag) {
                        self::CODE_JSON => $str ? json_decode($str, true) : null,
                        self::CODE_SERI => $str ? unserialize($str) : null,
                        default => $str,
                    };
                }
                if (is_array($res)) {
                    $data = $res;
                    foreach ($patch as $k=>$v) {
                        if (null === $v) {
                            unset($patch[$k]);
                            if (isset($data[$k])) {
                                unset($data[$k]);
                            }
                        } else {
                            $data[$k] = $v;
                        }
                    }
                } else {
                    $data = $patch;
                }
            }
            ftruncate($h, 0);
            $str = match ($flag) {
                self::CODE_JSON => json_encode($data, JSON_ENCODE_CFG),
                self::CODE_SERI => serialize($data),
                self::CODE_PHP => "<?php\nreturn " . var_export($data, true) . ';',
                self::CODE_PHP_ARR=>"<?php\nreturn " . ExportArray($data) . ';',
                default => $prefix . $data,
            };
            $n = fwrite($h, $str);
            flock($h, LOCK_UN);
            fclose($h);
            if (false === $n) {
                throw new \Exception("写入文件失败,请检查权限: {$file}");
            }
            return $n ? $patch : false;
        } else {
            fclose($h);
            throw new \Exception('获取文件锁失败');
        }
    }

    private static function setCacheWindows(string $file, $data, int $flag = 0, int $timeStamp = 0): array
    {
        $h = fopen($file, 'a+');
        if (flock($h, LOCK_EX)) {
            clearstatcache(true, $file);
            $size = filesize($file);
            $prefix = 1 > $timeStamp ? '' :  pack('N', $timeStamp);
            if (!$size || filemtime($file) < TIME) {
                is_callable($data) && $data = $data();
                $str = match ($flag) {
                    self::CODE_JSON => $prefix . json_encode($data, JSON_ENCODE_CFG),
                    self::CODE_SERI => $prefix . serialize($data),
                    self::CODE_PHP => "<?php\n" . (1 > $timeStamp ? '' : "\$__cache_expire__={$timeStamp};\n") . 'return ' . var_export($data, true) . ';',
                    self::CODE_PHP_ARR => "<?php\n" . (1 > $timeStamp ? '' : "\$__cache_expire__={$timeStamp};\n") . 'return ' . ExportArray($data) . ';',
                    default => $prefix . $data,
                };
                ftruncate($h, 0);
                $n = fwrite($h, $str);
                if (false === $n) {
                    flock($h, LOCK_UN);
                    fclose($h);
                    throw new \Exception("写入文件失败,请检查权限: {$file}");
                }
            } elseif (self::CODE_PHP === $flag || self::CODE_PHP_ARR === $flag) {
                is_callable('opcache_invalidate') && opcache_invalidate($file);
                $data = require($file);
            } else {
                $prefix && fseek($h, strlen($prefix));
                $str = fread($h, filesize($file));
                $data = match ($flag) {
                    self::CODE_JSON => $str ? json_decode($str, true) : null,
                    self::CODE_SERI => $str ? unserialize($str) : null,
                    default => $str,
                };
            }
            flock($h, LOCK_UN);
            fclose($h);
            return [$n ?? false, $data];
        } else {
            fclose($h);
            throw new \Exception('获取文件锁失败');
        }
    }

    private static function setCacheLinux(string $file, $data, int $flag, int $timeStamp = -1): array
    {
        if (!$h = fopen($file, 'a+')) {
            throw new \Exception('file can not write: ' . $file);
        }
        $prefix = 1 > $timeStamp ? '' :  pack('N', $timeStamp);
        if (flock($h, LOCK_EX | LOCK_NB)) {
            is_callable($data) && $data = $data();
            $str = match ($flag) {
                self::CODE_JSON => $prefix . json_encode($data, JSON_ENCODE_CFG),
                self::CODE_SERI  => serialize($data),
                self::CODE_PHP => "<?php\n" . (1 > $timeStamp ? '' : "\$__cache_expire__={$timeStamp};\n") . 'return ' . var_export($data, true) . ';',
                self::CODE_PHP_ARR => "<?php\n" . (1 > $timeStamp ? '' : "\$__cache_expire__={$timeStamp};\n") . 'return ' . ExportArray($data) . ';',
                default => $data,
            };
            ftruncate($h, 0);
            $n = fwrite($h, $str);
            flock($h, LOCK_UN);
            fclose($h);
            if (false === $n) {
                throw new \Exception("写入文件失败,请检查权限: {$file}");
            }
            return [$n, $data];
        } elseif (!flock($h, LOCK_SH)) {
            throw new \Exception('获取文件共享锁失败');
        } else {
            if (self::CODE_PHP === $flag || self::CODE_PHP_ARR === $flag) {
                is_callable('opcache_invalidate') && opcache_invalidate($file);
                $data = require($file);
            } else {
                $prefix && fseek($h, strlen($prefix));
                $str = fread($h, filesize($file));
                $data = match ($flag) {
                    self::CODE_JSON => $str ? json_decode($str, true) : null,
                    self::CODE_SERI => $str ? unserialize($str) : null,
                    default => $str,
                };
            }
            flock($h, LOCK_UN);
            return [false, $data];
        }
    }
}
