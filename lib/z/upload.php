<?php
declare(strict_types=1);
/**
 * $conf['previewPath'] = P_PUBLIC . 'uploads/preview'; // 预览图保存路径
 * $conf['previewWidth'] = 300; // 预览图宽度
 * $conf['previewQuality'] = 80; // 预览图质量(0-100)
 * $conf['path'] = P_PUBLIC . 'uploads/img';
 * $conf['allowType'] = array('jpg','gif','png');
 * $conf['maxSize'] = 1024*1024;
 * $up = new upload($conf);
 * $result = $up->upload(true);//参数true遇到错误继续，返回上传的文件信息，键名对应表单的name值
 * $info = $up->getInfo();//返回上传文件信息，索引数组
 * $err = $up->getError();//返回错误信息，数组
 */
namespace lib\z;

class upload
{
    const ERROR_MSG = [
        1 => '上传的文件超过了 PHP.ini 中 upload_max_filesize 选项限制的值',
        2 => '上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值',
        3 => '文件只有部分被上传',
        4 => '没有文件被上传',
        6 => '找不到临时文件夹',
        7 => '文件写入失败',
    ];
    
    private string $savePath = ''; //文件保存目录，根据$path和$subPath自动生成，不可指定
    private array $conf = [
        'path' => P_IN . 'uploads', //上传目录
        'subPath' => '', //子目录
        'setName' => '', //指定文件名
        'preName' => '',  //文件名前缀
        'sufName' => '', //文件名后缀
        'maxSize' => 2097152, //允许的文件大小
        'randName' => true, //是否随机命名
        'previewPath' => '', //预览图保存路径
        'previewWidth' => 300, //预览图宽度
        'previewQuality' => 80, //预览图质量(0-100)
    ];

    private array $allowType = ['.jpg', '.gif', '.png', '.rar', '.zip', '.7z', '.mp3', '.mp4', '.flv', '.sql', '.txt', '.xls', '.xlsx', '.doc', '.docx', '.pdf'], //允许的文件后缀
        $filesInfo = [], //原始文件信息
        $error = [], //错误信息
        $mapping = [], //上传文件信息的索引映射
        $info = []; //上传文件的信息

    public function __construct(?array $conf = null)
    {
        if ($this->checkLength() && $conf) {
            $this->conf = $conf + $this->conf;
            isset($conf['allowType']) && $this->allowType = $conf['allowType'];
        }
    }

    /**
     * 获取错误信息
     * @return [array] [description]
     */
    public function GetError(): array
    {
        return $this->error;
    }

    /**
     * [执行上传操作]
     * @param  boolean $ignore [遇到上传错误是否继续]
     * @return [array]         [数组键名对应form表单的name]
     */
    public function Upload(bool $ignore = false): array|false
    {
        $this->getFiles();
        if (empty($this->mapping)) {
            $this->error[] = "没有合法的上传文件";
            return false;
        }
        if ((!$ignore && $this->error) || !$this->makeDir()) {
            return false;
        }

        foreach ($this->filesInfo['name'] as $k => $v) {
            $this->setFileInfo($k);
            $move = $this->moveFile($k);
            if (!$ignore && !$move) {
                return false;
            }

        }
        return $this->info;
    }

    /**
     * 返回文件信息
     * @param  boolean $index [是否索引数组]
     * @return [type]         [description]
     */
    public function GetInfo(bool $isIndex = true): array|null
    {
        return $isIndex ? $this->mapping : $this->info;
    }

