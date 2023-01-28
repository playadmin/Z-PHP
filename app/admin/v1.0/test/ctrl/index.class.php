<?php
namespace ctrl;

use root\base\ctrl;
use z\view;

class index extends ctrl
{
    public static function init()
    {
        $navs = [
            '首页' => '/admin.php/test/',
            '文档' => '/admin.php/test/doc',
            '模型' => '/admin.php/test/model',
        ];
        view::assign('navs', $navs);
    }

    public static function index()
    {
        $title = '这是 admin应用 => test模块 => index控制器 => index（）方法';
        $str = str_repeat('balabalabalabalabalabalabala', 10);
        view::assign('title', $title);
        view::assign('str', $str);
        view::display();
    }

    public static function document()
    {
        echo '<h1>这是 admin应用 => test模块 => index控制器 => document（） 方法</h1>';
    }
}
