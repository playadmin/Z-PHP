<?php
declare(strict_types=1);
namespace lib\z;

use Exception;

class input {
    const NONE = 0,
    LT = -1, // 小于
    GT = 1, // 大于
    LTEQ = 2, // 小于等于
    GTEQ = 3, // 大于等于
    LT_GT = 4, GT_LT= 5, // 小于_大于, 大于_小于
    LTEQ_GT = 5, GTEQ_LT= 6, // 小于等于_大于, 大于等于_小于
    LT_GTEQ = 7, GT_LTEQ= 8, // 小于_大于等于, 大于_小于等于
    BETWEEN = 9, // 小于等于 大于等于
    EQ = 10, // 等于
    NEQ = 11, // 不等于

    LEN = 101, // 字符串长度
    MBLEN = 102,  // utf8编码的字符串的长度
    NOT_NULL = 103, // 不能是空字符

    BOOL = 111,
    NUMBER = 112,
    INT = 113,
    FLOAT = 114,

    CTRLS = 121, // 控制字符
    SPECIAL = 122, // 特殊字符('"&<>)
    SYMBOLS = 123, // 标点符号
    BASE = 124, // 基础字符(0-9, A-Z, a-z, _)
    NORMAL = 125, // 基础字符和标点符号
    LETTER = 126, //字母
    VAR = 127, // 字母开头的基础字符
    NOT_CTRLS = -121,
    NOT_SPECIAL = -122,
    NOT_SYMBOLS = -123,
    NOT_BASE = -124,
    NOT_NORMAL = -125,
    NOT_LETTER = -126,
    NOT_VAR = -127,

    STR = 131, // 常用字符(3字节的utf8字符范围)
    STRMB4 = 132, // 所有字符(除控制字符之外的所有字符)
    WORDS = 133, // 除标点符号外的常用字符
    WORDSMB4 = 134, // 除标点符号和控制字符外的所有字符
    MB4 = 135,  // 4字节编码的字符
    ZH = 141, // 汉字
    NOT_STR = -131,
    NOT_STRMB4 = -132,
    NOT_WORDS = -133,
    NOT_WORDSMB4 = -134,
    NOT_MB4 = -135,
    NOT_ZH = -141,

    TEL = 201, // 电话
    PHONE = 202, // 手机号
    INTE_PHONE = 203, // 带区号的手机号
    EMAIL = 204, // 电子邮件
    URL = 205, // URL地址
    AB_URL = 206, // URL地址(绝对)
    LOCAL_URL = 207, // URL地址(相对)
    IP = 208, // IP地址(IPv4和IPv6)
    IPV4 = 209, // IPv4地址
    IPV6 = 210,  // IPv6地址
    TIME = 211;

    const ENCODES = ['<'=>'&lt;', '>'=>'&gt;', '&'=>'&amp;', '"'=>'&quot;', '\''=>'&apos;'];
    const PREG_LETTER = 'A-Za-z';
    const PREG_BASE = '0-9A-Za-z_';
    const PREG_NORMAL = '0-9A-Za-z\x{21}-\x{2F}\x{3A}-\x{40}\x{5B}-\x{5E}\x{7B}-\x{7E}`';
    const PREG_CTRLS = '\x{00}-\x{08}\x{0E}-\x{7F}';
    const PREG_SPECIAL = '\<\>\'\"&';
    const PREG_SYMBOLS = '\x{21}-\x{2F}\x{3A}-\x{40}\x{5B}-\x{5E}\x{7B}-\x{7E}`';
    const PREG_STR = '\x{21}-\x{FFFF}';
    const PREG_STRMB4 = '\x{21}-\x{10FFFF}';
    const PREG_WORDS = '0-9A-Za-z_\x{80}-\x{FFFF}';
    const PREG_WORDSMB4 = '0-9A-Za-z_\x{80}-\x{10FFFF}';
    const PREG_ZH = '\x{4E00}-\x{9FFF}\x{3400}-\x{4DFF}';
    const PREG_MB4 = '\x{10000}-\x{10FFFF}';
    const PREG_VAR = '[A-Za-z_][0-9A-Za-z_]*';
    const PREG_EMAIL = '[\w-]+@(\w+\.)+\w+';
    const PREG_URL = '(\w+\:\/)?\/.+';
    const PREG_ABURL = '(\w+:)?\/\/.+';
    const PREG_LOCAL_URL = '\/.+';
    const PREG_TEL = '(\+?\d{2,4}[-\s]?)?(\d{2,4}[-\s]?){0,2}\d{4,9}';
    const PREG_PHONE = '1[3-9]\d{9}';
    const PREG_INTE_PHONE = '(\+?\d{2,4}[-\s]?)?\d{7,11}';
    const PREG_IPV4 = '((2(5[0-5]|[0-4]\d))|[0-1]?\d{1,2})(\.((2(5[0-5]|[0-4]\d))|[0-1]?\d{1,2})){3}';
    const PREG_TIME = '([0-1]?[0-9]|2[0-3])(:[0-5]?[0-9]?){1,2}';

