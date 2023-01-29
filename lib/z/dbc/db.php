<?php
declare(strict_types=1);

namespace lib\z\dbc;

use Exception;

abstract class db
{
    use \lib\z\dbc\cache;
    const WRAP_L = '', WRAP_R = '';
    protected static array $DB_INSTANCE = [];
    protected array $DB_CONFIG = [],
    $DB_BASE = [],
    $DB_BIND = [],
    $DB_PAGE = [],
    $DB_PAGED = [],
    $DB_TABLES = [],
    $DB_WHERE = [],
    $DB_JOINMAP = [],
    $DB_HAVING = [],
    $DB_JOIN = [],
    $DB_CHAIN = [],
    $DB_CALL = [],
    $Z_CACHE = [];

    protected string $DB_PREFIX = '',
    $DB_WRAP_FIELD_REPLACE = '',
    $DB_WRAP_L = '',
    $DB_WRAP_R = '',
    $DB_TABLE = '',
    $DB_TABLED = '',
    $DB_WHERED = '',
    $DB_FIELD = '*',
    $DB_JOIND = '',
    $DB_HAVINGD = '',
    $DB_GROUP = '',
    $DB_ORDER = '',
    $DB_MERGE = '',
    $DB_LIMIT = '',
    $DB_TMP = '',
    $DB_SQLD = '';

    public pdo $PDO;

    public static function Init(string $table = '', array $c = null): static
    {
        $pdo = dbc::Init($c);
        $cfg = $pdo->GetConfig();
        $key = md5($cfg['dsn'] ?? '');
        if (isset(static::$DB_INSTANCE[$key])) {
            $table && static::$DB_INSTANCE[$key]->Table($table);
            return static::$DB_INSTANCE[$key];
        }
        $class = __NAMESPACE__ . '\\DB' . $cfg['drive'];
        return new $class($cfg, $pdo, $table, $key);
    }
    public static function New (string $table = '', array $c = null, string $key = null): static
    {
        $pdo = dbc::Init($c);
        $cfg = $pdo->GetConfig();
        $key = md5($cfg['dsn'] ?? '');
        $class = __NAMESPACE__ . '\\DB' . $cfg['drive'];
        return new $class($cfg, $pdo, $table, $key);
    }
    protected function __construct(array $cfg, pdo $pdo, string $table = '', $key = null)
    {
        $this->PDO = $pdo;
        $key || $key = md5($cfg['dsn'] ?? '');
        $this->DB_CONFIG = $cfg;
        $this->DB_PREFIX = $cfg['prefix'] ?? '';
        $this->DB_WRAP_FIELD_REPLACE = '$1' . static::WRAP_L . '$2' . static::WRAP_R;
        $this->DB_WRAP_L = static::WRAP_L;
        $this->DB_WRAP_R = static::WRAP_R;
        $table && $this->Table($table);
        if (!empty($this->DB_CONFIG['base'])) {
            $base = IsFullPath($this->DB_CONFIG['base']) ? $this->DB_CONFIG['base'] : P_ROOT . $this->DB_CONFIG['base'];
            is_file($base) && is_array($base = require $base) && $this->DB_BASE = $base;
        }
    }
    public function Wrap (string|array $key, string $alias = '')
    {
        if ($alias) {
            return is_array($key) ? $alias . implode(",{$alias}", $key) : $alias . $key;
        }
        $preg = '/\(.+\)|' . preg_quote($this->DB_WRAP_L) . '\w+' . preg_quote($this->DB_WRAP_R) . '/';
        if (is_array($key)) {
            $keys = array_map(function ($k) use($preg) {
                return preg_match($preg, $k) ? $k : $this->DB_WRAP_L . $k . $this->DB_WRAP_R;
            }, $key);
            return implode(',', $keys);
        }
        return preg_match($preg, $key) ? $key : $this->DB_WRAP_L . $key . $this->DB_WRAP_R;
    }
    public function GetConfig (): array
    {
        return $this->DB_CONFIG;
    }
    public function CacheKey (string $type, int $mode, string $sql): array
    {
        $key = "{$type}|{$sql}|{$mode}";
        $this->DB_BIND && $key .= ':[' . implode(',', $this->DB_BIND) . ']';
        $table = strstr($this->DB_TABLE, ' ', true) ?: $this->DB_TABLE;
        return [$table, sha1($key)];
    }
    /**
     * 缓存数据
     * $mod 缓存方式 [file, redis, memcached]
     */
    public function Cache(int $expire = 0, ?string $mod = null): static
    {
        $mod || $mod = empty($this->DB_CONFIG['cache_mod']) ? 'file' : $this->DB_CONFIG['cache_mod'];
        empty($this->DB_CONFIG['dbname']) && $this->DB_CONFIG['dbname'] = 'mydb';
        $this->Z_CACHE = [$expire ?: 0, $mod];
        return $this;
    }
    public function Add(array $data, bool $ignore = false)
    {
        return $this->Insert($data, $ignore);
    }
    public function IfInsert(array $insert, array $update = null)
    {
        return $this->IfUpdate($insert, $update);
    }
    public function Save(array $data)
    {
        return $this->Update($data);
    }
    public function GetPrefix(): string
    {
        return $this->DB_PREFIX;
    }
    public function InTransaction()
    {
        return $this->PDO->Writer()->inTransaction();
    }
    public function GetSql (): string {
        return $this->PDO->GetSql();
    }
    public function Begin(): bool
    {
        $this->DB_CALL = [];
        return $this->PDO->Begin();
    }
    public function Rollback(): bool
    {
        $this->DB_CALL = [];
        return $this->PDO->Rollback();
    }
    public function Commit(): bool
    {
        $result = $this->PDO->Commit();
        if ($result && $this->DB_CALL) {
            foreach ($this->DB_CALL as $v) {
                $v[0]($v[1], $this);
            }
        }
        $this->DB_CALL = [];
        return $result;
    }
    public function Tmp(string $sql, string $alias = 'z'): static
    {
        $this->DB_TMP = "({$sql}) AS {$alias}";
        return $this;
    }
    public function Table(string $table): static
    {
        $this->DB_TABLE = $table;
        $this->DB_TABLES = [];
        $this->DB_TABLED = '';
        return $this;
    }

