<?php
declare(strict_types=1);
namespace nec\z;

debug::setup();

class debug
{
    const ERRTYPE = [2 => '运行警告', 8 => '运行提醒', 256 => '错误', 512 => '警告', 1024 => '提醒', 2048 => '编码标准化警告', 1100 => '文件', 1110 => 'SQL错误', 1120 => 'SQL查询', 1130 => '环境', 1131 => '常量', 1132 => '配置', 1133 => '命名空间', 1140 => '模板文件', 1150 => '模板变量', 1160 => 'POST', 8192 => '运行通知'];
    private static float $pdotime = 0;
    private static array $errs = [];

    public static function setup(): void {
        define('DEBUGER', __CLASS__);
    }
    public static function pdotime(float $time): void
    {
        self::$pdotime += $time;
    }
    private static function debugShowLevel (): int
    {
        $level = $GLOBALS['ZPHP_CONFIG']['DEBUG']['level'] ?? 3;
        if (!$level || !$ipset = $GLOBALS['ZPHP_CONFIG']['DEBUG']['ip'] ?? null) {
            return $level;
        }
        $ip = GetIp();
        if (is_string($ipset)) {
            if ('/' === $ip[0] && '/' === $ip[strlen($ip) - 1]) {
                return preg_match($ipset, $ip) ? $level : 0;
            }
            return $ipset === $ip ? $level : 0;
        }
        if (is_array($ipset)) {
            foreach($ipset as $v) {
                if ('/' === $v[0] && '/' === $v[strlen($v) - 1]) {
                    return preg_match($v, $ip) ? $level : 0;
                }
                if ($ipset === $ip) {
                    return $level;
                }
            }
            return 0;
        }
    }
    public static function exceptionHandler(\Error|\Exception $e): void
    {
        $level = self::debugShowLevel();
        $log = $GLOBALS['ZPHP_CONFIG']['DEBUG']['log'] ?? 0;
        2 > $level && !$log && \z::_500();
        $line = $e->getLine();
        $file = $e->getFile();
        $msg = TransCode($e->getMessage()) . " at [{$file} : {$line}]";
        $trace = $e->getTraceAsString();
        $trace = str_replace('\\\\', '\\', $trace);

        foreach ($e->getTrace() as $k => $v) {
            isset($v['args']) && $args["#{$k}"] = 1 === count($v['args']) ? $v['args'][0] : $v['args'];
        }
        if ($log) {
            $args_str = isset($args) ? P($args, false) : '';
            $str = $msg . PHP_EOL . $trace . PHP_EOL;
            $args_str && $str .= 'args: ' . str_replace("\n", PHP_EOL, $args_str);
            self::log($str, 'error');
        }
        if ($level > 1) {
            header('Status: 500');
            $type = $GLOBALS['ZPHP_CONFIG']['DEBUG']['type'] ?? 'html';
            if ('json' === $type) {
                $err = ['errMsg' => $msg, 'trace' => $trace];
                isset($args) && $err['args'] = $args;
                if (!empty($GLOBALS['ZPHP_CONFIG']['DEBUG']['hash'])) {
                    $json = self::encode(json_encode($args, JSON_ENCODE_CFG));
                    die($json);
                }
                \z::json($err);
            } else {
                $err = "<style>body{margin:0;padding:0;}</style><div style='background:#FFBBDD;padding:1rem;'><h2>ERROR!</h2><h3>{$msg}</h3>";
                $err .= '<strong><pre>' . $trace . '</pre></strong>';
                if (isset($args)) {
                    $err .= '<h3>参数：</h3>';
                    $err .= '<pre>' . P($args, false) . '</pre>';
                }
                $err .= '</div>';
                die(self::encode($err));
            }
        } else {
            \z::_500();
        }
    }
    private static function log(string $str, string $type): void
    {
        $dir = P_TMP . "/{$type}_log/" . APP_NAME;
        !file_exists($dir) && !mkdir($dir, 0755, true);
        $file = $dir . '/' . date('Y-m-d') . '.log';
        $str = '[' . date('H:i:s') . "] {$str}";
        file_put_contents($file, $str . PHP_EOL, FILE_APPEND);
    }
    public static function setMsg(int $errno, string $str): void
    {
        self::$errs[$errno][] = $str;
    }
    public static function errorHandler(int $errno, string $errstr, string $errfile, int $errline): void
    {
        $level = self::debugShowLevel();
        $log = $GLOBALS['ZPHP_CONFIG']['DEBUG']['log'] ?? 0;
        if ($level < 3 && $log < 2) {
            return;
        }

        $errstr = TransCode($errstr);
        $errfile = '[' . str_replace('\\', '/', $errfile) . " ] : {$errline}";
        $log > 1 && self::log("{$errstr} {$errfile}", 'warning');
        if ($level > 2) {
            IsAjax() || $errstr = str_replace('\\', '\\\\', $errstr);
            self::$errs[$errno][] = "{$errstr} {$errfile}";
        }
    }
    public static function GetDebug(int $level = -1): array|null
    {
        -1 === $level && $level = $GLOBALS['ZPHP_CONFIG']['DEBUG']['level'] ?? 0;
        if ($level) {
            $json['运行'] = [
                'SQL查询' => round(1000 * self::$pdotime, 3) . 'ms',
                '运行时间' => round(1000 * (microtime(true) - FTIME), 3) . 'ms',
                '内存使用' => FileSizeFormat(memory_get_usage()),
                '内存峰值' => FileSizeFormat(memory_get_peak_usage()),
            ];
        }
        if (2 < $level) {
            defined('VIEW') && (VIEW)::GetParams();
            $json['文件'] = get_included_files();
            $json['环境'] = $_SERVER;
            $json['POST'] = $_POST;
            $json['常量'] = get_defined_constants(true)['user'];
            $json['配置'] = $GLOBALS['ZPHP_CONFIG'];
            $json['命名空间'] = $GLOBALS['ZPHP_MAPPING'];
        }
        if (1 < $level) {
            foreach (self::$errs as $k => $v) {
                $json[self::ERRTYPE[$k]] = $v;
            }
        }
        return $json ?? null;
    }
    public static function Decode (string $str): void
    {
        if (!$str = trim($str)) {
            die('NULL');
        }
        if (!$hash = $GLOBALS['ZPHP_CONFIG']['DEBUG']['hash'] ?? null) {
            die('请设置DEBUG[hash]的加密字符串');
        }
        $t = intval(TIME/300);
        if (!$str = 
            openssl_decrypt($str, 'AES-256-ECB', pack('I', $t) . $hash) ?:
            openssl_decrypt($str, 'AES-256-ECB', pack('I', $t - 1) . $hash)
        ) {
            die('');
        }

        if ('<' === $str[0]) {
            die($str);
        } else {
            die("<script>console.log({$str})</script>");
        }
    }
    private static function encode (string $str) : string
    {
        if ($str && $hash = $GLOBALS['ZPHP_CONFIG']['DEBUG']['hash'] ?? null) {
            $str = openssl_encrypt($str, 'AES-256-ECB', pack('I', intval(TIME/300)) . $hash);
        }
        return $str;
    }
    public static function ShowMsg(): void
    {
        if (!$level = self::debugShowLevel()) {
            die;
        }
        switch ($GLOBALS['ZPHP_CONFIG']['DEBUG']['type'] ?? '') {
            case 'html':
                self::ShowHtml($level);
                break;
            case 'json':
                self::ShowJson($level);
                break;
            default:
                self::ShowJson($level);
                break;
        }
    }
    public static function ShowJson(int $level): void
    {
        $json = json_encode(self::GetDebug($level), JSON_ENCODE_CFG);
        $json = self::encode($json);
        die("<script>console.log({$json})</script>");
    }
    public static function ShowHtml(int $level): void
    {
        $runtime = round(1000 * (microtime(true) - FTIME), 3) . 'ms';
        $pdotime = round(1000 * self::$pdotime, 3) . 'ms';
        $memory = FileSizeFormat(memory_get_usage());
        $memory_max = FileSizeFormat(memory_get_peak_usage());
        $html = $tab = '';
        if (2 < $level) {
            self::getConfigs();
            self::getServer();
            self::getConstants();
            self::getIncludeFiles();
            self::getMapping();
            self::getPost();
            self::getParams();
        }
        if (1 < $level) {
            foreach (self::$errs as $k => $v) {
                $tab .= "<button type=\"button\" ID=\"{$k}\" tid=\"{$k}\">" . self::ERRTYPE[$k] . ':[' . count($v) . ']</button>';
                $html .= "<div ID=\"zdebug-li{$k}\"><p># " . implode('</p><p># ', $v) . '</p></div>';
            }
        }
        $html = str_replace(["'", '"'], ["\\'", '\\"'], $html);
        $echo = <<<EOT
<style type="text/css">body{margin: 0;padding: 0;}
#zdebug,#zdebug p,#zdebug h2{margin:0;padding:0}#zdebug a:hover,#zdebug a:visited,#zdebug a:link,#zdebug a:active{color:#00e}#zdebug-li div{display:none}#zdebug-li #zdebug-lisysmain{display:block}#zdebug hr{-webkit-margin-before:0;-webkit-margin-after:0;margin:2px}#zdebug{font-size:14px;overflow:hidden;position:fixed;min-width:200px;background:#eb99ff;border:1px solid #444;border-radius:5px;box-shadow:0 1px 3px 2px #666}#zdebug .zdebug-content{overflow-y:auto;width:100%;min-width:560px;min-height:131px;overflow-x:hidden}.zdebug-main{padding:5px 10px;word-wrap:break-word;word-break:break-all}.zdebug-main p{-webkit-margin-before:0;-webkit-margin-after:0;font-size:14px;line-height:24px}#zdebug-tab{padding-bottom:5px}#zdebug-tab button{line-height:1.2;font-weight:700;padding:2px 2px}#zdebug h2{-webkit-margin-before:0;-webkit-margin-after:0}#zdebug .title{position:relative;height:27px;padding:0 10px;cursor:move;border-bottom:solid 1px #888}#zdebug .title h2{font-size:14px;height:27px;line-height:24px}#zdebug .title div{position:absolute;height:19px;top:2px;right:0}#zdebug .title a,a.open{float:left;height:19px;display:block;margin-left:5px;text-decoration:none}#zdebug .title a.min{background-position:-29px 0}#zdebug .title a.min:hover{background-position:-29px -29px}#zdebug .title a.max{background-position:-60px 0}#zdebug .title a.max:hover{background-position:-60px -29px}#zdebug .title a.revert{background-position:-149px 0;display:none}#zdebug .title a.revert:hover{background-position:-149px -29px}#zdebug .title a.close{background-position:-89px 0;display:none}#zdebug .title a.close:hover{background-position:-89px -29px}#zdebug .resizeBR{position:absolute;width:14px;height:14px;right:0;bottom:0;overflow:hidden;cursor:nw-resize}#zdebug .resizeL,#zdebug .resizeT,#zdebug .resizeR,#zdebug .resizeB,#zdebug .resizeLT,#zdebug .resizeTR,#zdebug .resizeLB{position:absolute;background:#000;overflow:hidden;opacity:0;filter:alpha(opacity=0)}#zdebug .resizeL,#zdebug .resizeR{top:0;width:5px;height:100%;cursor:w-resize}#zdebug .resizeR{right:0}#zdebug .resizeT,#zdebug .resizeB{width:100%;height:5px;cursor:n-resize}#zdebug .resizeT{top:0}#zdebug .resizeB{bottom:0}#zdebug .resizeLT,#zdebug .resizeTR,#zdebug .resizeLB{width:8px;height:8px;background:#FF0}#zdebug .resizeLT{top:0;left:0;cursor:nw-resize}#zdebug .resizeTR{top:0;right:0;cursor:ne-resize}#zdebug .resizeLB{left:0;bottom:0;cursor:ne-resize}
</style>
<script type="text/javascript">window.onload=function(){var iframeid=top.document.getElementsByTagName("iframe").length;top==parent||(iframeid+=parent.document.getElementsByTagName("iframe").length);var zdebug=document.createElement("div");zdebug.style["z-index"]=2147483647-iframeid;zdebug.id="zdebug";zdebug.innerHTML='<div class="title"><div style="margin:5px 5px 0 5px;position:relative;"><h2>DEBUG'+(iframeid?' #'+iframeid:'')+'</h2><div><a class="min" href="javascript:;" title="最小化">最小</a><a class="max" href="javascript:;" title="最大化">最大</a><a class="revert" href="javascript:;" title="还原">重置</a><a class="close" href="javascript:;" title="关闭">关闭</a></div></div></div><div class="resizeL"></div><div class="resizeT"></div><div class="resizeR"></div><div class="resizeB"></div><div class="resizeLT"></div><div class="resizeTR"></div><div class="resizeBR"></div><div class="resizeLB"></div><div class="zdebug-main"><div class="zdebug-content"><div ID="zdebug-tab"><button type="button" tid="sysmain" style="background:#69D34E;">基本信息</button>$tab</div><div ID="zdebug-li"><div ID="zdebug-lisysmain"><p># [SQL查询耗时] : $pdotime </p><p># [脚本总运行时间] : $runtime </p><p># [内存使用] : $memory </p><p># [内存峰值] : $memory_max </div> $html </div><hr></div></div>';document.body.appendChild(zdebug);var get={byId:function byId(ID){return typeof ID==="string"?document.getElementById(ID):ID},byClass:function byClass(sClass,oParent){var aClass=[];var reClass=new RegExp("(^| )"+sClass+"( |$)");var aElem=this.byTagName("*",oParent);for(var i=0;i<aElem.length;i++){reClass.test(aElem[i].className)&&aClass.push(aElem[i])}return aClass},byTagName:function byTagName(elem,obj){return(obj||document).getElementsByTagName(elem)}},dragMinWidth=200,dragMinHeight=36,oL=get.byClass("resizeL",zdebug)[0],oT=get.byClass("resizeT",zdebug)[0],oR=get.byClass("resizeR",zdebug)[0],oB=get.byClass("resizeB",zdebug)[0],oLT=get.byClass("resizeLT",zdebug)[0],oTR=get.byClass("resizeTR",zdebug)[0],oBR=get.byClass("resizeBR",zdebug)[0],oLB=get.byClass("resizeLB",zdebug)[0],oTitle=get.byClass("title",zdebug)[0],oContent=get.byClass("zdebug-content",zdebug)[0],rH=zdebug.clientHeight;oContent.style["max-height"]=Math.ceil(document.documentElement.offsetHeight/2)+"px",oContent.style.height=rH-oTitle.offsetHeight-15+"px";function setPosition(){var height=document.body.clientHeight,width=document.body.clientWidth,offH=window.innerHeight-zdebug.offsetHeight,offW=width-zdebug.offsetWidth-1;zdebug.style.top=offH<0?0:offH+"px";zdebug.style.left=offW+"px";zdebug.style["max-width"]=document.body.clientWidth}function setIframe(s){if(!window.frames.length){return false}var iframe=document.getElementsByTagName("iframe");if(!iframe.length){return fasle}s=s?"block":"none";for(i=0;iframe[i];i++){iframe[i].style.display=s}}function setCursor(c){if(c){oL.style.cursor="w-resize";oT.style.cursor="n-resize";oR.style.cursor="w-resize";oB.style.cursor="n-resize";oLT.style.cursor="nw-resize";oTR.style.cursor="ne-resize";oBR.style.cursor="nw-resize";oLB.style.cursor="ne-resize"}else{oL.style.cursor="default";oT.style.cursor="default";oR.style.cursor="default";oB.style.cursor="default";oLT.style.cursor="default";oTR.style.cursor="default";oBR.style.cursor="default";oLB.style.cursor="default"}}function reset(){drag(zdebug,oTitle);resize(zdebug,oLT,true,true,false,false);resize(zdebug,oTR,false,true,false,false);resize(zdebug,oBR,false,false,false,false);resize(zdebug,oLB,true,false,false,false);resize(zdebug,oL,true,false,false,true);resize(zdebug,oT,false,true,true,false);resize(zdebug,oR,false,false,false,true);resize(zdebug,oB,false,false,true,false)}function drag(zdebug,handle){oContent.style.width=oContent.offsetWidth;var disX=0,dixY=0;var oMin=get.byClass("min",zdebug)[0];var oMax=get.byClass("max",zdebug)[0];var oRevert=get.byClass("revert",zdebug)[0];var oClose=get.byClass("close",zdebug)[0];handle=handle||zdebug;handle.style.cursor="move";handle.onmousedown=function(event){setIframe(0);var event=event||window.event;var disX=event.clientX-zdebug.offsetLeft,disY=event.clientY-zdebug.offsetTop;document.onmousemove=function(event){var height=document.body.clientHeight,width=document.body.clientWidth,event=event||window.event,iL=parseInt(event.clientX-disX),iT=parseInt(event.clientY-disY),maxL=parseInt(width-zdebug.offsetWidth),maxT=parseInt(window.innerHeight-zdebug.offsetHeight);maxT=maxT<0?0:maxT;iL>=maxL&&(iL=maxL-1);iT>=maxT&&(iT=maxT-1);iL<=0&&(iL=0);iT<=0&&(iT=0);zdebug.style.left=iL+"px";zdebug.style.top=iT+"px"};document.onmouseup=function(){setIframe(1);document.onmousemove=null;document.onmouseup=null;this.releaseCapture&&this.releaseCapture()};return false};oMax.onclick=function(){zdebug.style.top=zdebug.style.left=0;zdebug.style.width="100%";zdebug.style.height="";this.style.display="none";oContent.style.display="block";oRevert.style.display="block";oMin.style.display="block";oContent.style["max-height"]="";oContent.style.width=null;oContent.style["overflow-y"]="auto";oContent.style.height=window.innerHeight-45+"px"};oRevert.onclick=function(){zdebug.style.height=rH+"px";zdebug.style.width="auto";oContent.style.display="block";this.style.display="none";oMin.style.display="block";oMax.style.display="block";oContent.style.height=rH-oTitle.offsetHeight-15+"px";setCursor(true);oContent.style.width="560px";setPosition()};oMin.onclick=oClose.onclick=function(){zdebug.style.height="auto";zdebug.style.width="200px";oContent.style.display="none";this.style.display="none";oRevert.style.display="block";oMax.style.display="block";setCursor(false);setPosition()};oMin.onmousedown=oMax.onmousedown=oClose.onmousedown=function(event){this.onfocus=function(){this.blur()};(event||window.event).cancelBubble=true}}function resize(oParent,handle,isLeft,isTop,lockX,lockY){handle.onmousedown=function(event){setIframe(0);oContent.style.width="";if(oContent.style.display=="none"){return false}var event=event||window.event;var disX=event.clientX-handle.offsetLeft;var disY=event.clientY-handle.offsetTop;var iParentTop=oParent.offsetTop;var iParentLeft=oParent.offsetLeft;var iParentWidth=oParent.offsetWidth;var iParentHeight=oParent.offsetHeight;document.onmousemove=function(event){var event=event||window.event;var iL=event.clientX-disX;var iT=event.clientY-disY;var maxW=document.documentElement.clientWidth-oParent.offsetLeft-2;var iW=isLeft?iParentWidth-iL:handle.offsetWidth+iL;var iH=isTop?iParentHeight-iT:handle.offsetHeight+iT;isLeft&&(oParent.style.left=iParentLeft+iL+"px");isTop&&(oParent.style.top=iParentTop+iT+"px");iW<dragMinWidth&&(iW=dragMinWidth);iW>maxW&&(iW=maxW);lockX||(oParent.style.width=iW+"px");iH<dragMinHeight&&(iH=dragMinHeight);iH>window.innerHeight&&(iH=window.innerHeight);lockY||(oParent.style.height=iH+"px");lockY||(oContent.style.height=zdebug.clientHeight-oTitle.offsetHeight-15+"px");lockY||(oContent.style["max-height"]="");if(isLeft&&iW==dragMinWidth||isTop&&iH==dragMinHeight){document.onmousemove=null}return false};document.onmouseup=function(){setIframe(1);document.onmousemove=null;document.onmouseup=null;if(parseInt(zdebug.style.top.replace("px",""))<=0){zdebug.style.top=0}};return false}}window.onresize=function(){setPosition()};reset();setPosition();var button=document.getElementById("zdebug-tab").getElementsByTagName("button"),li=document.getElementById("zdebug-li").getElementsByTagName("div");for(var i=0;li[i];i++){if("zdebug-lisysmain"!=li[i].id){li[i].style.display="none"}}for(i=0;button[i];i++){switch(button[i].getAttribute("tid")){case"2":case"256":case"512":case"256":case"2048":case"1101":case"1110":button[i].style.color="red";break}button[i].onclick=function(){var thisTid=this.getAttribute("tid"),show=document.getElementById("zdebug-li"+thisTid);for(var b=0;b<button.length;b++){var tid=button[b].getAttribute("tid");if(thisTid==tid&&show.style.display=="none"){button[b].style.background="#69D34E"}else{button[b].style.background=""}}for(var a=0;a<li.length;a++){if("zdebug-li"+thisTid==li[a].id&&show.style.display=="none"){li[a].style.display="block"}else{li[a].style.display="none"}}}}};</script>
EOT;
        echo self::encode($echo);
        die;
    }
    private static function getIncludeFiles(): void
    {
        $files = get_included_files();
        foreach ($files as $v) {
            $file = str_replace('\\', '/', $v);
            self::$errs[1100][] = $file . '[ ' . FileSizeFormat(filesize($file)) . ' ]';
        }
    }
    private static function getMapping(): void
    {
        if (isset($GLOBALS['ZPHP_MAPPING'])) {
            foreach ($GLOBALS['ZPHP_MAPPING'] as $k => $v) {
                self::$errs[1133][] = "{$k}: $v";
            }
        }
    }
    private static function getConfigs(): void
    {
        foreach ($GLOBALS['ZPHP_CONFIG'] as $k => $v) {
            $str = htmlspecialchars(json_encode($v, JSON_ENCODE_CFG));
            self::$errs[1132][] = "[{$k}] : {$str}";
        }
    }
    private static function getParams(): void
    {
        if (!$params = defined('VIEW') ? (VIEW)::GetParams() : false) {
            return;
        }
        foreach ($params as $k => $v) {
            $str = htmlspecialchars(json_encode($v, JSON_ENCODE_CFG));
            self::$errs[1150][] = "\${$k} : {$str}";
        }
    }
    private static function getPost(): void
    {
        if ($_POST) {
            foreach ($_POST as $k => $v) {
                $str = htmlspecialchars(json_encode($v, JSON_ENCODE_CFG));
                self::$errs[1160][] = "[{$k}] : {$str}";
            }
        }
    }
    private static function getConstants(): void
    {
        $const = get_defined_constants(true)['user'];
        foreach ($const as $k => $v) {
            $str = htmlspecialchars(json_encode($v, JSON_ENCODE_CFG));
            self::$errs[1131][] = "[{$k}] : {$str}";
        }
    }
    private static function getServer(): void
    {
        foreach ($_SERVER as $k => $v) {
            $str = htmlspecialchars(json_encode($v, JSON_ENCODE_CFG));
            self::$errs[1130][] = "[{$k}] : {$str}";
        }
    }
}
