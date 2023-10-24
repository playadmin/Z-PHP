<?php
declare(strict_types=1);

namespace nec\z;

router::setup();

class router
{
    private static int $MOD = 0;
    private static bool $IS_MODULE = false;
    private static array $ROUTER = [];
    private static array $FORMAT = [];
    private static array $APP_ISMODULE = [];
    private static array $APP_MAP = [];

    public static function setup(): void {
        $class = __CLASS__;
        define('ROUTER', $class);
        \z::Hook(\z::BEFORE_ROUTER, $class, function(): void
        {
            self::$IS_MODULE = !empty($GLOBALS['ZPHP_CONFIG']['ROUTER']['module']);
            self::$MOD = $GLOBALS['ZPHP_CONFIG']['ROUTER']['mod'] ?? -1;
            $pathinfo = self::getPathInfo();
            switch (self::$MOD) {
                case 0:
                    $route = self::defaultRoute();
                    break;
                case 1:
                    $route = self::pathinfoRoute($pathinfo);
                    break;
                case 2:
                case 3:
                    if (!$router = self::router()) {
                        if (self::$IS_MODULE) {
                            $router = [];
                        } else {
                            throw new \Exception('没有找到路由配置');
                        }
                    }
                    $route = self::route($pathinfo, $router);
                    break;
                default:
                    if ($router = self::router()) {
                        self::$MOD = 2;
                        $route = self::route($pathinfo, $router);
                    } elseif ($pathinfo) {
                        self::$MOD = 1;
                        $route = self::pathinfoRoute($pathinfo);
                    } else {
                        self::$MOD = 0;
                        $route = self::defaultRoute();
                    }
                    break;
            }
            $route['query'] = isset($route['params']) ? $route['params'] + $_GET : $_GET;
            $route['uri'] = $_SERVER['REQUEST_URI'];
            $route['app'] = APP_NAME;
            $route['mod'] = self::$MOD;
            define('ROUTE', $route);
        });
    }

