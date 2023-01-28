<?php
namespace ctrl;

use root\base\ctrl;
use z\view;

class index extends ctrl
{
    public static function init()
    {
        $navs = [
            '首页' => '/',
            '验证码' => '/demo/ver',
            '上传' => '/demo/index/upload',
            '模型' => '/demo/model',
        ];
        view::assign('navs', $navs);
    }

    public static function index()
    {
        $title = '这是 demo应用 => index控制器 => index（）方法';
        $str = str_repeat('balabalabalabalabalabalabala', 10);
        view::assign('title', $title);
        view::assign('str', $str);
        view::display();
    }
    public static function vercode()
    {
        $ver = new \ext\verimg(100, 30, 4, 12);
        $ver->Img();
    }
    public static function verbase64()
    {
        $title = '使用base64格式的验证码';
        $ver = new \ext\verimg(100, 30, 4, 12);
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
            $path = P_PUBLIC . 'uploads';
            $up = new \ext\upload();
            $up->set('path', $path); //定义文件上传路径
            $up->set('allowType', ['.jpg', '.gif', '.png', '.jpeg']); //定义允许上传的文件后缀
            $up->set('maxSize', 1024 * 1024); //定义允许上传的最大尺寸
            $result = $up->upload(); //执行上传
            $info = $up->getInfo(); //返回上传文件信息，索引数组
            $err = $up->getError();
            P($info);
            P($err);
        } else {
            view::display();
        }
    }
}
