<?php
return [
    'PATH' => 'admin', //此处是url的根路径，前后不带"/"，index可省略留空
    '/' => [
        'ctrl' => 'index',
        'act' => 'index',
    ],
    '/index' => [
        'ctrl' => 'index',
        'act' => '*',
    ],
    '*' => [ // 以上匹配不到时使用此路由
        'ctrl' => 'index',
        'act' => '_404',
    ],
];
