<?php

define('APP_NAME', 'api'); /*定义应用目录名称*/
require '../core.php'; /*加载框架*/
AppRun(__DIR__, [
    'nec\z\debug',
    'nec\z\router',
    'nec\z\lang',
]);
