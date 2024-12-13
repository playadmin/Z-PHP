<?php
declare(strict_types=1);

function AppRun(string $entry, array $nec = null): void
{
    define('FTIME', microtime(true));
    define('TIME', (int)FTIME);
    define('STIME', (int)(1000 * FTIME));
    define('ZPHP_VER', '5.1.0');
    define('FILE_CORE', str_replace('\\', '/', __FILE__));
    $php = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
    define('PHP_FILE', array_pop($php));
    define('U_ROOT', $php ? '/' . implode('/', $php) : '');
    define('U_HOME', U_ROOT . '/');
    define('METHOD', $_SERVER['REQUEST_METHOD']);
    define('P_IN', str_replace('\\', '/', $entry) . '/');
    define('P_ROOT', str_replace('\\', '/', __DIR__) . '/');
    define('P_APP', P_ROOT . 'app/' . APP_NAME . '/');
    define('P_TMP', P_ROOT . 'tmp/');
    define('P_CACHE', P_TMP . 'cache/');
    define('P_LOCK', P_TMP . 'locks/');
    define('LEN_IN', strlen(P_IN));
    define('JSON_ENCODE_CFG', JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    if (P_IN === P_ROOT) {
        define('P_PUBLIC', P_IN . 'public/');
        define('U_PUBLIC', U_HOME . 'public');
    } else {
        define('P_PUBLIC', P_IN);
        define('U_PUBLIC', U_ROOT);
    }
    define('U_TMP', U_PUBLIC . '/tmp');
    define('P_RES', P_PUBLIC . 'res/');
    define('P_RES_APP', P_PUBLIC . 'res/' . APP_NAME . '/');
    define('U_RES', U_PUBLIC . '/res');
    define('U_RES_APP', U_RES . '/' . APP_NAME);
    spl_autoload_register('z::AutoLoad');
    set_error_handler('z::errorHandler');
    set_exception_handler('z::exceptionHandler');
    $GLOBALS['ZPHP_MAPPING'] = [
        'nec' => P_ROOT . 'nec/',
        'lib' => P_ROOT . 'lib/',
        'model' => P_ROOT . 'model/',
        'sdk' => P_ROOT . 'sdk/',
        'app' => P_APP,
    ];
    if ($nec) {
        foreach($nec as $v) {
            z::AutoLoad($v);
        }
    }
    z::CallHooks(z::BEFORE_CONFIG);
    z::LoadConfig();
    z::CallHooks(z::BEFORE_ROUTER);
    defined('ROUTER') || define('ROUTER', null);
    defined('DEBUGER') || define('DEBUGER', null);
    ini_set('date.timezone', $GLOBALS['ZPHP_CONFIG']['TIME_ZONE'] ?? 'Asia/Shanghai');
    isset($GLOBALS['ZPHP_CONFIG']['DEBUG']['level']) || $GLOBALS['ZPHP_CONFIG']['DEBUG']['level'] = 3;

    if (!defined('ROUTE')) {
        $router = [
            'mod'=>0,
            'ctrl' => empty($_GET['c']) ? 'index' : $_GET['c'],
            'act' => empty($_GET['a']) ? 'index' : $_GET['a'],
            'uri' => $_SERVER['REQUEST_URI'],
            'query'=> $_GET,
        ];
        empty($GLOBALS['ZPHP_CONFIG']['ROUTER']['module']) || $router['module'] = empty($_GET['m']) ? 'index' : $_GET['m'];
        define('ROUTE', $router);
    }
    if (isset(ROUTE['module'])) {
        define('P_MODULE', P_APP . ROUTE['module'] . '/');
        define('P_RES_MODULE', P_RES_APP . ROUTE['module'] . '/');
        define('U_RES_MODULE', U_RES_APP . '/' . ROUTE['module']);
    }
    if ($GLOBALS['ZPHP_CONFIG']['DEBUG']['level'] > 1) {
        error_reporting(E_ALL);
    } else {
        ini_set('expose_php', 'Off');
        error_reporting(0);
    }
    z::start();
}
function IsWindows (): bool
{
    if (!defined('IS_WINDOWS')) {
        define('IS_WINDOWS', str_starts_with(PHP_OS, 'WIN'));
    }
    return IS_WINDOWS;
}
function IsAjax (): bool
{
    if (!defined('IS_AJAX')) {
        define('IS_AJAX', isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 'xmlhttprequest' === strtolower($_SERVER['HTTP_X_REQUESTED_WITH']));
    }
    return IS_AJAX;
}
function IsFullPath(string $path): bool
{
    return IsWindows() ? ':' === $path[1] : '/' === $path[0];
}
function Zautoload(callable $act): void
{
    $GLOBALS['ZPHP_AUTOLOAD'] = $act;
}
function Debug(int $i, string $type = ''): void
{
    $GLOBALS['ZPHP_CONFIG']['DEBUG']['level'] = $i;
    $type && $GLOBALS['ZPHP_CONFIG']['DEBUG']['type'] = $type;
}
function SetConfig(string $key, $value): void
{
    if (isset($GLOBALS['ZPHP_CONFIG'][$key]) && is_array($value)) {
        $GLOBALS['ZPHP_CONFIG'][$key] = $value + $GLOBALS['ZPHP_CONFIG'][$key];
    } else {
        $GLOBALS['ZPHP_CONFIG'][$key] = $value;
    }
}
function GetIp(bool $int = false): int|string|bool
{
    $ip = empty($_SERVER['HTTP_CLIENT_IP']) ? '' : $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(', ', $_SERVER['HTTP_X_FORWARDED_FOR']);
        if ($ip) {
            array_unshift($ips, $ip);
            $ip = '';
        }
        $count = count($ips);
        for ($i = 0; $i !== $count; ++$i) {
            if (!preg_match('/^(10│172.16│192.168)./', $ips[$i])) {
                $ip = $ips[$i];
                break;
            }
        }
    }
    $ip = $ip ?: $_SERVER['REMOTE_ADDR'];
    return $int ? (int)sprintf('%u', ip2long($ip)) : $ip;
}
function ArrayIsList (&$arr) {
    if (is_callable('array_is_list')) {
        return array_is_list($arr);
    }
    $isList = true;
    foreach(array_keys($arr) as $k=>$v) {
        if ($k !== $v) {
            $isList = false;
            break;
        }
    }
    return $isList;
}
function ExportArray (array $arr, bool $escape = false, string $indent = '', $prefix = '', $indentNum = 0, $forceStringValue = true) {
    if (!$arr) {
        return '[]';
    }

    if ($escape) {
        $search = ["'", "\n","\t","\r","\f","\\","\v"];
        $replace = ["\'", '\n','\t','\r','\f','\\','\v'];
    } else {
        $search = "'";
        $replace = "\'";
    }

    if (ArrayIsList($arr)) {
        foreach($arr as $v) {
            if (is_array($v)) {
                $slice[] = ExportArray($v, $escape, $indent, $prefix, 1 + $indentNum, $forceStringValue);
            } elseif (null === $v) {
                $slice[] = 'null';
            } elseif (true === $v) {
                $slice[] = 'true';
            } elseif (false === $v) {
                $slice[] = 'false';
            } else {
                ($forceStringValue || is_string($v)) && $v = "'" . str_replace($search, $replace, (string)$v) . "'";
                $slice[] = $v;
            }
        }
    } else {
        foreach($arr as $k=>$v) {
            if (is_string($k)) {
                $k = str_replace($search, $replace, $k);
                $key = "'{$k}'=>";
            } else {
                $key = "{$k}=>";
            }
            if (is_array($v)) {
                $slice[] = $key . ExportArray($v, $escape, $indent, $prefix, 1 + $indentNum, $forceStringValue);
            } elseif (null === $v) {
                $slice[] = "{$key}null";
            } elseif (true === $v) {
                $slice[] = "{$key}true";
            } elseif (false === $v) {
                $slice[] = "{$key}false";
            } else {
                ($forceStringValue || is_string($v)) && $v = "'" . str_replace($search, $replace, (string)$v) . "'";
                $slice[] = $key . $v;
            }
        }
    }
    if ($indent) {
        $pre = $prefix . str_repeat($indent, $indentNum);
        return "[\n{$pre}{$indent}" . implode(",\n{$pre}{$indent}", $slice) . "\n{$pre}]";
    } else {
        return '[' . implode(',', $slice) . ']';
    }
}
function P($var, bool $echo = true): string
{
    ob_start();
    var_dump($var);
    $html = preg_replace('/\]\=\>\n(\s+)/m', '] =>', htmlspecialchars_decode(ob_get_clean()));
    if ($echo) {
        echo "<pre>{$html}</pre>";
    }
    return $html;
}
function FileSizeFormat(int $size = 0, int $dec = 2): string
{
    $unit = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $pos = 0;
    while ($size >= 1024) {
        $size /= 1024;
        ++$pos;
    }
    return round($size, $dec) . $unit[$pos];
}