    public function Field(string|array $fields = ''): static
    {
        if ($fields) {
            if (is_array($fields)) {
                $this->DB_FIELD = $this->Wrap($fields);
            } else {
                $this->DB_FIELD = $this->DB_wrapFileds($fields);
            }
        }
        return $this;
    }
    public function Group(string $group = ''): static
    {
        $group && $this->DB_GROUP = $this->DB_wrapFileds($group);
        return $this;
    }
    public function Order(string $order = ''): static
    {
        $order && $this->DB_ORDER = $this->DB_wrapFileds($order);
        return $this;
    }
    public function Limit(string|int $limit = ''): static
    {
        $limit && $this->DB_LIMIT = (string)$limit;
        return $this;
    }
    public function Where(null|string|array $where, array $bind = null): static
    {
        $where && $this->DB_WHERE[] = [$where, $bind];
        return $this;
    }
    public function And(null|string|array $where, array $bind = null): static
    {
        $where && $this->DB_WHERE[] = [$where, $bind, 'AND'];
        return $this;
    }
    public function Or(null|string|array $where, array $bind = null): static
    {
        $where && $this->DB_WHERE[] = [$where, $bind, 'OR'];
        return $this;
    }
    public function Having(string|array $having, array $bind = null): static
    {
        $having && $this->DB_HAVING = [$having, $bind];
        return $this;
    }
    public function Join(string|array $join = ''): static
    {
        $join && (is_array($join) ? $this->DB_JOIN = array_merge($this->DB_JOIN, $join) : $this->DB_JOIN[] = $join);
        return $this;
    }
    public function Chain(array $chain = null): static
    {
        /**
         * $chain: [table=>[prikey, [raw_field=>[...chain_field]]]]
         * $sourceMap: [raw_field=>[new_field, map]]
         */
        $this->DB_CHAIN = $chain ?: [];
        return $this;
    }

    /**
     * 合并关联查询
     */
    public function QueryChain(&$data, array $chain)
    {
        $resultMap = [];
        $fieldMap = [];
        $implode = "{$this->DB_WRAP_R},{$this->DB_WRAP_L}";
        foreach ($chain as $table=>$set) {
            $map = [];
            $bind = [];
            foreach ($set[1] as $raw=>$field) {
                if (is_array($field)) {
                    foreach ($field as $k=>$v) {
                        if (is_int($k)) {
                            $map[$v] = true;
                            $fieldMap[$table][$raw] = $v;
                        } else {
                            $fieldMap[$table][$raw] = [$k, $v];
                            $map[$k] = true;
                        }
                    }
                } else {
                    $map[$field] = true;
                    $fieldMap[$table][$raw] = $field;
                }
                array_push($bind, ...array_column($data, $raw));
            }
            $tableName = "{$this->DB_WRAP_L}{$this->DB_PREFIX}{$table}{$this->DB_WRAP_R}";
            $bind = array_values(array_unique($bind));
            $where = "{$this->DB_WRAP_L}{$set[0]}{$this->DB_WRAP_R} IN(" . str_repeat('?,', count($bind) - 1) . '?)';
            $queryFields = "{$this->DB_WRAP_L}{$set[0]}{$implode}" . implode($implode, array_keys($map)) . $this->DB_WRAP_R;
            $sql = "SELECT {$queryFields} FROM {$tableName} WHERE {$where}";
            $q = $this->PDO->Stmt($sql, $bind);
            if ($res = $q->Rows(\PDO::FETCH_ASSOC)) {
                $map = [];
                foreach ($res as $v) {
                    $map[$v[$set[0]]] = $v;
                }
                $resultMap[$table] = $map;
            }
        }
        if ($resultMap) {
            foreach ($data as &$d) {
                foreach ($fieldMap as $t=>$set) {
                    if (!$map = $resultMap[$t] ?? null) {
                        continue;
                    }
                    foreach ($set as $k=>$s) {
                        $key = $d[$k];
                        if (is_array($s)) {
                            $d[$s[1]] = $map[$key][$s[0]] ?? null;
                        } else {
                            $d[$s] = $map[$key][$s] ?? null;
                        }
                    }
                }
            }
        }
    }
    public function Parse ($reset = true): array
    {
        $sql = $this->DB_sql();
        $field = $this->DB_field();
        $res[] = "SELECT {$field} FROM " . $sql;
        $res[] = $this->DB_BIND;
        $reset && $this->DB_done();
        return $res;
    }
    public function SubQuery(string $field = '', bool $lock = false): string
    {
        $field && $this->DB_FIELD = $this->DB_wrapFileds($field);
        list($sql) = $this->Parse(false);
        $lock && $sql = $this->DB_lockRows($sql);
        $this->DB_WHERE = [];
        $this->DB_WHERED = '';
        $this->DB_TMP = '';
        $this->DB_FIELD = '*';
        $this->DB_PAGE = [];
        $this->DB_JOIN = [];
        $this->DB_JOIND = '';
        $this->DB_JOINMAP = [];
        $this->DB_GROUP = '';
        $this->DB_ORDER = '';
        $this->DB_LIMIT = '';
        $this->DB_HAVING = [];
        $this->DB_SQLD = '';
        $this->DB_MERGE = '';
        return $sql;
    }

