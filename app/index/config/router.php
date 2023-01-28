<?php
  return [
    'PATH'=>'index.php', //重写入口文件*.php路径
    
    '/'=>[
      'ctrl'=>'index',
      'act'=>'index'
    ],
    '/index'=>[
      'ctrl'=>'index',
      'act'=>'*'
    ],
    '/info'=>[
      'ctrl'=>'index',
      'act'=>'*'
    ],
    '/info/img'=>[
      'ctrl'=>'index',
      'act'=>'aa'
    ],

    '*'=>[
      'ctrl'=>'index',
      'act'=>'_404'
    ]
  ];
