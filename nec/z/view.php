<?php
declare(strict_types=1);

namespace nec\z;

use Exception;
use lib\z\cache;

view::setup();

class view
{
    const
    ENCODE_PREFIX = 'z-php-encode.', ENCODE_END_CHAR = '.',
    OPTIONS = LIBXML_NSCLEAN + LIBXML_PARSEHUGE + LIBXML_NOBLANKS + LIBXML_NOERROR + LIBXML_HTML_NODEFDTD + LIBXML_ERR_FATAL + LIBXML_COMPACT;
    private static \DOMDocument|null $DOM = null;
    private static array $TAG = [], $TPLDOM = [], $PARAMS = [], $REPLACE = [], $PREG_FIX = [], $CACHE = [];
    private static string $PRE = '', $SUF = '', $PREG_CODE = '';
    private static int $CHANGED = 0;

    public static function setup(): void {
        \z::Hook(\z::BEFORE_START, __CLASS__, function () {
            define('THEME', $GLOBALS['ZPHP_CONFIG']['VIEW']['theme'] ?? 'default');
            define('P_VIEW', empty(ROUTE['module']) ? P_APP . 'view/' : P_APP . ROUTE['module'] . '/view/');
            define('P_THEME', P_VIEW . THEME . '/');
            define('P_HTML', P_TMP . 'html/');
            define('P_RUN', P_TMP . 'run/');
        });
    }
    private static function replaceEncode(string $html): string
    {
        $preg = '/\<\w+\s+[^>]*'.self::$PREG_CODE.'[^<]+\>/';
        $pregCode = '/'.self::$PREG_CODE.'/U';
        $html = preg_replace_callback($preg, function (array $match) use($pregCode): string {
            return preg_replace_callback($pregCode, function (array $m2) {
                $code = trim($m2[1]);
                $i = array_push(self::$REPLACE, "<?php echo {$code};?>") - 1;
                return self::ENCODE_PREFIX . $i . self::ENCODE_END_CHAR;
            }, $match[0]);
        }, $html);
        $pregSymbol = '/(\s+[@#&$]+\w+)\s*(?==\s*["\'])/';
        $html = preg_replace_callback($pregSymbol, function (array $m) {
            $i = array_push(self::$REPLACE, $m[1]) - 1;
            return self::ENCODE_PREFIX . $i . self::ENCODE_END_CHAR;
        }, $html);
        return preg_replace(self::$PREG_FIX, ['<?php echo ', ';?>'], $html);
    }
    private static function replaceDecode(string $html): string
    {
        $prefix = preg_quote(self::ENCODE_PREFIX);
        $endchar = preg_quote(self::ENCODE_END_CHAR);
        $html = preg_replace_callback("/{$prefix}(\d+){$endchar}/", function (array $match): string {
            return self::$REPLACE[$match[1]] ?? '';
        }, $html);
        if ($str = preg_replace('/\?>[\s\n]*<\?php\s/', '', $html)) {
            $html = $str;
        }
        if ($str = preg_replace('/endif;[\s\n]*else/', 'else', $html)) {
            $html = $str;
        }
        self::$REPLACE = [];
        return $html;
    }

    public static function GetTpl(string $filename = '', string $parent = ''): string
    {
        if (!$filename) {
            $file = P_THEME . ROUTE['ctrl'] . '/' . ROUTE['act'] . '.html';
        } else {
            $parent && $parent = dirname($parent);
            $info = pathinfo($filename);
            if (IsFullPath($filename)) {
                $dir = $info['dirname'];
            } elseif ($parent && preg_match('/^((\.\.\/)+)(.+)$/', $info['dirname'], $match)) {
                $parr = explode('/', $parent);
                $i = substr_count($match[1], '/');
                while ($i > 0) {
                    array_pop($parr);
                    --$i;
                }
                $dir = implode('/', $parr) . "/{$match[3]}";
            } elseif ($parent && '.' === $info['dirname'][0] && '/' === $info['dirname'][1]) {
                $dir = $parent . substr($info['dirname'], 1);
            } else {
                $arr = explode('/', $info['dirname']);
                if ('.' === $arr[0]) {
                    array_shift($arr);
                }
                if (!count($arr)) {
                    $dir = P_THEME . ROUTE['ctrl'];
                } else {
                    $pre = array_shift($arr);
                    $dir = defined($pre) ? rtrim(constant($pre), '/') : P_THEME . $pre;
                    $dir .= '/' . implode('/', $arr);
                }
            }
            $file = isset($info['extension']) ? "{$dir}/{$info['basename']}" : "{$dir}/{$info['basename']}.html";
        }
        if (!is_file($file)) {
            throw new Exception("file not fond: {$file}");
        }
        return $file;
    }