    abstract function Columns (string $table): array | false;
    abstract function Column (string $table, string $field): array | bool;
    abstract function PriKey (?string $table = null): array|string;
    abstract protected function DB_ApproximateRows (string $table): int; // 数据表总数据量的模糊值,非必要,无此功能的数据库可返回-1
    abstract protected function DB_lockRows (string $sql): string|null; // 锁定数据行,无此功能的数据库可直接返回参数$sql(返回null则会抛出异常)
    abstract protected function DB_duplicate(array $insert, array $update = null): array|string|bool|int|null; // 有则更新，无则插入(返回null则会抛出异常)
    abstract protected function DB_dcount (string $from, string $sfield, string $field): int|false|null;// 去重统计(返回null则会抛出异常)
    abstract protected function DB_iinsert(string $table, array $data): stmt|false|null; // 插入数据, 已存在数据则不插入(返回null则会抛出异常)
    abstract protected function DB_ibatchInsert(array &$data): string|null; // 批量插入数据, 已存在数据则不插入(返回null则会抛出异常)

    public function GetPage(): array
    {
        return $this->DB_PAGED;
    }
    public function Truncate (string $table)
    {
        // 防止误操作必须指定$table 而不使用 $this->DB_table()
        $table = $this->Wrap($table);
        $sql = "TRUNCATE TABLE {$table}";
        return $this->PDO->exec($sql);
    }
    public function Count(string $field = '', bool $done = true): int
    {
        $field || $field = $this->DB_FIELD ?: '*';
        $from = $this->DB_sql(true, true);
        $this->DB_GROUP && $from = "(SELECT 1 FROM {$from}) DB_n";
        if (9 < strlen($field) && preg_match('/DISTINCT[^\w]+(\w+)/', $field, $match)) {
            $result = $this->DB_dcount($from, $field, $match[1]);
            if (null === $result) {
                throw new Exception('DISTINCT is not supported');
            }
        } else {
            $result = $this->PDO->Stmt("SELECT COUNT({$field}) FROM {$from}", $this->DB_BIND)->Row(\PDO::FETCH_COLUMN);
        }
        $done && $this->DB_done();
        return (int)$result;
    }
    public function Merge(string $sql, string|array $type = ''): static
    {
        // type='ALL'不去除重复
        if (is_array($sql)) {
            if (is_array($type)) {
                foreach ($sql as $k => $v) {
                    $t = empty($type[$k]) ? ' UNION ' : " UNION {$type[$k]} ";
                    $this->DB_MERGE .= $t . $v;
                }
            } else {
                $t = $type ? " UNION {$type} " : ' UNION ';
                $this->DB_MERGE = $t . implode($t, $sql);
            }
        } else {
            $type && $type .= ' ';
            $this->DB_MERGE = $type . $sql;
        }
        return $this;
    }
    public function Fetch(bool $lock = false): \PDOStatement
    {
        if ($this->DB_PAGE) {
            $this->DB_page();
            $this->DB_PAGED['r'] || $this->DB_done();
            $sql = $this->DB_sql(true);
        } else {
            $sql = $this->DB_sql();
        }
        $field = $this->DB_field();
        $sql = "SELECT {$field} FROM " . $sql;
        $lock && $sql = $this->DB_lockRows($sql);
        if (null === $sql) {
            throw new Exception('Row lock is not supported');
        }
        $stmt = $this->PDO->Stmt($sql, $this->DB_BIND)->Execute();
        $this->DB_done();
        return $stmt;
    }
    public function Find(string $field = '', bool $lock = false)
    {
        $fetch = \PDO::FETCH_ASSOC;
        $field && ($this->DB_FIELD = $this->DB_wrapFileds($field)) && $fetch = \PDO::FETCH_COLUMN;
        $sql = $this->DB_sql();
        $field = $this->DB_field();
        $sql = "SELECT {$field} FROM {$sql}";
        if ($lock || !$this->Z_CACHE) {
            $lock && $sql = $this->DB_lockRows($sql);
            if (null === $sql) {
                throw new Exception('Row lock is not supported');
            }
            $result = $this->PDO->Stmt($sql, $this->DB_BIND)->Row($fetch);
        } else {
            $result = $this->CacheFind($fetch, $sql);
        }
        $this->DB_done();
        return $result;
    }
    public function CacheFind(int $fetch, string $sql): array
    {
        list($mod, $expire) = $this->Z_CACHE;
        list($table, $key) = $this->CacheKey('1', $fetch, $sql);
        $this->Z_CACHE = [];
        $result = $this->GetCache($mod, $this->DB_CONFIG['dbname'], $table, $key);
        if (null === $result) {
            $res = $this->SetCache($mod, $this->DB_CONFIG['dbname'], $table, $key, function () use ($sql, $fetch) {
                $stmt = $this->PDO->Stmt($sql, $this->DB_BIND);
                return $stmt->Row($fetch);
            }, $expire);
            $result = $res[1];
        }
        return $result;
    }
    public function CacheSelect(int $fetch): array
    {
        $field = $this->DB_field();
        list($mod, $expire) = $this->Z_CACHE;
        $this->Z_CACHE = [];
        $sql = "SELECT {$field} FROM ";
        if ($this->DB_PAGE) {
            $page = Page($this->DB_PAGE);
            $this->DB_LIMIT = $page['limit'];
            $sqlc = $sql . $this->DB_sql();
            list($table, $key) = $this->CacheKey('2', $fetch, $sqlc);
            $pkey = "{$key}-p{$page['num']}";
            $result = $this->GetCache($mod, $this->DB_CONFIG['dbname'], $table, $key);
            $paged = $this->GetCache($mod, $this->DB_CONFIG['dbname'], $table, $pkey);
            if (null === $result || !$paged) {
                $res = $this->SetCache($mod, $this->DB_CONFIG['dbname'], $table, $key, function () use ($sql, $fetch, $mod, $table, $pkey, $expire) {
                    $this->DB_page();
                    $sql .= $this->DB_sql(true);
                    $rows = $this->PDO->Stmt($sql, $this->DB_BIND)->Rows($fetch);
                    $this->SetCache($mod, $this->DB_CONFIG['dbname'], $table, $pkey, $this->DB_PAGED, $expire);
                    $rows && $this->DB_CHAIN && $this->QueryChain($rows, $this->DB_CHAIN);
                    $this->DB_done();
                    return $rows;
                }, $expire);
                $result = $res[1];
            } else {
                $this->DB_PAGED = $paged;
            }
        } else {
            $sql .= $this->DB_sql(true);
            list($table, $key) = $this->CacheKey('2', $fetch, $sql);
            $result = $this->GetCache($mod, $this->DB_CONFIG['dbname'], $table, $key);
            if (null === $result) {
                $res = $this->SetCache($mod, $this->DB_CONFIG['dbname'], $table, $key, function () use ($sql, $fetch) {
                    $stmt = $this->PDO->Stmt($sql, $this->DB_BIND);
                    return $stmt->Rows($fetch);
                }, $expire);
                $result = $res[1];
            }
        }
        return $result;
    }
    public function Select(string $field = null, bool $lock = false): array
    {
        $fetch = \PDO::FETCH_ASSOC;
        $field && ($this->DB_FIELD = $this->DB_wrapFileds($field)) && $fetch = \PDO::FETCH_COLUMN;
        if ($lock || !$this->Z_CACHE) {
            $field = $this->DB_field();
            $this->DB_PAGE && $this->DB_page();
            $sql = "SELECT {$field} FROM " . $this->DB_sql();
            $lock && $sql = $this->DB_lockRows($sql);
            if (null === $sql) {
                throw new Exception('Row lock is not supported');
            }
            $q = $this->PDO->Stmt($sql, $this->DB_BIND);
            $result = $q->Rows($fetch);
            $result && $this->DB_CHAIN && $this->QueryChain($result, $this->DB_CHAIN);
        } else {
            $result = $this->CacheSelect($fetch);
        }
        $this->DB_done();
        return $result;
    }

