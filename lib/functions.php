<?php

function IsWeixin (): bool
{
    if (!defined('IS_WX')) {
        define('IS_WX', str_contains($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger'));
    }
    return IS_WX;
}