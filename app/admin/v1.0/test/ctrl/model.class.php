<?php
namespace ctrl;

use model\test;
use root\base\ctrl;
use z\view;
// 引入当前应用 model 目录下的 test 类
class model extends ctrl
{
    public static function init()
    {
        $navs = [
            '首页' => '/admin/test/',
            '文档' => '/admin/test/doc',
            '模型' => '/admin/test/model',
        ];
        view::assign('navs', $navs);
    }

    public static function index()
    {
        $title = '这是 admin应用 => test模块 => model控制器 => index（）方法';
        $act = [
            '创建数据表' => '/admin/test/model/create',
            '添加数据' => '/admin/test/model/add',
            '查询一条数据' => '/admin/test/model/find',
            '查询10条数据' => '/admin/test/model/select',
            '分页查询，只返回基本分页数据' => '/admin/test/model/pselect0',
            '分页查询，返回基本分页数据和分页链接' => '/admin/test/model/pselect1',
            '缓存数据查询' => '/admin/test/model/cselect',
        ];
        view::assign('title', $title);
        view::assign('act', $act);
        view::display();
    }

    /**
     * 创建数据表
     */
    public static function create()
    {
        $m = new test;
        $result = $m->CreateTable();
        P($result);
    }

    /**
     * 添加数据
     */
    public static function add()
    {
        $m = new test;
        $result = $m->InsertData();
        P($result);
    }

    /**
     * 查询一条数据
     */
    public static function find()
    {
        $m = new test;
        $result = $m->FindData();
        P($result);
    }

    /**
     * 查询n条数据
     */
    public static function select()
    {
        $m = new test;
        $result = $m->SelectData();
        P($result);
    }

    /**
     * 分页查询，只返回基本分页数据
     */
    public static function pselect0()
    {
        $m = new test;
        $result = $m->PageSelect0();
        P($result);
    }

    /**
     * 分页查询，返回基本分页数据和分页链接
     */
    public static function pselect1()
    {
        $m = new test;
        $result = $m->PageSelect1();
        P($result);
    }

    /**
     * 缓存数据查询
     */
    public static function cselect()
    {
        $m = new test;
        $result = $m->CacheSelect();
        P($result);
    }
}
