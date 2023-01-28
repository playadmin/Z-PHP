<?php
namespace app\common;
class tags {
    static function size (int $size = 0, int $dec = 2): string
    {
        $unit = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $pos = 0;
        while ($size >= 1024) {
            $size /= 1024;
            ++$pos;
        }
        return round($size, $dec) . $unit[$pos];
    }
}
