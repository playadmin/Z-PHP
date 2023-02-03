<?php
namespace model;

class testc
{
    static function testa($attrs)
    {
        $cat = $attrs['cat'] ?? 1;
        $num = $attrs['num'] ?? 10;
        $type = $attrs['type'] ?? '';
        if ($cat > 10 || $type !== 'news') {
            return [];
        }
        $res = [];
        for ($i = 0; $i < $num; ++$i) {
            $res[] = $i;
        }
        return $res;
    }

    static function testb($attrs)
    {
        if (!$num = (int)$attrs['num'] ?? 0) {
            return [];
        }
        $num > 100 && $num = 100;
        $res = [];
        for ($i = 0; $i < $num; ++$i) {
            $res[] = $i * 10;
        }
        return $res;
    }
}