    private static function getPathInfo(): string
    {
        if (isset($_SERVER['DOCUMENT_URI'])) {
            $pathinfo = substr($_SERVER['DOCUMENT_URI'], strlen($_SERVER['SCRIPT_NAME']));
        } else {
            $pathinfo = $_SERVER['PATH_INFO'] ?? $_SERVER['REDIRECT_PATH_INFO'] ?? '';
        }
        $pathinfo && $pathinfo = trim($pathinfo, '/');
        return $pathinfo;
    }
    private static function router(string $name = ''): array
    {
        $name || $name = APP_NAME;
        $path = P_ROOT . "app/{$name}/";
        if(!$router = is_file($file = "{$path}config/router.php") ? require $file : []) return [];
        isset($router['PATH']) && $router['PATH'] = trim($router['PATH'], '/');
        self::$ROUTER[$name] = $router;
        return $router;
    }
    private static function getAppName(string $php): string
    {
        if (isset(self::$APP_MAP[$php])) {
            return self::$APP_MAP[$php];
        }
        if (is_file($file = P_IN . $php) && $str = file_get_contents($file)) {
            $preg = '/define.+\,\s*\'(\w+)\'\s*\)/';
            preg_match($preg, $str, $match);
            self::$APP_MAP[$php] = $match[1] ?? false;
        }
        return self::$APP_MAP[$php];
    }
    private static function getIsmodule(string $app): bool
    {
        if ($app === APP_NAME) {
            return self::$IS_MODULE;
        }
        if (!isset(self::$APP_ISMODULE[$app])) {
            if (is_file($file = P_ROOT . "app/{$app}/config.php") && $config = require $file) {
                $ismodule = $config['ROUTER']['module'] ?? null;
            }
            if (!isset($ismodule) && is_file($file = P_ROOT . "app/{$app}/config.php") && $config = require $file) {
                $ismodule = $config['ROUTER']['module'] ?? null;
            }
            if (!isset($ismodule) && is_file($file = P_ROOT . 'config/config.php') && $config = require $file) {
                $ismodule = $config['ROUTER']['module'] ?? false;
            }
            self::$APP_ISMODULE[$app] = $ismodule;
        }
        return self::$APP_ISMODULE[$app];
    }
    private static function getModuleRouter(string $m, string $name = ''):array
    {
        $name || $name = APP_NAME;
        $M = "+{$m}";
        if (isset(self::$ROUTER[$name][$M])) {
            return self::$ROUTER[$name][$M];
        }
        $module = P_ROOT . "app/{$name}/{$m}/";
        $router = is_file($file = "{$module}config/router.php") ? require $file : false;
        if (isset(self::$ROUTER[$name][$m]) && is_array(self::$ROUTER[$name][$m])) {
            $router = $router ? $router + self::$ROUTER[$name][$m] : self::$ROUTER[$name][$m];
        }
        empty(self::$ROUTER[$name]) ? self::$ROUTER[$name] = [$M => $router] : self::$ROUTER[$name][$M] = $router;
        return $router;
    }
    private static function format(string $name, string $m): array
    {
        $name || $name = APP_NAME;
        $key = "{$name}-{$m}";

        if (isset(self::$FORMAT[$key])) {
            return self::$FORMAT[$key];
        }

        if (!$router = $m ? self::getModuleRouter($m, $name) : (self::$ROUTER["{$name}"] ?? self::router($name))) {
            $data = [];
        } else {
            if (isset($router['PATH'])) {
                $data[0] = $router['PATH'] ?: '';
                unset($router['PATH']);
            } else {
                $data[0] = '';
            }
            foreach ($router as $k => $v) {
                if ('*' === $k || '/' !== $k[0]) {
                    continue;
                }

                $ctrl = $v['ctrl'] ?? 'index';
                $act = $v['act'] ?? 'index';
                $a = str_replace('*', '', $act);
                $d = [$k, $v['params'] ?? false];
                if ($a !== $act) {
                    $data[$ctrl]['*'][$a] = $d;
                } else {
                    $data[$ctrl][$act] = $d;
                }
            }
        }
        self::$FORMAT[$key] = $data;
        return $data;
    }
    private static function getUf(string $path): array
    {
        if (!$path || '/' === $path) {
            self::$IS_MODULE && $uf['m'] = ROUTE['module'];
            $uf['c'] = ROUTE['ctrl'];
            $uf['a'] = 'index';
            return $uf;
        }
        $arr = explode('/', $path);
        if ('.php' === substr($arr[0], -4)) {
            $uf['app'][0] = $arr[0];
            $uf['app'][1] = self::getAppName($uf['app'][0]);
            if (self::getIsmodule($uf['app'][1])) {
                if (4 !== count($arr)) {
                    throw new \Exception('RUL(参数错误)，格式："入口文件名/模块名/控制器/操作"');
                }
                $uf['m'] = $arr[1];
                $uf['c'] = $arr[2];
                $uf['a'] = $arr[3];
            } else {
                if (3 !== count($arr)) {
                    throw new \Exception('URL(参数错误)，格式："入口文件名/控制器/操作"');
                }
                $uf['c'] = $arr[1];
                $uf['a'] = $arr[2];
            }
        } elseif (self::$IS_MODULE) {
            switch (count($arr)) {
                case 1:
                    $uf['m'] = ROUTE['module'];
                    $uf['c'] = ROUTE['ctrl'];
                    $uf['a'] = $arr[0];
                    break;
                case 2:
                    $uf['m'] = ROUTE['module'];
                    $uf['c'] = $arr[0];
                    $uf['a'] = $arr[1];
                    break;
                case 3:
                    $uf['m'] = $arr[0];
                    $uf['c'] = $arr[1];
                    $uf['a'] = $arr[2];
                    break;
            }
        } else {
            switch (count($arr)) {
                case 1:
                    $uf['c'] = ROUTE['ctrl'];
                    $uf['a'] = $arr[0];
                    break;
                case 2:
                    $uf['c'] = $arr[0];
                    $uf['a'] = $arr[1];
                    break;
            }
        }
        return $uf;
    }
    private static function getInPath(array $info, bool $param = false): string
    {
        if (isset($info['app'])) {
            $php = $info['app'][0];
            $app = $info['app'][1];
        } else {
            $php = PHP_FILE;
            $app = APP_NAME;
        }
        $m = $info['m'] ?? ROUTE['module'] ?? false;
        if ($route = self::format($app, $m)) {
            $url = isset($route[0]) ? U_HOME . $route[0] : U_ROOT;
        } else {
            $url = !$param && 'index.php' === $php ? U_ROOT : U_HOME . $php;
        }
        return $url;
    }
    public static function U0(string $path, array $args = null): string
    {
        $Q = self::getUf($path);
        $url = self::getInPath($Q);

        unset($Q['app']);
        if ('index' === $Q['m']) {
            unset($Q['m']);
        }
        if ('index' === $Q['c']) {
            unset($Q['c']);
        }

        if ('index' === $Q['a']) {
            unset($Q['a']);
        }
        if ($args) {
            empty($args['params']) || $Q += $args['params'];
            empty($args['query']) || $Q += $args['query'];
            if (!isset($args['params']) && !isset($args['query'])) {
                $Q += $args;
            }
        }
        $Q && $url .= '?' . http_build_query($Q);
        return $url;
    }

