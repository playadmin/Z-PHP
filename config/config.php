<?php
declare(strict_types=1);
// 全局配置
return [
    'DEBUG' => [
        'level' => 3,
        'type' => 'auto',
        'log' => 1,
        // 'ip'=>'127.0.0.1', // 可查看debug信息的ip白名单(字符串，数组，正则表达式)
    ],
    'ROUTER' => [
        'mod' => 1, //0:queryString，1：pathInfo，2：路由
        'module' => 0,
    ],
    'SESSION' => [
        'name' => 'SID', //session 名
        'auto' => true, //自动开启 session
        'redis' => false,
        'host' => '',
        'port' => '',
        'pass' => '',
    ],
    'DB' => [
        'dsn' => 'mysql:host=127.0.0.1;dbname=test;port=3306',
        'db' => 'test',
        'user' => 'root',
        'pass' => 'root',
        'charset' => 'utf8mb4',
        'prefix' => 'z_',
    ],
    'VIEW' => [
        'prefix'=>'[#',
        'suffix'=>'#]',
        'custom_tags' => [
            'loop' => ['Loop'],
        ],
    ],
    'REDIS' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'pass' => '',
        'db' => 0,
        'timeout' => 1,
    ],
    'LANG'=>[
        'name'=>'lang',
        'default'=>'en-US',
    ],
];
