<?php
return [
    // 配置版本号必须在版本目录之外
    'VER' => ['1.0', '1.0'], // [0]:默认版本号:没有请求版本号或找不到请求版本号对应目录的情况下使用此版本号,[1]:强制指定版本号：无视请求版本号，一律使用此版本号
    'DEBUG' => [
        'level' => 3, // 输出全部debug信息
        'type' => 'json', // 输出json格式的debug信息
    ],
    'ROUTER' => [
        'mod' => 2, // 0:queryString，1：pathInfo，2：路由
        'module' => true, // 启用模块模式
        // 'restfull' => [], // restfull模式
    ],
];