    /**
     * 获取文件基本信息
     * @return [type] [description]
     */
    private function getFiles()
    {
        $i = 0;
        $arr = array_keys($_FILES);
        foreach ($arr as $v) {
            if (is_array($_FILES[$v]['name'])) {
                $keys = array_keys($_FILES[$v]['name']);
                foreach ($keys as $key) {
                    if (empty($_FILES[$v]['name'][$key])) {
                        continue;
                    }

                    $pathinfo = pathinfo($_FILES[$v]['name'][$key]);
                    $ext = $pathinfo['extension'] ? '.' . strtolower($pathinfo['extension']) : '';
                    if (!$this->check($_FILES[$v]['name'][$key], $_FILES[$v]['size'][$key], $ext, $_FILES[$v]['error'][$key])) {
                        continue;
                    }

                    $this->filesInfo['ext'][$i] = $ext;
                    $this->filesInfo['name'][$i] = $_FILES[$v]['name'][$key];
                    $this->filesInfo['type'][$i] = $_FILES[$v]['type'][$key];
                    $this->filesInfo['size'][$i] = $_FILES[$v]['size'][$key];
                    $this->filesInfo['tmp_name'][$i] = $_FILES[$v]['tmp_name'][$key];
                    $this->info[$v][$key]['name'] = $this->getFileName($i);
                    $this->info[$v][$key]['ext'] = $this->filesInfo['ext'][$i];
                    $this->info[$v][$key]['rawName'] = $this->filesInfo['name'][$i];
                    $this->info[$v][$key]['type'] = $this->filesInfo['type'][$i];
                    $this->info[$v][$key]['size'] = $this->filesInfo['size'][$i];
                    $this->mapping[$i] = $this->info[$v][$key];
                    ++$i;
                }
            } else {
                if (empty($_FILES[$v]['name'])) {
                    continue;
                }

                $pathinfo = pathinfo($_FILES[$v]['name']);
                $ext = $pathinfo['extension'] ? '.' . strtolower($pathinfo['extension']) : '';
                if (!$this->check($_FILES[$v]['name'], $_FILES[$v]['size'], $ext, $_FILES[$v]['error'])) {
                    continue;
                }

                $this->filesInfo['ext'][$i] = $ext;
                $this->filesInfo['name'][$i] = $_FILES[$v]['name'];
                $this->filesInfo['type'][$i] = $_FILES[$v]['type'];
                $this->filesInfo['size'][$i] = $_FILES[$v]['size'];
                $this->filesInfo['tmp_name'][$i] = $_FILES[$v]['tmp_name'];
                $this->info[$v]['name'] = $this->getFileName($i);
                $this->info[$v]['ext'] = $this->filesInfo['ext'][$i];
                $this->info[$v]['rawName'] = $this->filesInfo['name'][$i];
                $this->info[$v]['type'] = $this->filesInfo['type'][$i];
                $this->info[$v]['size'] = $this->filesInfo['size'][$i];
                $this->mapping[$i] = $this->info[$v];
                ++$i;
            }
        }
    }
    /**
     * 获取新文件名
     */
    private function getFileName(int $i): string
    {
        if ($this->conf['setName']) {
            return $i ? "{$this->conf['setName']}_{$i}{$this->filesInfo['ext'][$i]}" : "{$this->conf['setName']}{$this->filesInfo['ext'][$i]}";
        }

        if (!$this->conf['randName']) {
            return $this->filesInfo['name'][$i];
        }
        return str_replace('.', '-', uniqid($this->conf['preName'], true)) . $i . $this->conf['sufName'] . $this->filesInfo['ext'][$i];
    }

    /**
     * 创建目录
     */
    private function makeDir(): string|false
    {
        $path = rtrim($this->conf['path'], '/');
        if ('/' != $path[0] && ':' != $path[1]) {
            $path = P_IN . $path;
        }

        if ($this->conf['subPath']) {
            $path .= '/' . trim($this->conf['subPath'], '/');
        }

        MakeDir($path);
        if (!is_writable($path)) {
            $this->error[] = "目录[{$path}]不可写，请检查权限";
            return false;
        } else {
            $this->savePath = $path;
            return $path;
        }
    }

    /**
     * 设置文件信息
     * @param [type] $i [description]
     */
    private function setFileInfo(int $i)
    {
        $path = "{$this->savePath}/{$this->mapping[$i]['name']}";
        $this->mapping[$i]['path'] = $path;
        $this->mapping[$i]['src'] = U_HOME . substr($path, LEN_IN);
    }

    /**
     * 保存文件
     * @param int $i 文件索引
     * @return bool 是否成功
     */
    private function moveFile(int $i): bool
    {
        $mov = move_uploaded_file($this->filesInfo['tmp_name'][$i], iconv("UTF-8", "GBK", $this->mapping[$i]['path']));
        if ($mov) {
            // 如果是图片且配置了预览路径，则生成预览图
            if (in_array($this->filesInfo['ext'][$i], ['.jpg', '.jpeg', '.png', '.gif'])) {
                $this->createPreviewImage($this->mapping[$i]['path'], $i);
            }
            return true;
        } else {
            $this->error[] = "文件[{$this->filesInfo['name'][$i]}]保存失败";
            unset($this->mapping[$i]);
            return false;
        }
    }