    public static function Len (string $str, int $min = null, int $max = null): bool
    {
        if (null === $min && null === $max) {
            throw new Exception('字符长度参数错误');
        }
        $len = strlen($str);

        return null !== $min && null !== $max ? $min <= $len && $max >= $len : (null !== $min ? $min <= $len : $max >= $len);
    }
    public static function MbLen (string $str, int $min = 0, int $max = 0): bool
    {
        if (null === $min && null === $max) {
            throw new Exception('字符长度参数错误');
        }
        $len = mb_strlen($str);
        return null !== $min && null !== $max ? $min <= $len && $max >= $len : (null !== $min ? $min <= $len : $max >= $len);
    }

    public static function Valid (int|float|string|array &$s, int|string|callable $flag, array $args = []): bool
    {
        if (is_array($s)) {
            foreach ($s as $v) {
                if (!self::Valid($v, $flag, $args)) {
                    return false;
                }
            }
            return true;
        }
        if (is_callable($flag)) {
            return $flag($s, $args);
        }
        return match($flag) {
            self::LT => is_numeric($s) && $s < $args[0],
            self::GT => is_numeric($s) && $s > $args[0],
            self::LTEQ => is_numeric($s) && $s <= $args[0],
            self::GTEQ => is_numeric($s) && $s >= $args[0],
            self::EQ => is_numeric($s) && $s === $args[0],
            self::NEQ => is_numeric($s) && $s !== $args[0],
            self::LT_GT => is_numeric($s) && $s < $args[0] && $s > $args[1],
            self::GT_LT => is_numeric($s) && $s > $args[0] && $s < $args[1],
            self::GTEQ_LT => is_numeric($s) && $s >= $args[0] && $s < $args[1],
            self::GT_LTEQ => is_numeric($s) && $s > $args[0] && $s <= $args[1],
            self::LT_GTEQ => is_numeric($s) && $s < $args[0] && $s >= $args[1],
            self::LTEQ_GT => is_numeric($s) && $s <= $args[0] && $s > $args[1],
            self::BETWEEN => is_numeric($s) && $s >= $args[0] && $s <= $args[1],
            self::LEN => self::Len($s, ...$args),
            self::MBLEN => self::MbLen($s, ...$args),
            self::NOT_NULL => !!$s,

            self::NUMBER => is_numeric($s),
            self::BOOL => self::Vbool($s),
            self::INT => self::Vint($s, $args),
            self::FLOAT => self::Vfloat($s, $args),
            default => self::Is($s, $flag, ...$args),
        };
    }
    public static function Vbool (string &$s): bool
    {
        if (!$s || null === ($a = filter_var($s, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE))) {
            return false;
        }
        $s = $a;
        return true;
    }
    public static function Vfloat (string &$s, array $args = []): bool
    {
        $options = [];
        if ($args) {
            isset($args[0]) && $options['options']['min_range'] = $args[0];
            isset($args[1]) && $options['options']['max_range'] = $args[1];
        }
        $a = filter_var($s, FILTER_VALIDATE_FLOAT, $options);
        if ($ret = false !== $a && null !== $a) {
            $s = $a;
        }
        return $ret;
    }
    public static function Vint (string &$s, array $args = []): bool
    {
        $options = [];
        if ($args) {
            isset($args[0]) && $options['options']['min_range'] = $args[0];
            isset($args[1]) && $options['options']['max_range'] = $args[1];
        }
        $a = filter_var($s, FILTER_VALIDATE_INT, $options);
        if ($ret = false !== $a && null !== $a) {
            $s = $a;
        }
        return $ret;
    }