    public static function U1(string $path, array $args = null): string
    {
        $info = self::getUf($path);
        $url = self::getInPath($info, !empty($args['params']));
        $m = isset($info['m']) ? "/{$info['m']}" : '';
        if (empty($args['params'])) {
            if ('index' !== $info['a']) {
                $url .= "{$m}/{$info['c']}/{$info['a']}";
            } elseif ('index' !== $info['c']) {
                $url .= "{$m}/{$info['c']}";
            } elseif ($m && '/index' !== $m) {
                $url .= $m;
            }
        } else {
            $url .= "{$m}/{$info['c']}/{$info['a']}";
            foreach ($args['params'] as $k => $v) {
                $url .= "/{$k}/{$v}";
            }
        }
        empty($args['query']) || $url .= '?' . http_build_query($args['query']);
        return $url;
    }

    public static function U2(string $path, array $args = null): string
    {
        $info = self::getUf($path);
        $app = $info['app'][1] ?? APP_NAME;
        $m = $info['m'] ?? '';
        $c = $info['c'];
        $a = $info['a'];
        if (!$data = self::format($app, $m)) {
            throw new \Exception("没有配置路由：{$app}");
        }
        $url = $data[0] ? U_HOME . $data[0] : U_ROOT;
        $url .= $m ? ($data[0] ? "{$data[0]}/{$m}" : $m) : $data[0];
        if (isset($data[$c][$a])) {
            $route = $data[$c][$a];
        } elseif (isset($data[$c]['*'])) {
            foreach ($data[$c]['*'] as $k => $v) {
                if ('' !== $k && str_contains($a, $k)) {
                    $route = $v;
                    $a = str_replace($k, '', $a);
                    break;
                }
            }
            $route ?? $route = $data[$c]['*'][''] ?? null;
            $route && $route[0] .= '/' . $a;
        } else {
            throw new \Exception("没有匹配到路由，[ctrl：{$c}，act：{$a}]");
        }
        if (isset($route)) {
            $route[0] && $url .= $route[0];
            if (isset($args['params']) && $route[1]) {
                $i = 0;
                foreach ($route[1] as $k => $v) {
                    if ($k === $i) {
                        ++$i;
                        $key = $v;
                    } else {
                        $key = $k;
                    }
                    if (isset($args['params'][$key])) {
                        $params[] = $args['params'][$key];
                        unset($args['params'][$key]);
                    }
                }
            }
        }

        $query = $args['params'] ?? [];
        empty($args['query']) || $query += $args['query'];
        isset($params) && $url .= '/' . implode('/', $params);
        $query && $url .= '?' . http_build_query($query);
        return $url;
    }

