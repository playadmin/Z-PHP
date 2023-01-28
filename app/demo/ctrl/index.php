<?php
declare(strict_types=1);
namespace app\ctrl;

use nec\z\view;
use lib\z\{verimg, upload};

class index {
    public static function init()
    {
        $navs = [
            '首页' => '/demo.php',
            '验证码' => '/demo.php/index/vercode',
            '上传' => '/demo.php/index/upload',
            '模型' => '/demo.php/model',
        ];
        view::assign('navs', $navs);
    }

    static function index () {
        $title = '这是 demo应用 => index控制器 => index（）方法';
        $str = str_repeat('balabalabalabalabalabalabala', 10);
        $var = 1234567;
        view::assign('var', $var);
        view::assign('title', $title);
        view::assign('str', $str);
        view::Display();
    }

    public static function vercode()
    {
        $ver = new verimg(100, 38, 4, 12);
        $ver->Img();
    }

    public static function verbase64()
    {
        $title = '使用base64格式的验证码';
        $ver = new verimg(100, 30, 4, 12);
        $base64 = $ver->Base64();
        $code = strtolower($ver->GetCode());
        view::assign('title', $title);
        view::assign('base64', $base64);
        view::assign('code', $code);
        view::display();
    }

    public static function upload()
    {
        if ('POST' === METHOD) {
            $sets = [
                'path'=>P_PUBLIC . 'uploads',
                'maxSize'=>1024 * 1024,
                'allowType'=>['.jpg', '.gif', '.png', '.jpeg'],
            ];
            $up = new upload($sets);
            if ($up->upload()) {
                $info = $up->getInfo(); //返回上传文件信息，索引数组
                P($info);
            }
            if ($err = $up->getError()) {
                P($err);
            }
        } else {
            view::display();
        }
    }
}