    public function Insert(array $data, bool $ignore = false, bool $call = true): string|array|bool
    {
        //$ignore=true:主键重复则不执行
        $table = $this->DB_table();
        if ($ignore && !$q = $this->DB_iinsert($table, $data)) {
            if (null === $q) {
                throw new Exception('Row lock is not supported');
            }
            return false;
        } elseif (!$sql = $this->DB_bindData($data, 1)) {
            return false;
        } else {
            $q = $this->PDO->Stmt("INSERT INTO {$table} {$sql}", $this->DB_BIND);
        }
        if ($result = $q->Exec() && $q->RowCount()) {
            if (isset($this->DB_BASE[$this->DB_TABLE]['prikey']) && is_array($this->DB_BASE[$this->DB_TABLE]['prikey'])) {
                $result = [];
                foreach ($this->DB_BASE[$this->DB_TABLE]['prikey'] as $key) {
                    $result[$key] = $data[$key] ?? $q->LastInsertId($key);
                }
            } else {
                $result = $q->LastInsertId() ?: true;
            }
            $call && $this->DB_call('insert', ['result' => $result, 'data' => $data, 'sql' => $sql, 'bind' => $this->DB_BIND]);
        }
        $this->DB_done();
        return $result;
    }

    // 批量插入数据
    public function BatchInsert(array $keys, array $data, bool $ignore = false): array
    {
        $table = $this->DB_table();
        $values = str_repeat('?,', count($keys) - 1) . '?';
        $keys = $this->Wrap($keys);
        $ignore && $ignore = $this->DB_ibatchInsert($data);
        if (null === $ignore) {
            throw new Exception('IGNORE is not supported');
        }
        $sql = "INSERT{$ignore} INTO {$table} ({$keys}) VALUES({$values})";
        $q = $this->PDO->Stmt($sql);
        return $q->Batch($data, true);
    }