    /**
     * 过滤器
     * $str 要过滤的字符
     * $flag 过滤标记或是正则表达式(匹配要保留的字符)
     * $others 指定需要额外保留(NOT_xx 额外过滤)的字符(正则表达式的字符格式)
     * $encodes 指定需要编码的字符
     */
    public static function Filter (string $str, int|string $flag = 0, string $others = '', array|bool $encodes = null): string
    {
        $preg = is_int($flag) ? match($flag) {
            self::NONE => false,

            self::NOT_MB4 => '/[' . self::PREG_MB4 . "{$others}]/u",
            self::NOT_CTRLS => '/[' . self::PREG_CTRLS . "{$others}]/u",
            self::NOT_SPECIAL => '/[' . self::PREG_SPECIAL . "{$others}]/",
            self::NOT_BASE => '/[' . self::PREG_BASE . "{$others}]/",
            self::NOT_LETTER => '/[' . self::PREG_LETTER . "{$others}]/",
            self::NOT_NORMAL => '/[' . self::PREG_NORMAL . "{$others}]/u",
            self::NOT_SYMBOLS => '/[' . self::PREG_SYMBOLS . "{$others}]/u",
            self::NOT_STR => '/[' . self::PREG_STR . "{$others}]/u",
            self::NOT_STRMB4 => '/[' . self::PREG_STRMB4 . "{$others}]/u",
            self::NOT_WORDS => '/[' . self::PREG_WORDS . "{$others}]/u",
            self::NOT_WORDSMB4 => '/[' . self::PREG_WORDSMB4 . "{$others}]/u",
            self::NOT_ZH => '/[' . self::PREG_ZH . "{$others}]/u",

            self::MB4 => '/[^' . self::PREG_MB4 . "{$others}]/u",
            self::CTRLS => '/[^' . self::PREG_CTRLS . "{$others}]/u",
            self::SPECIAL => '/[^' . self::PREG_SPECIAL . "{$others}]/",
            self::BASE => '/[^' . self::PREG_BASE . "{$others}]/",
            self::LETTER => '/[^' . self::PREG_LETTER . "{$others}]/",
            self::NORMAL => '/[^' . self::PREG_NORMAL . "{$others}]/u",
            self::SYMBOLS => '/[^' . self::PREG_SYMBOLS . "{$others}]/u",
            self::STR => '/[^' . self::PREG_STR . "{$others}]/u",
            self::STRMB4 => '/[^' . self::PREG_STRMB4 . "{$others}]/u",
            self::WORDS => '/[^' . self::PREG_WORDS . "{$others}]/u",
            self::WORDSMB4 => '/[^' . self::PREG_WORDSMB4 . "{$others}]/u",
            self::ZH => '/[^' . self::PREG_ZH . "{$others}]/u",
            default => throw new Exception('不能识别的过滤标记'),
        } : $flag;

        $preg && $str = preg_replace($preg, '', $str);
        if (null === $str) {
            throw new Exception('额外保留字符错误');
        }

        if ($encodes || !$flag) {
            $search = ['<?', '?>'];
            $replace = ['&lt;?', '?&gt;'];
            if ($encodes && is_array($encodes) || $encodes = self::ENCODES) {
                foreach ($encodes as $k=>$v) {
                    $search[] = $k;
                    $replace[] = $v;
                }
            }
            $str = str_replace($search, $replace, $str);
        }
        return $str;
    }
    /**
     * 是否匹配
     * $str 要匹配的字符
     * $flag 匹配标记或是正则表达式或自定义函数
     * $others 额外参数: 例如指定需要额外匹配的字符(正则表达式的字符格式)，或者是指定正则表达式的结果取反
     */
    public static function Is (string &$str, int|string|callable $flag, string|bool $others = ''): bool
    {
        if (is_callable($flag)) {
            return $flag($str, $others);
        }
        if (is_int($flag)) {
            return match($flag) {
                self::NOT_MB4 => !preg_match('/[' . self::PREG_MB4 . "{$others}]/u", $str),
                self::NOT_CTRLS => !preg_match('/[' . self::PREG_CTRLS . "{$others}]/u", $str),
                self::NOT_SPECIAL => !preg_match('/[' . self::PREG_SPECIAL . "{$others}]/", $str),
                self::NOT_BASE => !preg_match('/[' . self::PREG_BASE . "{$others}]/", $str),
                self::NOT_LETTER => !preg_match('/[' . self::PREG_LETTER . "{$others}]/", $str),
                self::NOT_NORMAL => !preg_match('/[' . self::PREG_NORMAL . "{$others}]/u", $str),
                self::NOT_SYMBOLS => !preg_match('/[' . self::PREG_SYMBOLS . "{$others}]/u", $str),
                self::NOT_STR => !preg_match('/[' . self::PREG_STR . "{$others}]/u", $str),
                self::NOT_STRMB4 => !preg_match('/[' . self::PREG_STRMB4 . "{$others}]/u", $str),
                self::NOT_WORDS => !preg_match('/[' . self::PREG_WORDS . "{$others}]/u", $str),
                self::NOT_WORDSMB4 => !preg_match('/[' . self::PREG_WORDSMB4 . "{$others}]/u", $str),
                self::NOT_ZH => !preg_match('/[' . self::PREG_ZH . "{$others}]/u", $str),

                self::BASE => !preg_match('/[^' . self::PREG_BASE . "{$others}]/", $str),
                self::SPECIAL => !preg_match('/[^' . self::PREG_SPECIAL . "{$others}]/", $str),
                self::NORMAL => !preg_match('/[^' . self::PREG_NORMAL . "{$others}]/u", $str),
                self::SYMBOLS => !preg_match('/[^' . self::PREG_SYMBOLS . "{$others}]/u", $str),
                self::STR => !preg_match('/[^' . self::PREG_STR . "{$others}]/u", $str),
                self::MB4 => !preg_match('/[^' . self::PREG_MB4 . "{$others}]/u", $str),
                self::STRMB4 => !preg_match('/[^' . self::PREG_STRMB4 . "{$others}]/u", $str),
                self::WORDS => !preg_match('/[^' . self::PREG_WORDS . "{$others}]/u", $str),
                self::WORDSMB4 => !preg_match('/[^' . self::PREG_WORDSMB4 . "{$others}]/u", $str),
                self::ZH => !preg_match('/[^' . self::PREG_ZH . "{$others}]/u", $str),
                self::VAR => !!preg_match('/^' . self::PREG_VAR . '$/', $str),
                self::EMAIL => !!preg_match('/^' . self::PREG_EMAIL . '$/', $str),
                self::URL => !!preg_match('/^' . self::PREG_URL . '$/', $str),
                self::AB_URL => !!preg_match('/^' . self::PREG_ABURL . '$/', $str),
                self::LOCAL_URL => !!preg_match('/^' . self::PREG_LOCAL_URL . '$/', $str),
                self::TEL => !!preg_match('/^' . self::PREG_TEL . '$/', $str),
                self::PHONE => !!preg_match('/^' . self::PREG_PHONE . '$/', $str),
                self::INTE_PHONE => !!preg_match('/^' . self::PREG_INTE_PHONE . '$/', $str),
                self::TIME => !!preg_match('/^' . self::PREG_TIME . '$/', $str),
                self::NUMBER => is_numeric($str),
                self::BOOL => self::Vbool($str),
                self::INT => self::Vint($str),
                self::FLOAT => self::Vfloat($str),
                self::IP => !!filter_var($str, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4|FILTER_FLAG_IPV6),
                self::IPV4 => !!filter_var($str, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4),
                self::IPV6 => !!filter_var($str, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6),
                default => throw new Exception('不能识别的过滤标记:' . $flag),
            };
        }
        return $others ? !preg_match($flag, $str) : !!preg_match($flag, $str);
    }
    /**
     * 是否包含
     * $str 要匹配的字符
     * $flag 匹配标记或是正则表达式
     * $others 指定需要额外匹配的字符(正则表达式的字符格式)
     */
    public static function Has (string $str, int|string $flag, string $others = ''): string|false
    {
        $preg = is_int($flag) ? match($flag) {
            self::NOT_MB4 => '/[^' . self::PREG_MB4 . "{$others}]/",
            self::NOT_CTRLS => '/[^' . self::PREG_CTRLS . "{$others}]/",
            self::NOT_SPECIAL => '/[^' . self::PREG_SPECIAL . "{$others}]/",
            self::NOT_BASE => '/[^' . self::PREG_BASE . "{$others}]/",
            self::NOT_LETTER => '/[^' . self::PREG_LETTER . "{$others}]/",
            self::NOT_NORMAL => '/[^' . self::PREG_NORMAL . "{$others}]/u",
            self::NOT_SYMBOLS => '/[^' . self::PREG_SYMBOLS . "{$others}]/u",
            self::NOT_STR => '/[^' . self::PREG_STR . "{$others}]/u",
            self::NOT_STRMB4 => '/[^' . self::PREG_STRMB4 . "{$others}]/u",
            self::NOT_WORDS => '/[^' . self::PREG_WORDS . "{$others}]/u",
            self::NOT_WORDSMB4 => '/[^' . self::PREG_WORDSMB4 . "{$others}]/u",
            self::NOT_ZH => '/[^' . self::PREG_ZH . "{$others}]/u",

            self::BASE => '/[' . self::PREG_BASE . "{$others}]/",
            self::SPECIAL => '/[' . self::PREG_SPECIAL . "{$others}]/",
            self::NORMAL => '/[' . self::PREG_NORMAL . "{$others}]/",
            self::SYMBOLS => '/[' . self::PREG_SYMBOLS . "{$others}]/u",
            self::STR => '/[' . self::PREG_STR . "{$others}]/u",
            self::STRMB4 => '/[' . self::PREG_STRMB4 . "{$others}]/u",
            self::MB4 => '/[' . self::PREG_MB4 . "{$others}]/u",
            self::WORDS => '/[' . self::PREG_WORDS . "{$others}]/u",
            self::WORDSMB4 => '/[' . self::PREG_WORDSMB4 . "{$others}]/u",
            self::ZH => '/[' . self::ZH . "{$others}]/u",
            self::VAR => '/' . self::PREG_VAR . '/',
            self::EMAIL => '/' . self::PREG_EMAIL . '/',
            self::URL => '/' . self::PREG_URL . '/',
            self::AB_URL => '/' . self::PREG_ABURL . '/',
            self::LOCAL_URL => '/' . self::PREG_LOCAL_URL . '/',
            self::TEL => '/' . self::PREG_TEL . '/',
            self::PHONE => '/' . self::PREG_PHONE . '/',
            self::INTE_PHONE => '/' . self::PREG_INTE_PHONE . '/',
            self::IPV4 => '/' . self::PREG_IPV4 . '/',
            self::TIME => '/' . self::PREG_TIME . '/',
            default => throw new Exception('不能识别的过滤标记'),
        } : $flag;
        if (preg_match($preg, $str, $match)) {
            return $match[0];
        }
        return false;
    }
    public static function ParseInt(string|int|array $res, string $dim = ''): int|array
    {
        if (is_int($res)) {
            return $res;
        }
        if (is_numeric($res)) {
            $data = $dim ? [(int)$res] : (int)$res;
        } elseif (is_array($res)) {
            foreach($res as $v) {
                $data[] = (int)$v;
            }
        } elseif ($dim && str_contains($res, $dim)) {
            $arr = explode($dim, $res);
            foreach($arr as $v) {
                if (is_numeric($v)) {
                    $data[] = (int)$v;
                }
            }
        }
        return $data ?? 0;
    }
    public static function ParseFloat(string|array $res, string $dim = ''): int|array
    {
        if (is_numeric($res)) {
            $data = $dim ? [(float)$res] : (float)$res;
        } elseif (is_array($res)) {
            foreach($res as $v) {
                $data[] = (float)$v;
            }
        } elseif ($dim && str_contains($res, $dim)) {
            $arr = explode($dim, $res);
            foreach($arr as $v) {
                if (is_numeric($v)) {
                    $data[] = (float)$v;
                }
            }
        }
        return $data ?? 0;
    }
    public static function ParseMoney($res, string $dim = '')
    {
        if (is_numeric($res)) {
            $data = $dim ? [(int)(100 * $res)] : (int)(100 * $res);
        } elseif (is_array($res)) {
            foreach($res as $v) {
                $data[] = (int)$v;
            }
        } elseif ($dim && false !== strpos($res, $dim)) {
            $arr = explode($dim, $res);
            foreach($arr as $v) {
                if (is_numeric($v)) {
                    $data[] = (int)(100 * $v);
                }
            }
        }
        return $data ?? 0;
    }

