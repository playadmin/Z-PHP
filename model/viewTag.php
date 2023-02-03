<?php
/**
 * 自定义标签示例
 */

declare(strict_types=1);

namespace model;

use Exception;

class viewTag {
    const CLASS_PATH = 'model';

    /**
     * @ $attrs dom 标签的属性
     * @ $args dom 标签属性(属性值为表达式。属性名前面有冒号:，表示值是php代码，不是字符串)
     */
    static function if ($attrs, $args) {
        $class = '';
        $act = '';
        $var = '$var';
        if (isset($args['var'])) {
            // 获取 :var 属性的值 作为接收数据的变量名
            $var = $args['var'];
            unset($args['var']);
        }
        if (isset($attrs['a'])) {
            // 获取 a 属性的值 作为函数名
            $act = $attrs['a'];
            unset($attrs['a']);
            unset($args['a']);
        }
        if (!$act) {
            // 如果没有函数名，则之间判断 变量(或表达式) 的真假
            return [
                'if('. $var . "):",
                'endif;'
            ];
        }
        if (isset($attrs['c'])) {
            // 获取 c 属性的值 作为类的命名空间
            $class = $attrs['c'];
            unset($attrs['c']);
            unset($args['c']);
        }

        // 拼接调用路径
        if ($class) {
            str_contains($class, '\\') || $class = self::CLASS_PATH . '\\' . $class;
            $call = "{$class}::{$act}";
            is_callable([$class, $act]) || throw new Exception("不支持的调用: {$call}");
        } else {
            $call = $act;
            is_callable($act) || throw new Exception("不支持的调用: {$act}");
        }

        $args = ExportArray($args, false);

        // 拼接表达式，返回值写入模板
        // 数组2个值分为前后两部分写入模板，<if><p>It is true</p></if>标签中间的p标签是判断为真的时候需要显示的内容
        return [
            'if('. $var .'='. $call . "({$args})):",
            'endif;'
        ];
    }
    static function loop (array $attrs, $args) {
        $var = empty($args['var']) ? '$var': $args['var'];
        $key = empty($args['key']) ? '$key' : $args['key'];
        $val = empty($args['val']) ? '$val' : $args['val'];
        return [
            'foreach (' . $var . ' as '.$key.'=>'.$val.'):',
            'endforeach;',
        ];
    }

}