    public function Update(array $data, bool $call = true): int|false
    {
        $table = $this->DB_table();
        $where = $this->DB_WHERE ? $this->DB_sqlWhere() : '';
        $join = $this->DB_JOIN ? $this->DB_sqlJoin() : '';
        if (!$sql = $this->DB_bindData($data, 0)) {
            return false;
        }
        $sql = "UPDATE {$table}{$join} SET {$sql}{$where}";
        $q = $this->PDO->Stmt($sql, $this->DB_BIND);
        $result = $q->Exec() ? $q->RowCount() : false;
        $call && $result && $this->DB_call('update', ['result' => $result, 'where' => $this->DB_WHERE, 'data' => $data, 'sql' => $sql, 'bind' => $this->DB_BIND]);
        $this->DB_done();
        return $result;
    }

    public function IfUpdate(array $insert, array $update = null, bool $call = true): string|bool|int
    {
        $result = $this->DB_duplicate($insert, $update);
        if (null === $result) {
            throw new Exception('DUPLICATE is not supported');
        }
        $call && $result && $this->DB_call('ifupdate', ['result' => $result, 'insert' => $insert, 'update' => $update, 'sql' => $this->PDO->GetSql(), 'bind' => $this->DB_BIND]);
        $this->DB_done();
        return $result;
    }

    protected function DB_call(string $name, array $params): void
    {
        if ($this->PDO->inTransaction()) {
            foreach ($this->DB_TABLES as $table => $a) {
                if ($act = $this->DB_BASE[$table]['call'][$name] ?? false) {
                    $this->DB_CALL[] = [$act, $params];
                }
            }
        } else {
            foreach ($this->DB_TABLES as $table => $a) {
                if ($call = $this->DB_BASE[$table]['call'][$name] ?? null) {
                    is_callable($call) && $call($params, $this);
                }
            }
        }
    }
    public function Delete(string $alias = '', bool $call = true): int|false
    {
        $alias && $alias = " {$alias}";
        $table = $this->DB_table();
        $where = $this->DB_WHERE ? $this->DB_sqlWhere() : '';
        $join = $this->DB_JOIN ? $this->DB_sqlJoin() : '';
        $sql = "DELETE{$alias} FROM {$table}{$join}{$where}";
        $q = $this->PDO->Stmt($sql, $this->DB_BIND);
        $result = $q->Exec() ? $q->RowCount() : false;
        $call && $result && $this->DB_call('delete', ['result' => $result, 'where' => $this->DB_WHERE, 'sql' => $sql, 'bind' => $this->DB_BIND]);
        $this->DB_done();
        return $result;
    }

    public function Page(array $params) :static
    {
        $this->DB_PAGE = $params;
        return $this;
        /**
         * $params[
         *  'p' => 当前页码, 默认：$_GET[$params['var']] ?? 1
         *  'num' => 每页的数据量, 默认：10
         *  'max' => 最大页码数, 默认：0（不限制）
         *  'var' => 参数名($_GET[var]), 默认：p
         *  'mod' => url模式, 默认：当前模式
         *  'nourl' => 空链接的地址, 默认：javascript:;
         *  'return' => 需要返回的参数：默认：无
         *             [
         *               'prev',   上一页
         *               'next',   下一页
         *               'first',  第一页
         *               'last',   最后一页
         *               'list'    分页列表
         *             ]
         * ]
         */
    }

    public function GetWhereByKey(string $key, array $where = []): mixed
    {
        if ($where || $where = $this->DB_WHERE) {
            $preg0 = "/{$key}\\{$this->DB_WRAP_R}?\s*=\s*([^\s]+)/";
            $preg1 = "/{$key}\\{$this->DB_WRAP_R}?\s+IN\s*\(([^\)]+)\)/i";
            foreach ($where as $w) {
                if (is_array($w[0])) {
                    $val = $w[0][$key] ?? $w[0]["{$key} IN"] ?? $w[0]["{$key} in"] ?? null;
                    if (isset($val)) {
                        break;
                    }
                } elseif (preg_match($preg0, $w[0], $match) && isset($match[1])) {
                    $val = trim($match[1], "'\"");
                    break;
                } elseif (preg_match($preg1, $w[0], $match)) {
                    if (isset($match[1]) && $arr = explode(',', $match[1])) {
                        foreach ($arr as $v) {
                            $val[] = trim($v, " '\"");
                        }
                        break;
                    }
                }
            }
        }
        return $val ?? null;
    }