function MakeDir(string $dir, int $mode = 0755, bool $recursive = true): bool
{
    if (!file_exists($dir) && !mkdir($dir, $mode, $recursive)) {
        throw new Error("创建目录{$dir}失败,请检查权限");
    }
    return true;
}
function DelDir($dir, $rmdir = false, $i = 0): int|false
{
    if (file_exists($dir) && $h = opendir($dir)) {
        while (false !== ($item = readdir($h))) {
            if ('.' !== $item && '..' !== $item) {
                if (is_dir($dir . '/' . $item)) {
                    $i += DelDir($dir . '/' . $item, $rmdir);
                } elseif (unlink($dir . '/' . $item)) {
                    ++$i;
                }
            }
        }
        closedir($h);
    } else {
        return false;
    }

    $rmdir && rmdir($dir) && ++$i;
    return $i;
}

function Page($cfg, $return = false): array
{
    $var = $cfg['var'] ?? 'p';
    $data['total'] = $cfg['total'] ?? 0;
    $data['num'] = ($cfg['num'] ?? 10);
    $data['p'] = $cfg['p'] ?? (isset($_GET[$var]) ? (int) $_GET[$var] : 1);
    $data['p'] || $data['p'] = 1;
    if (isset($cfg['max'])) {
        $maxRows = $data['num'] * $cfg['max'];
        if ($maxRows < $data['total']) {
            $data['total'] = $maxRows;
            $data['p'] > $cfg['max'] && $data['p'] = $cfg['max'];
        }
    }
    $data['pages'] = $data['total'] ? (int) ceil($data['total'] / $data['num']) : 1;
    $inrange = $cfg['inrange'] ?? true;
    $inrange && $data['pages'] < $data['p'] && $data['p'] = $data['pages'];
    $start = ($data['p'] - 1) * $data['num'];
    $data['limit'] = "{$start},{$data['num']}";
    if (!$return) {
        return $data;
    }
    switch ($data['pages'] <=> $data['p']) {
        case -1:
            $data['rows'] = 0;
            break;
        case 0:
            $data['rows'] = $data['total'] % $data['num'] ?: ($data['total'] ? $data['num'] : 0);
            break;
        case 1:
            $data['rows'] = $data['num'];
            break;
    }
    if (ROUTER && is_array($return)) {
        $p = $data['p'];
        $var = $cfg['var'] ?? 'p';
        $mod = $cfg['mod'] ?? null;
        $nourl = $cfg['nourl'] ?? 'javascript:;';
        $params = ROUTE['params'] ?? [];
        $query = $_GET;
        foreach ($return as $v) {
            switch ($v) {
                case 'prev':
                    $params[$var] = $p - 1;
                    $data['prev'] = $params[$var] && $p !== $params[$var] ? (ROUTER)::url([ROUTE['ctrl'], ROUTE['act']], ['params' => $params, 'query' => $query], $mod) : $nourl;
                    break;
                case 'next':
                    $params[$var] = $p + 1;
                    $data['next'] = $data['pages'] > $p ? (ROUTER)::url([ROUTE['ctrl'], ROUTE['act']], ['params' => $params, 'query' => $query], $mod) : $nourl;
                    break;
                case 'first':
                    $params[$var] = 1;
                    $data['first'] = 1 === $p || 1 === $data['pages'] ? $nourl : (ROUTER)::url([ROUTE['ctrl'], ROUTE['act']], ['params' => $params, 'query' => $query], $mod);
                    break;
                case 'last':
                    $params[$var] = $data['pages'];
                    $data['last'] = 1 === $data['pages'] || $data['pages'] === $p ? $nourl : (ROUTER)::url([ROUTE['ctrl'], ROUTE['act']], ['params' => $params, 'query' => $query], $mod);
                    break;
                case 'list':
                    (int) $rolls = $cfg['rolls'] ?? 10;
                    if (1 < $data['pages']) {
                        $pos = intval($rolls / 2);
                        if ($pos < $p && $data['pages'] > $rolls) {
                            $i = $p - $pos;
                            $end = $i + $rolls - 1;
                            $end > $data['pages'] && ($end = $data['pages']) && ($i = $end - $rolls + 1);
                        } else {
                            $i = 1;
                            $end = $rolls > $data['pages'] ? $data['pages'] : $rolls;
                        }
                        for ($i; $i <= $end; $i++) {
                            $params[$var] = $i;
                            $data['list'][$i] = $p == $i ? 'javascript:;' : (ROUTER)::url([ROUTE['ctrl'], ROUTE['act']], ['params' => $params, 'query' => $query], $mod);
                        }
                    } else {
                        $data['list'] = [];
                    }
                    break;
            }
        }
    }
    return $data;
}