    public static function Get (string $key): string|array|null
    {
        if (!$key) {
            return $_GET;
        }
        if (!isset($_GET[$key])) {
            return null;
        }
        return is_string($_GET[$key]) ? trim($_GET[$key]) : $_GET[$key];
    }
    public static function GetInt (string $key, string $dim = ''): int|array|null
    {
        return isset($_GET[$key]) ? self::ParseInt($_GET[$key], $dim) : null;
    }
    public static function GetFloat (string $key, string $dim = ''): float|array|null
    {
        return isset($_GET[$key]) ? self::ParseFloat($_GET[$key], $dim) : null;
    }
    public static function GetMoney (string $key, string $dim = '')
    {
        return isset($_GET[$key]) ? self::ParseMoney($_GET[$key], $dim) : null;
    }
    public static function GetBool (string $key, bool $toInt = false): bool|int
    {
        $ret = match(empty($_GET[$key]) ? '' : strtoupper($_GET[$key])) {
            '1', 'Y', 'ON', 'YES', 'TRUE' => true,
            default => false,
        };
        return $toInt ? (int)$ret : $ret;
    }

    public static function Param (string $key): string|array|null
    {
        if (!isset(ROUTE['params'][$key])) {
            return null;
        }
        return is_string(ROUTE['params'][$key]) ? trim(ROUTE['params'][$key]) : ROUTE['params'][$key];
    }
    public static function ParamInt (string $key, string $dim = ''): int|array|null
    {
        return isset(ROUTE['params'][$key]) ? self::ParseInt(ROUTE['params'][$key], $dim) : null;
    }
    public static function ParamFloat (string $key, string $dim = ''): float|array|null
    {
        return isset(ROUTE['params'][$key]) ? self::ParseFloat(ROUTE['params'][$key], $dim) : null;
    }
    public static function ParamMoney (string $key, string $dim = '')
    {
        return isset(ROUTE['params'][$key]) ? self::ParseMoney(ROUTE['params'][$key], $dim) : null;
    }
    public static function ParamBool (string $key, bool $toInt = false): bool|int
    {
        $ret = match(empty(ROUTE['params'][$key]) ? '' : strtoupper(ROUTE['params'][$key])) {
            '1', 'Y', 'ON', 'YES', 'TRUE' => true,
            default => false,
        };
        return $toInt ? (int)$ret : $ret;
    }