    protected function DB_wrapFileds (string $str): string
    {
        $preg = '/(\w+\.)?\b(?<!' . preg_quote($this->DB_WRAP_L) . ')(\w+)(?!' . preg_quote($this->DB_WRAP_R) . ')\b(\s*\()?/';
        
        return preg_replace_callback($preg, function ($match) {
            return isset($match[3]) || (isset($match[2]) && 'AS' === strtoupper($match[2])) ? $match[0] : "{$match[1]}{$this->DB_WRAP_L}{$match[2]}{$this->DB_WRAP_R}";
        }, $str);
    }

    protected function DB_page(): void
    {
        if (empty($this->DB_PAGE['return'])) {
            if (!$this->DB_PAGED) {
                $this->DB_PAGED = Page($this->DB_PAGE);
                $this->DB_LIMIT = $this->DB_PAGED['limit'];
            }
            return;
        }
        $cfg = $this->DB_PAGE;
        if (!empty($cfg['max']) && !$this->DB_WHERE && !$this->DB_JOIN) {
            $table = $this->DB_PREFIX . $this->DB_TABLE;
            $cfg['num'] ?? $cfg['num'] = 10;
            $maxRows = $cfg['max'] * $cfg['num'];
            $this->DB_approximateRows($table) > $maxRows && $cfg['total'] = $maxRows;
        }
        $cfg['total'] ?? $cfg['total'] = $this->Count('*', false);
        $this->DB_PAGED = Page($cfg, $this->DB_PAGE['return']);
        $this->DB_LIMIT = $this->DB_PAGED['limit'];
    }

    protected function DB_done(): void
    {
        $this->DB_FIELD = '*';
        $this->DB_PAGE = [];
        $this->DB_WHERE = [];
        $this->DB_WHERED = '';
        $this->DB_JOIN = [];
        $this->DB_JOIND = '';
        $this->DB_JOINMAP = [];
        $this->DB_BIND = [];
        $this->DB_GROUP = '';
        $this->DB_ORDER = '';
        $this->DB_LIMIT = '';
        $this->DB_HAVING = [];
        $this->Z_CACHE = [];
        $this->DB_SQLD = '';
        $this->DB_MERGE = '';
        $this->DB_CHAIN = [];
    }

    protected function DB_field(): string
    {
        if (!$this->DB_TABLES) {
            return $this->DB_FIELD;
        }

        if ('*' === $this->DB_FIELD) {
            $field = '';
            $fields = [];
            $alias = [];
            foreach ($this->DB_TABLES as $table => $a) {
                if (empty($this->DB_BASE[$table]['columns'])) {
                    return '*';
                }

                $a = $table === $a ? $this->DB_WRAP_L : "{$this->DB_WRAP_L}{$a}.";
                if (empty($this->DB_BASE[$table]['alias'])) {
                    $field .= $a . implode("{$this->DB_WRAP_R},{$a}", $this->DB_BASE[$table]['columns']) . $this->DB_WRAP_R . ',';
                } else {
                    foreach ($this->DB_BASE[$table]['columns'] as $v) {
                        $as = $this->DB_BASE[$table]['alias'][$v] ?? false;
                        $v = $a ? "{$a}{$v}" : "{$this->DB_WRAP_L}{$v}{$this->DB_WRAP_R}";
                        if ($as && !isset($alias[$as])) {
                            $field .= "{$v} {$this->DB_WRAP_L}{$as}{$this->DB_WRAP_R},";
                            $alias[$as] = 1;
                        } elseif (!isset($fields[$v])) {
                            $field .= "{$v},";
                            $fields[$v] = 1;
                        }
                    }
                }
            }
            $field = rtrim($field, ',');
        } else {
            $alias = [];
            foreach ($this->DB_TABLES as $table => $a) {
                if (isset($this->DB_BASE[$table]['alias'])) {
                    $a = $table === $a ? '' : "{$a}.";
                    foreach ($this->DB_BASE[$table]['alias'] as $k => $v) {
                        if (!isset($alias[$v])) {
                            $find[] = "{$k}{$this->DB_WRAP_R},";
                            $replace[] = "{$a}{$this->DB_WRAP_L}{$v}{$this->DB_WRAP_R},";
                            $alias[$v] = 1;
                        }
                    }
                }
            }
            $field = isset($find) ? rtrim(str_replace($find, $replace, $this->DB_FIELD . ','), ',') : $this->DB_FIELD;
        }
        return $field;
    }

