<?php
declare(strict_types=1);

define('LANG_ROOT', P_ROOT . 'lang/');

\z::Hook(\z::BEFORE_START, 'Lang', function () {
    $name = $GLOBALS['ZPHP_CONFIG']['LANG']['name'] ?? 'lang';
    $def = $GLOBALS['ZPHP_CONFIG']['LANG']['default'] ?? 'zh-cn';
    if (!$lang = $_GET[$name] ?? null) {
        if (!$lang = $_SERVER['HTTP_' . strtoupper($name)] ?? null) {
            $lang = $_COOKIE[$name] ?? '';
        }
    }
    if (!$lang) {
        if ($accepts = AcceptLang()) {
            $main = [];
            foreach($accepts as $a) {
                if (file_exists($dir = LANG_ROOT . $a) && ($scan = scandir($dir)) && 2 < count($scan)) {
                    $lang = $a;
                    break;
                }
                if (($s = explode('-', $a)) && 1 < count($s)) {
                    $main[] = $s[0];
                }
            }
            if (!$lang && $main) {
                foreach($main as $a) {
                    if (file_exists($dir = LANG_ROOT . $a) && ($scan = scandir($dir)) && 2 < count($scan)) {
                        $lang = $a;
                        define('LANG_MAIN', $a);
                        break;
                    }
                }
            }
        }
        $lang || $lang = $def;
        $_COOKIE['lang'] = $lang;
        setcookie($name, $lang, 0, '/');
    }
    define('LANG_DEFAULT', $def);
    define('LANG', $lang ?: $def);
    defined('LANG_MAIN') || define('LANG_MAIN', explode('-', LANG)[0]);
});

function GetLang (string $key, string $path = null, array $args = null): string
{
    $path || $path = 'common';
    $map = $GLOBALS['ZPHP_LANG'][$path] ?? null;
    if (null === $map) {
        if (is_file($file = LANG_ROOT . LANG . "/{$path}.php")) {
            $map = (require $file) ?: [];
        } elseif (is_file($file = LANG_ROOT . LANG_MAIN . "/{$path}.php")) {
            $map = (require $file) ?: [];
        } elseif (is_file($file = LANG_ROOT . LANG_DEFAULT . "/{$path}.php")) {
            $map = (require $file) ?: [];
        } else {
            $map = [];
        }
        $GLOBALS['ZPHP_LANG'][$path] = $map;
    }
    if (!$str = $map[$key] ?? '') {
        return $key;
    }
    if (!$args) {
        return $str;
    }
    return preg_replace_callback('/\{\$(\w+)\}/', function ($match) use($args) {
        $k = $match[1] ?? null;
        if (null === $k || '' === $k) {
            return '?Unknown';
        }
        return !isset($args[$k]) ? "?{$k}" : $args[$k];
    }, $str);
}

function Lang (string $key, string $path = null, array $args = null): void
{
    echo GetLang($key, $path, $args);
}

function AcceptLang (): array
{
    $lang = [];
    $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $preg = '/([a-zA-Z-]+)((,\s?[a-zA-Z-]+)+)?\;(q=(0\.\d+|\d+))?/';
    if (preg_match_all($preg, $accept, $match)) {
        $map = [];
        foreach($match[1] as $k=>$v) {
            if (!$n = $match[5][$k] ?? 1) {
                $n = '' === $n ? 1 : 0;
            }
            $map[$n][] = $v;
            if ($ss = $match[2][$k] ?? '') {
                $ss = explode(',', $ss);
                foreach($ss as $s) {
                    if ($s && $s = trim($s)) {
                        $map[$n][] = $s;
                    }
                }
            }
        }
        if ($map) {
            krsort($map, SORT_NUMERIC);
            foreach ($map as $a) {
                array_push($lang, ...$a);
            }
        }
    }
    return $lang;
}
