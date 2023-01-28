<?php
return [
    'PATH' => 'demo', //此处是url的根路径，前后不带"/"，index可省略留空
    '/' => [
        'ctrl' => 'index',
        'act' => 'index',
    ],
    '/ver' => [
        'ctrl' => 'index',
        'act' => 'verbase64',
    ],
    '/vercode' => [
        'ctrl' => 'index',
        'act' => 'vercode',
    ],
    '/index' => [
        'ctrl' => 'index',
        'act' => '*', //*会替换为 pathinfo 的 action
    ],
    '/model' => [
        'ctrl' => 'model',
        'act' => '*', //*会替换为 pathinfo 的 action
        'params' => ['p'],
    ],
    '*' => [ // 以上匹配不到时使用此路由
        'ctrl' => 'index',
        'act' => '_404',
    ],
];