class z
{
    const BEFORE_ROUTER = -120;
    const BEFORE_CONFIG = -110;
    const BEFORE_START = -100;
    const BEFORE_CTRL_INIT = -90;
    const BEFORE_CTRL_ACTION = -80;
    const BEFORE_EXIT = -70;

    private static array $HOOKS = [];
    public static function start(): void
    {
        self::loadMapping();
        self::loadFunctions();
        self::setSession();
        self::setInput();
        headers_sent() || header('Content-type: text/html; charset=utf-8');
        header('X-Powered-By: ' . ($GLOBALS['ZPHP_CONFIG']['POWEREDBY'] ?? 'ZPHP-MIN'));
        $ctrl = isset(ROUTE['module']) ? 'app\\' . ROUTE['module'] . '\\ctrl\\' . ROUTE['ctrl'] : 'app\\ctrl\\' . ROUTE['ctrl'];
        $act = (string)ROUTE['act'];

        self::CallHooks(self::BEFORE_START);
        if (!class_exists($ctrl)) {
            $GLOBALS['ZPHP_CONFIG']['DEBUG']['level'] < 2 && self::_404();
        }
        self::CallHooks(self::BEFORE_CTRL_INIT);
        method_exists($ctrl, 'init') && $ctrl::init();
        self::CallHooks(self::BEFORE_CTRL_ACTION);
        $result = $ctrl::$act();
        method_exists($ctrl, 'after') && $ctrl::after();
        self::CallHooks(self::BEFORE_EXIT);
        if (isset($result)) {
            die(self::json($result));
        }
        DEBUGER && (DEBUGER)::ShowMsg();
        die;
    }
    public static function Hook (int|string $flag, string $class, callable $call): void
    {
        self::$HOOKS[$flag][] = [$call, $class];
    }
    public static function CallHooks (int|string $flag): void
    {
        if (isset(self::$HOOKS[$flag])) {
            foreach (self::$HOOKS[$flag] as $item) {
                $item[0]();
                $item[1] && self::CallHooks($item[1]);
            }
            unset(self::$HOOKS[$flag]);
        }
    }

