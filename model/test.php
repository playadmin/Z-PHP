<?php
namespace model;

use lib\z\dbc\dbc;
use lib\z\dbc\db;

/**
 * 继承根目录下/base/model类
 * 并使用父类的方法初始化 pdo和db
 */
class test
{
    private $errMsg = '';
    private function error(string $err): bool
    {
        $this->errMsg = $err;
        return false;
    }
    public function GetError()
    {
        return $this->errMsg;
    }
    public function CreateTable()
    {
        $pdo = dbc::Init();

        $sql = "DROP TABLE IF EXISTS `z_user`";
        $result = $pdo->Exec($sql);

        $sql = "CREATE TABLE `z_user`(
		`uid` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`headimg` VARCHAR(255) NOT NULL DEFAULT '',
		`name` VARCHAR(32) NOT NULL DEFAULT '',
		`phone` VARCHAR(16) NOT NULL DEFAULT '',
		`email` VARCHAR(32) NOT NULL DEFAULT '',
		`ctime` INT UNSIGNED NOT NULL DEFAULT 0,
		INDEX z_user(`name`,`phone`,`email`,`ctime`)
		)ENGINE=InnoDB  DEFAULT CHARSET=utf8";
        $result = $pdo->Exec($sql);
        return $result;
    }
    public function InsertData()
    {
        $db = db::Init('user');
        $rand = uniqid();
        $data['name'] = substr($rand, 0, 6);
        $data['headimg'] = $rand;
        $data['phone'] = '186' . mt_rand(10000000, 99999999);
        $data['ctime'] = TIME;
        $result = $db->Insert($data) ? ['msg' => '添加成功'] : $this->error('添加数据失败');
        return $result;
    }
    public function FindData()
    {
        $db = db::Init('user');
        $where = 'uid > 0';
        $result = $db->table('user')->where($where)->find();
        return $result;
    }
    public function SelectData()
    {
        $db = db::Init('user');
        $limit = intval(ROUTE['query']['limit'] ?? 0) ?: 10;
        $where['uid >'] = 0;
        $result = $db->table('user')->where($where)->limit($limit)->select();
        return $result;
    }
    public function PageSelect0()
    {
        $db = db::Init('user');
        $p = intval(ROUTE['query']['p'] ?? 0) ?: 1;
        $num = intval(ROUTE['query']['num'] ?? 0) ?: 10;
        $page = ['num' => $num, 'p' => $p, 'return' => true];
        $result['data'] = $db->table('user')->page($page)->select();
        $result['page'] = $db->getPage();
        return $result;
    }
    public function PageSelect1()
    {
        $db = db::Init('user');
        $p = intval(ROUTE['query']['p'] ?? 0) ?: 1;
        $num = intval(ROUTE['query']['num'] ?? 0) ?: 10;
        $page = ['num' => $num, 'p' => $p, 'return' => ['prev', 'next', 'first', 'last', 'list']];
        $result['data'] = $db->table('user')->page($page)->select();
        $result['page'] = $db->getPage();
        return $result;
    }
    public function CacheSelect()
    {
        $db = db::Init('user');
        $p = intval(ROUTE['query']['p'] ?? 0) ?: 1;
        $num = intval(ROUTE['query']['num'] ?? 0) ?: 10;
        $page = ['num' => $num, 'p' => $p, 'return' => true];
        $result['data'] = $db->table('user')->page($page)->cache(60)->select();
        $result['page'] = $db->getPage();
        return $result;
    }
}