    protected function DB_bindData(array $data, int $act = 0): string
    {
        $columns = [];
        foreach ($this->DB_TABLES as $table => $a) {
            $as = $table === $a ? '' : $a;
            if (isset($this->DB_BASE[$table]['columns'])) {
                if ($as) {
                    foreach ($this->DB_BASE[$table]['columns'] as $v) {
                        $columns[$v] = true;
                        $columns["{$as}.{$v}"] = true;
                    }
                } else {
                    foreach ($this->DB_BASE[$table]['columns'] as $v) {
                        $columns[$v] = true;
                    }
                }
            }
        }
        // [':field1'=>'`field2` + 10'] // 字段前加: 表示绑定sql而不是值
        foreach ($data as $k => $v) {
            if ($columns && !isset($columns[$k])) {
                continue;
            }
            $isSqlValue = false;
            if (is_string($v) && ':' === $k[0]) {
                $isSqlValue = true;
                $k = substr($k, 1);
            }
            $_key = $this->Wrap($k);
            $keys[] = $_key;
            if ($isSqlValue) {
                $sets[] = "{$_key} = " . $this->DB_wrapFileds($v);
                $values[] = $v;
            } else {
                $bind[] = $v;
                $sets[] = "{$_key}=?";
                $values[] = '?';
            }
        }
        if (!isset($keys)) {
            throw new \PDOException("Binding parameter error, no fields can be added or updated. Please check the columns of base file are correct");
        }
        if ($act) {
            isset($bind) && array_push($this->DB_BIND, ...$bind);
            return '(' . implode(',', $keys) . ') VALUES (' . implode(',', $values) . ')';
        }
        isset($bind) && array_unshift($this->DB_BIND, ...$bind);
        return implode(',', $sets);
    }

    protected function DB_table(): string
    {
        if ($this->DB_TMP) {
            return $this->DB_TMP;
        }

        if (!$this->DB_TABLED) {
            if (str_contains($this->DB_TABLE, ',')) {
                $table = explode(',', $this->DB_TABLE);
                foreach ($table as $v) {
                    $v = trim($v);
                    if (str_contains($v, ' ')) {
                        $tableName_arr = explode(' ', $v);
                        $tableName = array_shift($tableName_arr);
                        $tableArr[] = "{$tableName}{$this->DB_WRAP_R} " . implode(' ', $tableName_arr);
                        $this->DB_TABLES[$tableName] = end($tableName_arr);
                    } else {
                        $tableArr[] = "{$v}{$this->DB_WRAP_R}";
                        $this->DB_TABLES[$v] = $v;
                    }
                }
                $tabled = "{$this->DB_WRAP_L}{$this->DB_PREFIX}" . implode(",{$this->DB_WRAP_L}{$this->DB_PREFIX}", $tableArr);
            } else {
                if (str_contains($this->DB_TABLE, ' ')) {
                    $tableName_arr = explode(' ', $this->DB_TABLE);
                    $tableName = array_shift($tableName_arr);
                    $this->DB_TABLES[$tableName] = end($tableName_arr);
                    $tabled = "{$this->DB_WRAP_L}{$this->DB_PREFIX}{$tableName}{$this->DB_WRAP_R} " . implode(' ', $tableName_arr);
                } else {
                    $this->DB_TABLES[$this->DB_TABLE] = $this->DB_TABLE;
                    $tabled = "{$this->DB_WRAP_L}{$this->DB_PREFIX}{$this->DB_TABLE}{$this->DB_WRAP_R}";
                }
            }
            $this->DB_TABLED = $tabled;
        }
        return $this->DB_TABLED;
    }
    protected function DB_sql(bool $r = false, bool $count = false): string
    {
        if ($r || !$this->DB_SQLD) {
            $table = $this->DB_table();
            $join = $this->DB_JOIN ? $this->DB_sqlJoin() : '';
            $where = $this->DB_WHERE ? $this->DB_sqlWhere() : '';
            $having = $this->DB_HAVING ? $this->DB_sqlHaving() : '';
            $group = $this->DB_GROUP ? " GROUP BY {$this->DB_GROUP}" : '';
            $limit = !$count && $this->DB_LIMIT ? " LIMIT {$this->DB_LIMIT}" : '';
            $order = !$count && $this->DB_ORDER ? " ORDER BY {$this->DB_ORDER}" : '';
            $sql = $table . $join . $where . $group . $having . $order . $limit;
            $this->DB_MERGE && $sql .= $this->DB_MERGE;
            if ('mysql' !== $this->DB_CONFIG['drive']) {
                $sql = preg_replace('/(?<!\\\\)`(\w+)`/', $this->DB_WRAP_L . '$1' . $this->DB_WRAP_R, $sql);
                $sql = str_replace('\`', '`', $sql);
            }
            $this->DB_SQLD = $sql;
        }
        return $this->DB_SQLD;
    }

    protected function DB_sqlJoin(): string
    {
        if ($this->DB_JOIN && !$this->DB_JOIND) {
            $SQL = [];
            foreach ($this->DB_JOIN as $join) {
                stristr($join, 'join', true) || $join = "INNER JOIN {$join}";
                $preg = '/(.+join\s+)(\w+)\s+(as\s+)?(\w+)(.+)/i';
                $sql = preg_replace_callback($preg, function ($match) {
                    $this->DB_JOINMAP[$match[4]] = $match[2];
                    $this->DB_TABLES[$match[2]] = $match[4];
                    return "{$match[1]}{$this->DB_WRAP_L}{$this->DB_PREFIX}{$match[2]}{$this->DB_WRAP_R} {$match[4]}{$match[5]}";
                }, $join);
                $SQL[] = $sql ?: $join;
            }
            $this->DB_JOIND = ' ' . implode(' ', $SQL);
        }
        return $this->DB_JOIND;
    }

