<?php
declare(strict_types=1);
namespace lib\z;

/**
 * 唯一值需要64位环境, 并且需要GMP数学扩展
 * 可用范围: 未来200多年内可用, 将在 2106-02-07 出现负数, 在 2242-03-16 用尽
 * 要求: 服务器需要开启时间同步 (或者调整时间的跨度 < 极端情况时处理21亿次请求需要的时间)
 * 要求: 重启系统时不可删除锁文件, 锁文件不可存放于内存盘等掉电和重启后会丢失数据的存储器上
 * 极端情况: 256秒内超过 268435455 次请求才可能会出现重复
 */

MakeDir(counter::LOCK_DIR);
class counter {
    const LOCK_DIR = P_LOCK . 'counter/';
    const MAX_RAND_0 = 536870911; //29bit(最大值)
    const MAX_RAND_1 = 268435456; //29bit(中间值)

    /**
     * 计数器, 计数范围(0 - PHP_INT_MAX)
     * $default 默认值 (当缓存不存在或为0时使用默认值)
     * 返回: [原值, 递增后的值]
     */
    static function Count (string $key, int $step = 1, int|callable $default = 0): array|false
    {
        $id = 0;
        MakeDir($lock_dir = self::LOCK_DIR . 'Count');
        $lock_file = "{$lock_dir}/{$key}.{$id}";
        if (!$f = fopen($lock_file, 'w+')) {
            throw new \Exception("创建锁文件失败，请检查权限：{$lock_file}");
        }
        if (!flock($f, LOCK_EX)) {
            fclose($f);
            throw new \Exception('获取文件锁失败');
        }
        $sid = ftok($lock_file, chr($id));

        if ($shmop = shmop_open($sid, 'n', 0755, PHP_INT_SIZE)) {
            $a = is_callable($default) ? $default() : $default;
        } elseif (!$shmop = shmop_open($sid, 'w', 0755, PHP_INT_SIZE)) {
            flock($f, LOCK_UN);
            fclose($f);
            throw new \Exception('打开共享内存失败');
        } else {
            $s = shmop_read($shmop, 0, PHP_INT_SIZE);
            if (false === $s || (!$pack = unpack('I', $s)) || !isset($pack[1])) {
                throw new \Exception('读取共享内存失败');
            }
            if (($pack = unpack('I', $s)) && isset($pack[1])) {
                $a = (int)$pack[1];
            } else {
                $a = is_callable($default) ? $default() : $default;
            }
        }

        $b = $a + $step;
        shmop_write($shmop, pack('I', $b), 0);
        flock($f, LOCK_UN);
        fclose($f);
        return [$a, $b];
    }
    /**
     * 删除计数器
     * shmop_delete 并不会立即回收内存，可能还能读取到，所以同时删除锁文件
     * 这样下次读操作会失败，写操作将会新建存储空间
     */
    static function DelCount (string $key, int $id = 0): bool
    {
        if (!is_file($lock_file = self::LOCK_DIR . "Count/{$key}.{$id}")) {
            return true;
        }
        $sid = ftok($lock_file, chr($id));
        $ret = unlink($lock_file);
        if ($shmop = shmop_open($sid, 'w', 0, 0)) {
            shmop_delete($shmop);
        }
        return $ret;
    }

    /**
     * 读取计数器的值
     */
    static function GetCount (string $key): int|false
    {
        $id = 0;
        if (!is_file($lock_file = self::LOCK_DIR . "Count/{$key}.{$id}")) {
            return false;
        }
        $sid = ftok($lock_file, chr($id));
        if (!$shmop = shmop_open($sid, 'a', 0, 0)) {
            return false;
        }
        $s = shmop_read($shmop, 0, PHP_INT_SIZE);
        if (false === $s || (!$pack = unpack('I', $s)) || !isset($pack[1])) {
            throw new \Exception('读取共享内存失败');
        }
        return (int)$pack[1];
    }
    /**
     * 重设计数器的值
     */
    static function SetCount (string $key, int $val = 0): bool
    {
        $id = 0;
        MakeDir($lock_dir = self::LOCK_DIR . 'Count');
        $lock_file = "{$lock_dir}/{$key}.{$id}";
        if (!$f = fopen($lock_file, 'w+')) {
            throw new \Exception("创建锁文件失败，请检查权限：{$lock_file}");
        }
        $f = fopen($lock_file, 'w+');
        if (!flock($f, LOCK_EX)) {
            fclose($f);
            throw new \Exception('获取文件锁失败');
        }
        $sid = ftok($lock_file, chr($id));
        if (!$shmop = shmop_open($sid, 'c', 0755, PHP_INT_SIZE)) {
            flock($f, LOCK_UN);
            fclose($f);
            throw new \Exception('打开共享内存失败');
        }
        $ret = shmop_write($shmop, pack('I', $val), 0);
        flock($f, LOCK_UN);
        fclose($f);
        return !!$ret;
    }