    public static function Url(string $path, array $args = null, $mod = -1): string
    {
        0 > $mod && $mod = $GLOBALS['ZPHP_CONFIG']['ROUTER']['mod'] ?? self::$MOD;

        switch ($mod) {
            case 0:
                $url = self::U0($path, $args);
                break;
            case 1:
                $url = self::U1($path, $args);
                break;
            case 2:
                $url = self::U2($path, $args);
                break;
            default:
                throw new \Exception('url参数4错误');
        }
        return $url;
    }
    private static function defaultRoute(): array
    {
        self::$IS_MODULE && $route['module'] = $_GET['m'] ?: 'index';
        if (isset($_GET['c'])) {
            $route['ctrl'] = $_GET['c'] ?: 'index';
            unset($_GET['c']);
        } else {
            $route['ctrl'] = 'index';
        }
        if (!empty($GLOBALS['ZPHP_CONFIG']['ROUTER']['restfull'])) {
            $act = strtolower($_SERVER['REQUEST_METHOD']);
            $route['act'] = $GLOBALS['ZPHP_CONFIG']['ROUTER']['restfull'][$act] ?? $act;
        } elseif (isset($_GET['a'])) {
            $route['act'] = $_GET['a'] ?: 'index';
            unset($_GET['a']);
        } else {
            $route['act'] = 'index';
        }
        return $route;
    }
    private static function pathinfo2arr(string $pathinfo): array
    {
        $params = $pathinfo ? explode('/', $pathinfo) : ['index'];
        self::$IS_MODULE && $info['module'] = array_shift($params);
        $info['ctrl'] = $params ? array_shift($params) : 'index';
        if (!empty($GLOBALS['ZPHP_CONFIG']['ROUTER']['restfull']) && $act = strtolower($_SERVER['REQUEST_METHOD'])) {
            $act = $GLOBALS['ZPHP_CONFIG']['ROUTER']['restfull'][$act] ?? $act;
        }
        return [$info, $params, $act ?? false];
    }
    private static function pathinfoRoute(string $pathinfo): array
    {
        list($route, $params, $act) = self::pathinfo2arr($pathinfo);
        $route['act'] = $act ?: ($params ? array_shift($params) : 'index');
        $route['path'] = $params;
        $route['params'] = [];
        if ($params && $params = array_chunk($params, 2)) {
            foreach ($params as $v) {
                $route['params'][$v[0]] = $v[1] ?? '';
            }
        }
        return $route;
    }
    private static function route(string $pathinfo, array $router): array
    {
        list($info, $arr, $act) = self::pathinfo2arr($pathinfo);
        if (isset($info['module']) && !$router = self::getModuleRouter($info['module'])) {
            throw new \Exception("没有{$info['module']}模块的路由");
        }
        if ($act && isset($router["/{$info['ctrl']}/{$act}"])) {
            $route = $router["/{$info['ctrl']}/{$act}"];
        } elseif (isset($arr[0]) && isset($router["/{$info['ctrl']}/{$arr[0]}"])) {
            $act = array_shift($arr);
            $route = $router["/{$info['ctrl']}/{$act}"];
        } elseif (!$route = $router["/{$info['ctrl']}/*"] ?? $router["/{$info['ctrl']}"] ?? false) {
            if (!$route = 'index' === $info['ctrl'] && !$arr ? $router['/'] ?? false : $router['*'] ?? false) {
                throw new \Exception('没有匹配到路由, 不想看到此错误请配置 * 路由');
            }
        }
        if (empty($route['ctrl']) || empty($route['act'])) {
            throw new \Exception('必须设置路由的 ctrl 和 act');
        } elseif (str_contains($route['act'], '*') && $replace = array_shift($arr) ?: 'index') {
            $route['act'] = str_replace('*', $replace, $route['act']);
        }
        isset($route['module']) || isset($info['module']) && $route['module'] = $info['module'];
        if (isset($route['params'])) {
            $ii = 0;
            $n = 0;
            $ii = 0;
            foreach ($route['params'] as $k => $v) {
                if ($ii === $k) {
                    $key = $v;
                    $value = '';
                    ++$ii;
                } else {
                    $key = $k;
                    $value = $v;
                }
                $params[$key] = isset($arr[$n]) ? $arr[$n] : $value;
                ++$n;
            }
        }
        $route['params'] = $params ?? [];
        $route['path'] = $arr;
        return $route;
    }
}
