<?php
declare(strict_types=1);
namespace app\ctrl;

use model\test;
use lib\z\{verimg, out, counter};

class index {
    static function index () {
        echo 'index';
    }

    static function aa()
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
}