    private static function getBlock(string $file): \DOMDocument
    {
        $time = filemtime($file);
        $time > self::$CHANGED && self::$CHANGED = $time;
        $html = self::replaceEncode(file_get_contents($file));
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, self::OPTIONS);
        DEBUGER && 2 < $GLOBALS['ZPHP_CONFIG']['DEBUG']['level'] && (DEBUGER)::setMsg(1140, $file);
        $nodes = $dom->getElementsByTagName(self::$TAG['template']);
        $dom->removeChild($dom->firstChild);
        self::$TPLDOM[$file][''] = [$dom, null, $file, ''];
        foreach ($nodes as $v) {
            $name = $v->getAttribute('name');
            self::$TPLDOM[$file][$name][0] = $v;
            if ($params = $v->getAttribute('params')) {
                $params = explode(',', $params);
                foreach($params as $v) {
                    $p = explode(':', $v);
                    $d[$p[0]] = $p[1] ?? null;
                }
                self::$TPLDOM[$file][$name][1] = $d;
            } else {
                self::$TPLDOM[$file][$name][1] = null;
            }
            self::$TPLDOM[$file][$name][2] = $file;
            self::$TPLDOM[$file][$name][3] = $name;
        }
        return $dom;
    }
    private static function setNodes(string $file, string $name, string $key, \DOMNode $parent, \DOMDocument $dom): void
    {
        $root = $parent->parentNode;
        if ($parent->attributes->length > 2 && $attrs = self::setAttrs($parent->attributes, self::$TPLDOM[$key][$name], $file)) {
            $Node = self::$TPLDOM[$key][$name][0]->cloneNode(true);
            $code = implode(';', $attrs) . ';?';
            $new = $dom->createProcessingInstruction('php', $code);
            $root->insertBefore($new, $parent);
        } else {
            $Node = self::$TPLDOM[$key][$name][0];
        }
        $nodes = $Node->childNodes;
        foreach ($nodes as $n) {
            if (self::$TAG['import'] !== $n->nodeName) {
                $new = $dom->importNode($n, true);
                $root->insertBefore($new, $parent);
            }
        }
        $root->removeChild($parent);
    }
    private static function compressHtml(string $html, int $compress): string
    {
        switch ($compress) {
            case 1:
                $preg = '/<!--(?!\[|\<if\s)[\S\s]*-->/U';
                $html = preg_replace($preg, '', $html);
                break;
            case 2:
                $preg = ['/<!--(?!\[|\<if\s)[\S\s]*-->|[\n\r]+/U', '/>\s+</U', '/\s{2,}/'];
                $replace = ['', '><', ' '];
                $html = preg_replace($preg, $replace, $html);
                break;
            default:
                $html = preg_replace('/\s*\n[\s\n]*/', "\n", $html);
        }
        return $html;
    }
    private static function setAttrs(\DOMNamedNodeMap $attrs, ?array $item, string $file): array
    {
        foreach ($attrs as $attr) {
            if (':' === $attr->name[0]) {
                $n = ltrim($attr->name, ':');
                $v = $attr->value ?: $n;
                $key = '$' . $n;
                if (!isset($item[1][$key]) && !isset($item[1][$n])) {
                    // 为方便阅读, 需在被调用的模板中声明传入的参数名
                    throw new Exception("received param {$attr->name} was not specified: {$item[2]}[name={$item[3]}]; from {$file}");
                }
                $sets[] = "{$key} = {$v} ?? null";
            }
        }
        return $sets ?? [];
    }

    private static function replaceTemplate(\DOMDocument $dom, string $file = ''): void
    {
        $imports = $dom->getElementsByTagName(self::$TAG['import']);
        if ($imports->length) {
            $replace = [];
            $sets = [];
            foreach ($imports as $v) {
                $name = $v->getAttribute('name');
                $f = $v->getAttribute('file');
                $tpl = $f ? self::GetTpl($f, $file) : $file;
                $d = isset(self::$TPLDOM[$tpl]) ? $dom : self::getBlock($tpl);
                if (!isset(self::$TPLDOM[$tpl][$name])) {
                    throw new Exception("template tagName '{$name}' not exits : {$tpl}");
                }
                $replace[] = [$d, $tpl];
                $sets[] = [$name, $tpl, $v, $dom];
            }
            foreach($sets as $v) {
                self::setNodes($file, ...$v);
            }
            foreach($replace as $v) {
                self::replaceTemplate(...$v);
            }
        }
    }

    private static function checkTplParams (&$params)
    {
        foreach ($params as &$v) {
            if (is_array($v)) {
                self::checkTplParams($v);
            } elseif (is_string($v)) {
                $v = str_replace(['<?', '?>'], ['&lt;?', '?&gt;'], $v);
            }
        }
    }

    public static function GetRun (string $tpl): array
    {
        $arr = explode('/', $tpl);
        $len = count($arr);
        $name = $arr[$len - 1];
        return [P_RUN . APP_NAME . '/' . THEME, $arr[$len - 2] . '/' . explode('.', $name)[0] . '.php'];
    }
    public static function Fetch(string $filename = '', bool $isDisplay = false): string
    {
        if (self::$PARAMS) {
            $isDisplay || self::checkTplParams(self::$PARAMS);
            extract(self::$PARAMS);
        }
        ob_start() && require self::GetCompile($filename);
        return ob_get_clean();
    }
    public static function GetCompile (string $filename): string
    {
        $tpl = self::GetTpl($filename);
        $run = self::GetRun($tpl);
        $run_file = "{$run[0]}/{$run[1]}";
        $run_time = is_file($run_file) ? filemtime($run_file) : 0;
        if (1 < $GLOBALS['ZPHP_CONFIG']['DEBUG']['level'] || !$run_time) {
            MakeDir($run[0], 0755, true) && self::Compile($tpl, $run_file, $run_time);
        }
        return $run_file;
    }
    public static function Compile(string $tpl, string $run_file, int $run_time): void
    {
        self::$CHANGED = filemtime($tpl);
        DEBUGER && 2 < $GLOBALS['ZPHP_CONFIG']['DEBUG']['level'] && (DEBUGER)::setMsg(1140, $tpl);
        self::$TAG || self::$TAG = [
            'php' => $GLOBALS['ZPHP_CONFIG']['VIEW']['php_tag'] ?? 'php',
            'custom' => $GLOBALS['ZPHP_CONFIG']['VIEW']['custom_tags'] ?? null,
            'import' => $GLOBALS['ZPHP_CONFIG']['VIEW']['import_tag'] ?? 'import',
            'template' => $GLOBALS['ZPHP_CONFIG']['VIEW']['template_tag'] ?? 'template',
        ];
        self::$PRE = $GLOBALS['ZPHP_CONFIG']['VIEW']['prefix'] ?? '<{';
        self::$SUF = $GLOBALS['ZPHP_CONFIG']['VIEW']['suffix'] ?? '}>';
        $pregPre = preg_quote(self::$PRE);
        $pregSuf = preg_quote(self::$SUF);
        self::$PREG_CODE = "{$pregPre}([\s\S]+){$pregSuf}";
        self::$PREG_FIX = ["/\s*{$pregPre}\s*/", "/\s*{$pregSuf}\s*/"];

        $flag = '<meta flag="ZPHP-UTF-8" http-equiv="Content-Type" content="text/html; charset=utf-8">';
        $html = self::replaceEncode(file_get_contents($tpl));
        $html = '<!DOCTYPE html>' . $flag . $html;
        self::$DOM = new \DOMDocument('1.0', 'UTF-8');
        self::$DOM->loadHTML($html, self::OPTIONS);
        self::replaceTemplate(self::$DOM, $tpl);
        if (self::$CHANGED > $run_time) {
            cache::SetFileCache($run_file, function () use ($flag): string {
                if ($compress = $GLOBALS['ZPHP_CONFIG']['VIEW']['compress'] ?? 0) {
                    self::compressCss($compress[1] ?? $compress);
                    self::compressJavaScript($compress[2] ?? $compress);
                }
                self::replacePhpTags();
                if (self::$TAG['custom']) {
                    foreach(self::$TAG['custom'] as $name=>$cfg) {
                        self::replaceCustomTags($name, $cfg);
                    }
                }
                $html = self::$DOM->saveHTML();
                $html = self::replaceDecode($html);
                $html = self::compressHtml($html, $compress[0] ?? $compress);
                return str_replace($flag, '', $html);
            });
        }
        self::$TPLDOM = [];
    }

    public static function GetCache(int $time, string $name = '', int $flag = 0): string | false
    {
        $tpl = self::GetTpl($name);
        $file = self::getCacheFile($flag, $tpl);
        $html_time = is_file($file) ? filemtime($file) : 0;
        if ($html_time && $html_time + $time >= TIME) {
            if ((!$h = fopen($file, 'r')) || !flock($h, LOCK_SH)) {
                throw new \Exception('获取文件共享锁失败');
            }
            $cache = fread($h, filesize($file));
            flock($h, LOCK_UN);
            return $cache;
        }
        self::$CACHE = [$tpl, $file];
        return false;
    }

    private static function compressJavaScript(int $compress): void
    {
        switch ($compress) {
            case 1:
                $preg = '/\/\*[\s\S]*\*\/|(?<!:|"|\')\/\/.*[\r\n]/U';
                $replace = '';
                break;
            case 2:
                $preg = ['/\/\*[\s\S]*\*\/|(?<!:|"|\')\/\/.*[\r\n]|[\n\r]+/U', '/\s*([\,\;\:\{\}\[\]\(\)\=])\s*/', '/\s{2,}/'];
                $replace = ['', '$1', ' '];
                break;
            default:
                return;
        }
        $tags = self::$DOM->getElementsByTagName('script');
        if ($tags->length) {
            foreach ($tags as $v) {
                $v->textContent = preg_replace($preg, $replace, $v->textContent);
            }
        }
    }
    private static function compressCss(int $compress): void
    {
        switch ($compress) {
            case 1:
                $preg = '/\/\*[\s\S]*\*\//U';
                $replace = '';
                break;
            case 2:
                $preg = ['/\/\*[\s\S]*\*\/|[\n\r]+/U', '/\s*([\,\;\:\{\}\[\]\(\)\=])\s*/', '/\s{2,}/'];
                $replace = ['', '$1', ' '];
                break;
            default:
                return;
        }
        $tags = self::$DOM->getElementsByTagName('style');
        if ($tags->length) {
            foreach ($tags as $v) {
                $v->textContent = preg_replace($preg, $replace, $v->textContent);
            }
        }
    }

    private static function replacePhpTags(): void
    {
        $tags = self::$DOM->getElementsByTagName(self::$TAG['php']);
        for ($i = $tags->length - 1; 0 <= $i; --$i) {
            $t = $tags[$i];
            $parent = $t->parentNode;
            if ($t->attributes->length) {
                $a = $t->attributes[0];
                switch ($a->name) {
                    case 'default':
                        $code = 'default:?';
                        $dd = '';
                        break;
                    case 'break':
                    case 'continue':
                        $code = '';
                        $dd = isset($a->value) ? "{$a->name} {$a->value};?" : "{$a->name};?";
                        break;
                    case 'case':
                        $dd = 'break;?';
                        $code = 'default' === $a->value ? 'default:?' : "case {$a->value}:?";
                        if (
                            $t->hasAttribute('break') &&
                            '' !== ($av = strtolower($t->getAttribute('break'))) &&
                            ($av === 'false' || $av === '0' || $av === 'no')
                        ) {
                            $dd = '';
                        }
                        break;
                    default:
                        $code = '' === $a->value ? "{$a->name}:?" : "{$a->name}({$a->value}):?";
                        $dd = 0 === strpos($a->name, 'else') ? 'endif;?' : "end{$a->name};?";
                }
                if ($code) {
                    $new = self::$DOM->createProcessingInstruction('php', $code);
                    $parent->insertBefore($new, $t);
                }
                foreach ($t->childNodes as $c) {
                    $new = $c->cloneNode(true);
                    $parent->insertBefore($new, $t);
                }
                if ($dd) {
                    $new = self::$DOM->createProcessingInstruction('php', $dd);
                    $parent->insertBefore($new, $t);
                }
            } else {
                $new = self::$DOM->createProcessingInstruction('php', "{$t->nodeValue};?");
                $parent->insertBefore($new, $t);
            }
            $parent->removeChild($t);
        }
    }

    private static function replaceCustomTags(string $name, array $cfg): void
    {
        $tags = self::$DOM->getElementsByTagName($name);
        for ($i = $tags->length - 1; 0 <= $i; --$i) {
            // if (!$act = $cfg[1] ?? null) {
            //     throw new Exception("Custom tag required a method name on parameter 2");
            // }
            $act = $cfg[1] ?? 'fn';
            if ($class = $cfg[0] ?? null) {
                $call = "{$class}::{$act}";
            } else {
                $call = $act;
            }
            $t = $tags[$i];
            $args = [];
            $attrs = [];
            if ($t->attributes->length) {
                foreach($t->attributes as $k=>$v) {
                    if ($k && ':' === $k[0]) {
                        $args[substr($k, 1)] = $v->value;
                    } else {
                        $args[$k] = is_string($v->value) ? "'" . str_replace("'", "\'", $v->value) . "'" : $v->value;
                        $attrs[$k] = $v->value;
                    }
                }
            }

            if (!empty($cfg[2])) {
                if (isset($args['var'])) {
                    $var = $args['var'] ?: '$var';
                    unset($args['var']);
                } else {
                    $var = '$var';
                }
                $code = str_contains($var, ',') ? "list({$var})=" : "{$var}=";
                $code .= $call . '(' . ExportArray($args) . ');?';
            } else {
                list($pre, $dd) = $call($attrs, $args);
                $code = $pre . '?';
                $dd .= '?';
            }

            $parent = $t->parentNode;
            $new = self::$DOM->createProcessingInstruction('php', $code);
            $parent->insertBefore($new, $t);
            foreach ($t->childNodes as $c) {
                $new = $c->cloneNode(true);
                $parent->insertBefore($new, $t);
            }
            if (!empty($dd)) {
                $new = self::$DOM->createProcessingInstruction('php', $dd);
                $parent->insertBefore($new, $t);
            }
            $parent->removeChild($t);
        }
    }

    private static function getCacheFile($flag, string $tpl): string
    {
        $arr = explode('/', $tpl);
        $len = count($arr);
        $name = $arr[$len - 1];
        $html_path = P_HTML . APP_NAME . '/' . THEME . '/' . $arr[$len - 2] . '/';

        if (!$flag) {
            $file = explode('.', $name)[0] . '.html';
            $html_file = $html_path . $file;
        } else {
            if (is_array($flag)) {
                $i = 0;
                foreach ($flag as $k=>$v) {
                    if ($k === $i++) {
                        $query[$v] = ROUTE['query'][$v] ?? '';
                    } else {
                        $query[$k] = $v;
                    }
                }
            } else {
                $query = ROUTE['query'];
            }
            $html_file = "{$html_path}/" . md5($name . serialize($query)) . '.html';
        }
        return $html_file;
    }
    public static function Display(string $name = ''): void
    {
        if (self::$CACHE) {
            $ret = cache::SetFileCache(self::$CACHE[1], function (): string {
                if (self::$PARAMS) {
                    self::checkTplParams(self::$PARAMS);
                    extract(self::$PARAMS);
                }
                ob_start() && require self::GetCompile(self::$CACHE[0]);
                return ob_get_clean();
            });
            echo $ret[1];
        } else {
            self::$PARAMS && extract(self::$PARAMS);
            require self::GetCompile($name);
        }
    }
    public static function Assign(string $key, $val): void
    {
        self::$PARAMS[$key] = $val;
    }
    public static function GetParams(): array
    {
        return self::$PARAMS;
    }
}