    /**
     * 创建预览图片
     * @param string $sourcePath 原始图片路径
     * @param int $i 文件索引
     * @return bool 是否成功
     */
    private function createPreviewImage(string $sourcePath, int $i): bool
    {
        // 如果没有设置预览路径，则使用原路径下的preview目录
        $previewPath = $this->conf['previewPath'] ?: $this->savePath . '/preview';
        
        // 创建预览目录
        if (!is_dir($previewPath) && !mkdir($previewPath, 0755, true)) {
            $this->error[] = "无法创建预览目录[{$previewPath}]";
            return false;
        }

        // 预览图文件名
        $previewFile = $previewPath . '/' . $this->mapping[$i]['name'];
        
        // 根据不同类型处理图片
        switch ($this->filesInfo['ext'][$i]) {
            case '.jpg':
            case '.jpeg':
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case '.png':
                $source = imagecreatefrompng($sourcePath);
                break;
            case '.gif':
                $source = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }

        if (!$source) {
            $this->error[] = "无法读取图片文件[{$sourcePath}]";
            return false;
        }

        // 获取原始图片尺寸
        $width = imagesx($source);
        $height = imagesy($source);
        
        // 计算新高度，保持宽高比
        $newWidth = $this->conf['previewWidth'];
        $newHeight = (int)($height * ($newWidth / $width));
        
        // 创建新图像
        $preview = imagecreatetruecolor($newWidth, $newHeight);
        
        // 处理PNG和GIF的透明背景
        if ($this->filesInfo['ext'][$i] == '.png' || $this->filesInfo['ext'][$i] == '.gif') {
            imagealphablending($preview, false);
            imagesavealpha($preview, true);
            $transparent = imagecolorallocatealpha($preview, 255, 255, 255, 127);
            imagefilledrectangle($preview, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // 调整图片大小
        imagecopyresampled($preview, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // 保存预览图
        $result = false;
        switch ($this->filesInfo['ext'][$i]) {
            case '.jpg':
            case '.jpeg':
                $result = imagejpeg($preview, $previewFile, $this->conf['previewQuality']);
                break;
            case '.png':
                $result = imagepng($preview, $previewFile, (int)(9 * (100 - $this->conf['previewQuality']) / 100));
                break;
            case '.gif':
                $result = imagegif($preview, $previewFile);
                break;
        }
        
        // 释放内存
        imagedestroy($source);
        imagedestroy($preview);
        
        if ($result) {
            // 保存预览图信息
            $this->mapping[$i]['preview'] = $previewFile;
            return true;
        } else {
            $this->error[] = "无法保存预览图[{$previewFile}]";
            return false;
        }
    }

    /**
     * 检查POST数据是否合法
     * @return [type] [description]
     */
    private function checkLength(): bool
    {
        if (empty($_FILES)) {
            $size = ini_get("post_max_size");
            $this->error[] = "没有上传文件或者数据大小超出[post_max_size:{$size}]，请检查PHP配置文件";
            return false;
        }
        return true;
    }

    /**
     * 检查文件合法性
     * @param  string $name 文件名
     * @param  int $size 文件大小
     * @param  string $fix  文件后缀
     * @param  int $err  错误号
     * @return int       [description]
     */
    private function check(string $name, int $size, string $fix, int $errNo)
    {
        return 3 == $this->checkSize($size, $name) + $this->checkType($fix, $name) + $this->checkErr($errNo, $name);
    }

    /**
     * 检查文件大小
     * @param  int $size     [description]
     * @param  string $fileName [description]
     * @return int           [description]
     */
    private function checkSize(int $size, string $fileName): int
    {
        if (!$size) {
            $this->error[] = "{$fileName}:文件大小错误";
            return 0;
        } elseif ($size > $this->conf['maxSize']) {
            $this->error[] = "{$fileName}:文件大小超过限制";
            return 0;
        } else {
            return 1;
        }
    }

    /**
     * 检查文件后缀合法性
     * @param  string $fix      [description]
     * @param  string $fileName [description]
     * @return int           [description]
     */
    private function checkType(string $fix, string $fileName): int
    {
        if ($this->allowType && $fix && !in_array($fix, $this->allowType)) {
            $this->error[] = "{$fileName}:不允许的文件类型";
            return 0;
        } else {
            return 1;
        }
    }

    /**
     * 检查错误号
     * @param  int $err      [description]
     * @param  string $fileName [description]
     * @return int           [description]
     */
    private function checkErr(int $err, string $fileName): int
    {
        if ($err) {
            $this->error[] = isset(self::ERROR_MSG[$err]) ? "{$fileName}:" . self::ERROR_MSG[$err] : "{$fileName}:未知错误";
            return 0;
        } else {
            return 1;
        }
    }
}