    protected function DB_sqlHaving(): string
    {
        if ($this->DB_HAVING && !$this->DB_HAVINGD) {
            if (is_array($this->DB_HAVING[0])) {
                $Q = $this->DB_whereArr($this->DB_HAVING[0]);
                $sql = $Q[0];
            } else {
                $sql = $this->DB_HAVING[1] ? $this->DB_whereStr($this->DB_HAVING[0], $this->DB_HAVING[1]) : $this->DB_HAVING[0];
            }
            $this->DB_HAVINGD = ' HAVING ' . $sql;
        }
        return $this->DB_HAVINGD;
    }

    protected function DB_sqlWhere(): string
    {
        if ($this->DB_WHERE && !$this->DB_WHERED) {
            $sql = '';
            foreach ($this->DB_WHERE as $where) {
                $logic = $sql && !empty($where[2]) ? "{$where[2]} " : '';
                $logic && $sql .= " {$logic}(";
                if (is_array($where[0])) {
                    $Q = $this->DB_whereArr($where[0]);
                    $sql .= !$logic && $sql ? " {$Q[1]}({$Q[0]})" : $Q[0];
                } else {
                    $sql .= empty($where[1]) ? $where[0] : $this->DB_whereStr($where[0], $where[1]);
                }
                $logic && $sql .= ')';
            }
            $this->DB_WHERED = ' WHERE ' . $sql;
        }
        return $this->DB_WHERED;
    }

    protected function DB_whereStr(string $where, array $bind): string
    {
        $i = 0;
        foreach ($bind as $k => $v) {
            if ($k == $i) {
                $this->DB_BIND[] = $v;
                ++$i;
            } else {
                $this->DB_BIND[$k] = $v;
            }
        }
        return $where;
    }

    protected function DB_whereArr(array $where): array
    {
        $sql = '';
        $lc = '';
        foreach ($where as $k => $value) {
            list($isSqlValue, $logic, $key, $operator) = $this->DB_checkKey($k);
            $logic || $logic = 'AND ';
            $lc || $lc = $logic;
            if (is_array($value)) {
                if (!$value) {
                    continue;
                }
                $operator = match ($operator) {
                    '<>', '!=' => 'NOT IN',
                    default => $operator ?: 'IN',
                };
            } elseif (is_string($value) && 'SELECT' === strtoupper(substr($value, 0, 6))) {
                // 包含子查询
                $operator || $operator = 'IN';
                $sql && $sql .= " {$logic}";
                $sql .= "{$key} {$operator} ({$value})";
                continue;
            }

            if (is_array($key)) {
                $subSql = [];
                foreach ($key as $kk) {
                    $subSql[] = $isSqlValue ? $this->DB_bindSqlWhere($kk, $value, $operator) : $this->DB_bindWhere($kk, $value, $operator);
                }
                $sql && $sql .= " {$logic}";
                $sql .= '(' . implode(' OR ', $subSql) . ')';
            } else {
                $sql && $sql .= " {$logic}";
                $sql .= $isSqlValue ? $this->DB_bindSqlWhere($key, $value, $operator) : $this->DB_bindWhere($key, $value, $operator);
            }
        }
        return [$sql, $lc];
    }
    protected function DB_bindSqlWhere(string $key, string $value, ?string $operator = null): string
    {
        $_key = $this->Wrap(trim($key));
        return $operator ? "{$_key} {$operator} {$value}" : "{$_key} {$value}";
    }


    protected function DB_bindWhere(string $key, $value, ?string $operator = null): string
    {
        $key = trim($key);
        
        $_key = $this->Wrap($key);
        if (is_array($value)) {
            array_push($this->DB_BIND, ...$value);
            if (str_contains($operator, 'BETWEEN')) {
                $where = "{$_key} {$operator} ? AND ?";
            } else {
                $operator || $operator = 'IN';
                if ($count = count($value)) {
                    $b = '?';
                    $count > 1 && $b .= str_repeat(',?', $count - 1);
                } else {
                    $b = '';
                }
                $where = "{$_key} {$operator} ({$b})";
            }
        } else {
            $operator || $operator = '=';
            $this->DB_BIND[] = $value;
            $where = "{$_key} {$operator} ?";
        }
        return $where;
    }

    protected function DB_checkKey(string $key): array
    {
        $isSqlValue = false;
        $operator = '';
        $logic = '';
        $preg = '/^(OR\s+|AND\s+)?((\:?\w+)\s*([\<\>\=\!]{0,2})|.+)$/i';
        if (preg_match($preg, $key, $match)) {
            $logic = empty($match[1]) ? '' : $match[1];
            if (!empty($match[3])) {
                $keys = $match[3];
                $operator = $match[4] ?? '=';
            } else {
                $keys = $match[2];
            }
        } else {
            $isSqlValue = false;
            $keys = $key;
        }
        
        if (':' === $keys[0]) {
            // [':aa'=>'IS NOT NULL', ':DATE_FORMAT(`dd`, "%Y-%m-%d")'=>'"2022-12-22"' // 字段前加: 表示绑定sql而不是值
            $isSqlValue || $isSqlValue = true;
            $keys = substr($keys, 1);
        }
        return [$isSqlValue, $logic, $keys, $operator];
    }
}