    /**
     * 删除数列生成的计数器
     * shmop_delete 并不会立即回收内存，可能还能读取到，所以同时删除锁文件
     * 这样下次读操作会失败，写操作将会新建存储空间
     */
    static function DelGenerate (string $key, int $id = 0): bool
    {
        if (!is_file($lock_file = self::LOCK_DIR . "Count/{$key}.{$id}")) {
            return true;
        }
        $sid = ftok($lock_file, chr($id));
        $ret = unlink($lock_file);
        if ($shmop = shmop_open($sid, 'w', 0, 0)) {
            shmop_delete($shmop);
        }
        return $ret;
    }
    /**
     * 获取数列生成器最后的计数值
     * 超过$max时则从0开始
     * $call 为获取初始值的回调函数, 当生成器初始化(值为0)时调用
     */
    static function GetGenerate (string $key, int $max = PHP_INT_MAX, $id = 0): int|false
    {
        if (!is_file($lock_file = self::LOCK_DIR . "Generate/{$key}.{$id}")) {
            return false;
        }
        switch (true) {
            case $max < 256:
                $size = 1;
                $tp = 'C';
                break;
            case $max < 65536:
                $size = 2;
                $tp = 'S';
                break;
            case $max < 4294967296:
                $size = 4;
                $tp = 'L';
                break;
            default:
                $size = 8;
                $tp = 'Q';
        }
        $sid = ftok($lock_file, chr($id));
        if (!$shmop = shmop_open($sid, 'a', 0, 0)) {
            throw new \Exception('打开共享内存失败');
        }
        $s = shmop_read($shmop, 0, $size);
        if (false === $s || (!$pack = unpack($tp, $s)) || !isset($pack[1])) {
            throw new \Exception('读取共享内存失败');
        }
        return (int)$pack[1];
    }
    /**
     * 数列生成器
     * 按$step累加, 超过$max时则从0开始
     * $call 为获取初始值的回调函数, 当生成器初始化(值为0)时调用
     */
    static function Generate (string $key, int $num = 1, int $step = 1, callable $call = null, int $max = PHP_INT_MAX): int|array
    {
        $id = 0;
        if ($max > PHP_INT_MAX) {
            throw new \Exception('max 不能超过' . PHP_INT_MAX);
        }
        MakeDir($lock_dir = self::LOCK_DIR . 'Generate');
        $lock_file = "{$lock_dir}/{$key}.{$id}";
        if (!$f = fopen($lock_file, 'w+')) {
            throw new \Exception("创建锁文件失败，请检查权限：{$lock_file}");
        }
        $f = fopen($lock_file, 'w+');
        if (!flock($f, LOCK_EX)) {
            fclose($f);
            throw new \Exception('获取文件锁失败');
        }
        switch (true) {
            case $max < 256:
                $size = 1;
                $tp = 'C';
                break;
            case $max < 65536:
                $size = 2;
                $tp = 'S';
                break;
            case $max < 4294967296:
                $size = 4;
                $tp = 'L';
                break;
            default:
                $size = 8;
                $tp = 'Q';
        }
        $sid = ftok($lock_file, chr($id));
        if (!$shmop = shmop_open($sid, 'c', 0755, $size)) {
            flock($f, LOCK_UN);
            fclose($f);
            throw new \Exception('打开共享内存失败');
        }
        $s = shmop_read($shmop, 0, $size);
        if (false === $s || (!$pack = unpack($tp, $s)) || !isset($pack[1])) {
            throw new \Exception('读取共享内存失败');
        }
        $n = (int)$pack[1] ?: ($call ? $call() : 0);
        $n += $step;
        if ($n > $max) {
            $n = $step;
        }
        if ($num == 1) {
            $res = $n;
        } else {
            $res[] = $n;
            --$num;
            for ($num; $num; --$num) {
                $n += $step;
                $n > $max && $n = $step;
                $res[] = $n;
            }
        }
        shmop_write($shmop, pack($tp, $n), 0);
        flock($f, LOCK_UN);
        fclose($f);
        return $res;
    }