    public static function Query (string $key): string|array|null
    {
        if (!$key) {
            return ROUTE['query'] ?? null;
        }
        if (!isset(ROUTE['query'][$key])) {
            return null;
        }
        return is_string(ROUTE['query'][$key]) ? trim(ROUTE['query'][$key]) : ROUTE['query'][$key];
    }
    public static function QueryInt (string $key, string $dim = ''): int|array|null
    {
        return isset(ROUTE['query'][$key]) ? self::ParseInt(ROUTE['query'][$key], $dim) : null;
    }
    public static function QueryFloat (string $key, string $dim = ''): float|array|null
    {
        return isset(ROUTE['query'][$key]) ? self::ParseFloat(ROUTE['query'][$key], $dim) : null;
    }
    public static function QueryMoney (string $key, string $dim = '')
    {
        return isset(ROUTE['query'][$key]) ? self::ParseMoney(ROUTE['query'][$key], $dim) : null;
    }
    public static function QueryBool (string $key, bool $toInt = false): bool|int
    {
        $ret = match(ROUTE['query'][$key] ?? '') {
            'on', 'On', 'ON', 'true', 'True', 'TRUE' => true,
            default => false,
        };
        return $toInt ? (int)$ret : $ret;
    }

    public static function Path (int $index): string|array|null
    {
        if (0 > $index) {
            return ROUTE['path'] ?? null;
        }
        if (!isset(ROUTE['path'][$index])) {
            return null;
        }
        return is_string(ROUTE['path'][$index]) ? trim(ROUTE['path'][$index]) : ROUTE['path'][$index];
    }
    public static function PathInt (int $index, string $dim = ''): int|array|null
    {
        return isset(ROUTE['path'][$index]) ? self::ParseInt(ROUTE['path'][$index], $dim) : null;
    }
    public static function PathFloat (int $index, string $dim = ''): float|array|null
    {
        return isset(ROUTE['path'][$index]) ? self::ParseFloat(ROUTE['path'][$index], $dim) : null;
    }
    public static function PathMoney (string $key, string $dim = '')
    {
        return isset(ROUTE['path'][$key]) ? self::ParseMoney(ROUTE['path'][$key], $dim) : null;
    }
    public static function PathBool (int $index, bool $toInt = false): bool|int
    {
        $ret = match(ROUTE['path'][$index] ?? '') {
            'on', 'On', 'ON', 'true', 'True', 'TRUE' => true,
            default => false,
        };
        return $toInt ? (int)$ret : $ret;
    }

