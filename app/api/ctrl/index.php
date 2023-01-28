<?php
declare(strict_types=1);
namespace app\ctrl;

use lib\z\{verimg, out, counter};

class index {
    static function index () {
        $data = [1,2,3,4,5];
        out::Success($data);
    }

    static function vercode()
    {
        $ver = new verimg(100, 30, 4, 12);
        $img = $ver->Base64();
        $code = strtolower($ver->GetCode());
        out::Success(['code'=>$code, 'img'=>$img]);
    }

    // 语言
    static function lang ()
    {
        $lang = GetLang('ERR_PARAMS', 'common', ['$a', '$b']);
        out::Error(400, $lang);
    }

    // 计数器
    static function count ()
    {
        $n = counter::Count('key_1');
        out::Success($n);
    }

    // 数列生成器
    static function generate ()
    {
        $res = counter::Generate('gen_1', 10);
        out::Success($res);
    }

    // 唯一id, 雪花id
    static function series ()
    {
        $res = counter::Series('product_1', 10);
        out::Success($res);
    }
}
