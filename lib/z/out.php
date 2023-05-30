<?php
declare(strict_types=1);
namespace lib\z;

class out {
    const SUCCESS = 0,
    CODE = 'Ret',
    DATA = 'Data',
    ERRDATA = 'ErrData',
    REDIRECT = 'Redirect',
    MSG = 'Msg',
    DEBUG = 'ZPHP_DEBUG',
    PAGES_LIST = 'Items',
    PAGES_PAGE = 'Page';

    static function Error(int $ret, string $desc = '', array $d = null, $header = null)
    {
        $data[static::CODE] = $ret;
        $data[static::MSG] = $desc;
        $d && $data[static::ERRDATA] = $d;
        self::Json($data, $header);
    }
    static function Redirect(int $ret, string $url, string $desc = '', $header = null)
    {
        $data[static::CODE] = $ret;
        $data[static::MSG] = $desc;
        $data[static::REDIRECT] = $url;
        self::Json($data, $header);
    }
    static function InnerPage(array $d, $page, $header = null)
    {
        $data[static::CODE] = self::SUCCESS;
        $data[static::DATA] = [self::PAGES_LIST=>$d]; //, self::PAGES_PAGE=>$page];
        $page && $data[static::DATA][self::PAGES_PAGE] = $page;
        self::Json($data, $header);
    }
    static function OuterPage(array $d, $page, $header = null)
    {
        $data[static::CODE] = self::SUCCESS;
        $data[static::DATA] = $d;
        $data[self::PAGES_PAGE] = $page;
        self::Json($data, $header);
    }
    static function Success($d = null, $header = null)
    {
        $data[static::CODE] = self::SUCCESS;
        $data[static::DATA] = $d;
        self::Json($data, $header);
    }
    static function Json(array $data = [], $header = null)
    {
        ob_end_clean();
        header('Content-Type:application/json; charset=utf-8');
        if ($header) {
            if (is_array($header)) {
                foreach ($header as $v) {
                    header($v);
                }
            } else {
                header($header);
            }
        }
        if (DEBUGER && $debug = (DEBUGER)::GetDebug()) {
            $data[static::DEBUG] = $debug;
        }
        die(json_encode($data, JSON_ENCODE_CFG));
    }
}
