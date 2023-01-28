<?php
namespace model;

use root\base\model;

/**
 * 继承根目录下/base/model类
 * 并使用父类的方法初始化 pdo和db
 */
class test extends model
{
    public function CreateTable()
    {
        $pdo = $this->pdo();

        $sql = "DROP TABLE IF EXISTS `z_user`";
        $result = $pdo->submit($sql);

        $sql = "CREATE TABLE `z_user`(
		`uid` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`headimg` VARCHAR(255) NOT NULL DEFAULT '',
		`name` VARCHAR(32) NOT NULL DEFAULT '',
		`phone` VARCHAR(16) NOT NULL DEFAULT '',
		`email` VARCHAR(32) NOT NULL DEFAULT '',
		`ctime` INT UNSIGNED NOT NULL DEFAULT 0,
		INDEX z_user(`name`,`phone`,`email`,`ctime`)
		)ENGINE=InnoDB  DEFAULT CHARSET=utf8";
        $result = $pdo->submit($sql);
        return $result;
    }
    public function InsertData()
    {
        $db = $this->db('user');
        $rand = uniqid();
        $data['name'] = substr($rand, 0, 6);
        $data['headimg'] = $rand;
        $data['phone'] = '186' . mt_rand(10000000, 99999999);
        $data['ctime'] = TIME;
        $result = $db->insert($data) ? ['msg' => '添加成功'] : $this->error($db->getError());
        return $result;
    }
    public function FindData()
    {
        $db = $this->db();
        $where = 'uid > 0';
        $result = $db->table('user')->where($where)->find();
        return $result;
    }
    public function SelectData()
    {
        $db = $this->db();
        $limit = intval(ROUTE['query']['limit'] ?? 0) ?: 10;
        $where['uid >'] = 0;
        $result = $db->table('user')->where($where)->limit($limit)->select();
        return $result;
    }
    public function PageSelect0()
    {
        $db = $this->db();
        $p = intval(ROUTE['query']['p'] ?? 0) ?: 1;
        $num = intval(ROUTE['query']['num'] ?? 0) ?: 10;
        $page = ['num' => $num, 'p' => $p, 'return' => true];
        $result['data'] = $db->table('user')->page($page)->select();
        $result['page'] = $db->getPage();
        return $result;
    }
    public function PageSelect1()
    {
        $db = $this->db();
        $p = intval(ROUTE['query']['p'] ?? 0) ?: 1;
        $num = intval(ROUTE['query']['num'] ?? 0) ?: 10;
        $page = ['num' => $num, 'p' => $p, 'var' => 'p', 'return' => ['prev', 'next', 'first', 'last', 'list']];
        $result['data'] = $db->table('user')->page($page)->select();
        $result['page'] = $db->getPage();
        return $result;
    }
    public function CacheSelect()
    {
        $db = $this->db();
        $p = intval(ROUTE['query']['p'] ?? 0) ?: 1;
        $num = intval(ROUTE['query']['num'] ?? 0) ?: 10;
        $page = ['num' => $num, 'p' => $p, 'return' => true];
        $result['data'] = $db->table('user')->page($page)->cache(60)->select();
        $result['page'] = $db->getPage();
        return $result;
    }
}