    /**
     * 获取唯一值生成器最后的计数值
     * $call 为获取初始值的回调函数, 当生成器初始化(值为0)时调用
     */
    static function GetSeries (string $key, $id = 0): int|false
    {
        if (!is_file($lock_file = self::LOCK_DIR . "Series/{$key}.{$id}")) {
            return false;
        }
        $sid = ftok($lock_file, chr($id));
        if (!$shmop = shmop_open($sid, 'a', 0, 0)) {
            throw new \Exception('打开共享内存失败');
        }
        $s = shmop_read($shmop, 0, 4);
        if (false === $s || (!$pack = unpack('L', $s)) || !isset($pack[1])) {
            throw new \Exception('读取共享内存失败');
        }
        return (int)$pack[1];
    }
    /**
     * 删除唯一值生成器
     */
    static function DelSeries (string $key, int $id = 0): bool
    {
        if (!is_file($lock_file = self::LOCK_DIR . "Series/{$key}.{$id}")) {
            return true;
        }
        $sid = ftok($lock_file, chr($id));
        $ret = unlink($lock_file);
        if ($shmop = shmop_open($sid, 'w', 0, 0)) {
            shmop_delete($shmop);
        }
        return $ret;
    }
    /**
     * 唯一值生成器
     * 可能是负数(2242年3月16日起会出现负数)
     * $flag 为区分多台服务器的标记(0 - 1023)
     */
    static function Series (string $key, int $num = 1, int $flag = 0, bool $toInt = true): string|int|array
    {
        $id = 0;
        MakeDir($lock_dir = self::LOCK_DIR . 'Series');
        $lock_file = "{$lock_dir}/{$key}.{$id}";
        if (!$f = fopen($lock_file, 'a+')) {
            throw new \Exception("创建锁文件失败，请检查权限：{$lock_file}");
        }
        if (!flock($f, LOCK_EX)) {
            fclose($f);
            throw new \Exception('获取文件锁失败');
        }
        $sid = ftok($lock_file, chr($id));
        if (!$shmop = shmop_open($sid, 'c', 0755, 4)) {
            flock($f, LOCK_UN);
            fclose($f);
            throw new \Exception('打开共享内存失败');
        }
        $s = shmop_read($shmop, 0, 4);
        if (false === $s || (!$pack = unpack('L', $s)) || !isset($pack[1])) {
            throw new \Exception('读取共享内存失败');
        }
        if (!$n = (int)$pack[1]) {
            //此处防止进程重启后可能出现的重复值
            $r = fread($f, 1);
            if (false === $r) {
                throw new \Exception("读取文件失败请检查权限: {$lock_file}");
            }
            if ($r) {
                if (!ftruncate($f, 0)) {
                    throw new \Exception("写入文件失败请检查权限: {$lock_file}");
                }
                $n = 1;
            } else {
                $n = self::MAX_RAND_1;
            }
        } elseif (self::MAX_RAND_0 < ++$n) {
            $n = 1;
        }
        $time = time();
        if ($num === 1) {
            $res =  self::_uniqId($time, $n, $flag, $toInt);
        } else {
            $res[] = self::_uniqId($time, $n, $flag, $toInt);
            for ($i = 1; $i !== $num; ++$i) {
                if (self::MAX_RAND_0 < ++$n) {
                    $n = 1;
                }
                $res[] = self::_uniqId($time, $n, $flag, $toInt);
            }
        }
        shmop_write($shmop, pack('L', $n), 0);
        $m = $n - self::MAX_RAND_1;
        if ($m > 0 && $m <= $num && !fwrite($f, '1')) {
            //此处防止进程重启后可能出现的重复值
            throw new \Exception("写入文件失败请检查权限: {$lock_file}");
        }
        flock($f, LOCK_UN);
        fclose($f);
        return $res;
    }

    private static function _uniqId (int $time, int $n, int $flag = 0, bool $toInt = false): string|int
    {
        // !!! 256秒内超过 268435455 次请求才可能会出现重复,
        // $time max 8589934591; (2242-03-16 20:56:31)
        // $rand max 536870911; 
        // $time >= 4294967296 (2106-02-07 14:28:16) 时开始出现负数: -9223371487098961921
        if (1023 < $flag || 0 > $flag) {
            throw new \Exception('$flag 范围0 ~ 1023');
        }
        $pt = pack('J', ($time >> 8 << 39));
        $ps = pack('J', $n << 10);
        $pn = pack('J', $flag);
        $d = (string)($pt ^ $ps ^ $pn);
        if (!$toInt) {
            return $d;
        }
        if (!($pack = unpack('J', $d)) || !isset($pack[1])) {
            throw new \Exception('_uniqId编码失败');
        }
        return $pack[1] < 0 ? sprintf('%u', $pack[1]) : $pack[1];
    }
}