    public static function Input (string $key)
    {
        if (!$key) {
            return match (METHOD) {
                'GET'=>$_GET,
                'POST'=>$_POST,
                default=> INPUT,
            };
        }
        $data = match (METHOD) {
            'GET'=>$_GET[$key] ?? null,
            'POST'=>$_POST[$key] ?? null,
            default=> INPUT,
        };
        if (null === $data) {
            return null;
        }
        return is_string($data) ? trim($data) : $data;
    }
    public static function InputInt (string $key, string $dim = ''): int|array|null
    {
        $data = match (METHOD) {
            'GET'=>$_GET[$key] ?? null,
            'POST'=>$_POST[$key] ?? null,
            default=> INPUT,
        };
        return null === $data ? null : self::ParseInt($data, $dim);
    }
    public static function InputFloat (string $key, string $dim = ''): float|array|null
    {
        $data = match (METHOD) {
            'GET'=>$_GET[$key] ?? null,
            'POST'=>$_POST[$key] ?? null,
            default=> INPUT,
        };
        return null === $data ? null : self::ParseFloat($data, $dim);
    }
    public static function InputMoney (string $key, string $dim = '')
    {
        $data = match (METHOD) {
            'GET'=>$_GET[$key] ?? null,
            'POST'=>$_POST[$key] ?? null,
            default=> INPUT,
        };
        return null === $data ? null : self::ParseMoney($data, $dim);
    }
    public static function InputBool (string $key, bool $toInt = false): bool|int|null
    {
        $data = match (METHOD) {
            'GET'=>$_GET[$key] ?? null,
            'POST'=>$_POST[$key] ?? null,
            default=> INPUT,
        };

        null === $data || $data = filter_var($data, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if (null === $data) {
            return null;
        }
        return $toInt ? (int)$data : $data;
    }
}