    public static function json($data): void
    {
        ob_end_clean();
        header('Content-Type:application/json; charset=utf-8');
        die(json_encode($data, JSON_ENCODE_CFG));
    }
    public static function _404(): void
    {
        header('Status: 404');
        die('<h1 style="text-align:center;padding:1rem 0;">404</h1>');
    }
    public static function _500(): void
    {
        header('Status: 500');
        die('<h1 style="text-align:center;padding:1rem 0;">500</h1>');
    }
    private static function setSession(): void
    {
        if (!isset($GLOBALS['ZPHP_CONFIG']['SESSION']['auto']) || $GLOBALS['ZPHP_CONFIG']['SESSION']['auto']) {
            self::SessionStart();
        }
    }
    public static function SessionStart(): void
    {
        if (!empty($GLOBALS['ZPHP_CONFIG']['SESSION']['name'])) {
            $org = session_name($GLOBALS['ZPHP_CONFIG']['SESSION']['name']);
            isset($_COOKIE[$org]) && setcookie($org, '', 0, '/');
        }
        if (!empty($GLOBALS['ZPHP_CONFIG']['SESSION']['httponly'])) {
            ini_set('session.cookie_httponly', 'true');
        }
        if (!empty($GLOBALS['ZPHP_CONFIG']['SESSION']['redis'])) {
            $cfg = empty($GLOBALS['ZPHP_CONFIG']['SESSION']['host']) ? $GLOBALS['ZPHP_CONFIG']['REDIS'] : $GLOBALS['ZPHP_CONFIG']['SESSION'];
            $database = $GLOBALS['ZPHP_CONFIG']['SESSION']['database'] ?? 1;
            $session_path = "tcp://{$cfg['host']}:{$cfg['port']}?database={$database}";
            empty($cfg['pass']) || $session_path .= "&auth={$cfg['pass']}";
            ini_set('session.save_handler', 'redis');
            ini_set('session.save_path', $session_path);
        }
        session_start();
    }
    public static function AutoLoad(string $r): void
    {
        if (str_contains($r, '\\')) {
            $path_arr = explode('\\', $r);
            $path_root = array_shift($path_arr);
            if (!$path = $GLOBALS['ZPHP_MAPPING'][$path_root] ?? null) {
                if (($fn = $GLOBALS['ZPHP_AUTOLOAD'] ?? null) && is_callable($fn)) {
                    $fn($r);
                } else {
                    throw new \Exception("Namespace: {$path_root} is not mapped");
                }
            }
            $fileName = array_pop($path_arr);
            $path .= $path_arr ? implode('/', $path_arr) . '/' : '';
            if (is_file($file = "{$path}{$fileName}.php")) {
                require $file;
            } else {
                throw new \Exception("file not fond: {$path}{$fileName}.php");
            }
        } else {
            empty($GLOBALS['ZPHP_AUTOLOAD']) || $GLOBALS['ZPHP_AUTOLOAD']($r);
        }
    }
    public static function LoadConfig(string $file = ''): void
    {
        if ($file) {
            is_file($file) && is_array($conf = require $file) && $GLOBALS['ZPHP_CONFIG'] = $conf + $GLOBALS['ZPHP_CONFIG'];
        } else {
            $GLOBALS['ZPHP_CONFIG'] = is_file($file = P_APP . 'config/config.php') && is_array($conf = require $file) ? $conf : [];
            is_file($file = P_APP . 'config.php') && is_array($conf = require $file) && $GLOBALS['ZPHP_CONFIG'] += $conf;
            is_file($file = P_ROOT . 'config/config.php') && is_array($conf = require $file) && $GLOBALS['ZPHP_CONFIG'] += $conf;
        }
    }
    public static function loadFunctions(): void
    {
        is_file($file = P_ROOT . 'lib/functions.php') && require $file;
        is_file($file = P_APP . 'lib/functions.php') && require $file;
    }
    public static function GetConfig(string $key = '')
    {
        return $key ? $GLOBALS['ZPHP_CONFIG'][$key] : $GLOBALS['ZPHP_CONFIG'];
    }
    private static function loadMapping(): void
    {
        is_file($file = P_APP . 'config/mapping.php') && is_array($map = require $file) && $GLOBALS['ZPHP_MAPPING'] += $map;
        is_file($file = P_ROOT . 'config/mapping.php') && is_array($map = require $file) && $GLOBALS['ZPHP_MAPPING'] += $map;
    }
    private static function setInput(): void
    {
        if (!empty($_SERVER['CONTENT_TYPE']) && $arr = explode(';', $_SERVER['CONTENT_TYPE'])) {
            $tp = [];
            $type = explode('/', $arr[0]);
            empty($arr[1]) || parse_str($arr[1], $tp);
            $tp && $type = array_merge($type, $tp);
            if (isset($type[1]) && 'application' === $type[0]) {
                if ('POST' === METHOD) {
                    if ('json' === $type[1] && $d = file_get_contents('php://input')) {
                        $_POST = json_decode($d, true);
                    }
                } elseif ('GET' !== METHOD) {
                    if ($raw = file_get_contents('php://input')) {
                        switch ($type[1]) {
                            case 'json':
                                $I = json_decode($raw, true);
                            break;
                            case 'xml':
                                $I = (array)simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA);
                            break;
                            case 'x-www-form-urlencoded':
                                parse_str($raw, $I);
                            break;
                        }
                    }
                }
            }
        }
        define('RAW', $raw ?? null);
        define('INPUT', $I ?? null);
        define('CONTENT_TYPE', $type ?? []);
    }

    public static function exceptionHandler(Error|Exception $e): void
    {
        if (DEBUGER) {
            (DEBUGER)::exceptionHandler($e);
        } else {
            $GLOBALS['ZPHP_CONFIG']['DEBUG']['level'] > 1 || z::_500();
            $line = $e->getLine();
            $file = $e->getFile();
            $msg = $e->getMessage() . " at [{$file} : {$line}]";
            $trace = $e->getTraceAsString();
            $trace = str_replace('\\\\', '\\', $trace);
            echo "<style>body{margin:0;padding:0;}</style><div style='background:#FFBBDD;padding:1rem;'><h2>ERROR!</h2><h3>{$msg}</h3>";
            echo '<strong><pre>' . $trace . '</pre></strong>';
            foreach ($e->getTrace() as $k => $v) {
                isset($v['args']) && $args["#{$k}"] = 1 === count($v['args']) ? $v['args'][0] : $v['args'];
            }
            if (isset($args)) {
                echo '<h3>参数：</h3>';
                P($args);
            }
            echo '</div>';
            die;
        }
    }
    public static function errorHandler(int $errno, string $errstr, string $errfile, int $errline): void
    {
        if (DEBUGER) {
            (DEBUGER)::errorHandler($errno, $errstr, $errfile, $errline);
        } elseif ($GLOBALS['ZPHP_CONFIG']['DEBUG']['level'] > 2) {
            $str = "<div style='background:#ccc;padding:1rem;width:100%;'><strong>Warning({$errno}): {$errstr} [{$errfile}: {$errline}]</strong></div>";
            echo $str;
        }
    }
}