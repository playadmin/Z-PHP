<?php
declare(strict_types=1);
namespace lib\z\dbc;

class DBsqlite extends db
{
    const WRAP_L = '`', WRAP_R = '`';

    protected array $COLUMNS = [];
    protected array $PRIKEYS = [];

    protected function DB_approximateRows (string $table): int
    {
        return -1;
    }

    function Columns (string $table): array | false
    {
        /**
         * cid    name      type        notnull     dflt_value     pk
         * 0        ID      INTEGER	    1     	    NULL            1
         */
        if (empty($this->COLUMNS[$table])) {
            $sql = "PRAGMA table_info(`{$table}`)";
            $this->COLUMNS[$table] = $this->PDO->QueryAll($sql);
        }
        return $this->COLUMNS[$table];
    }
    function Column (string $table, string $field): array | false
    {
        if (!$fields = $this->Columns($table)) {
            return false;
        }
        foreach ($fields as $v) {
            if ($v['name'] === $field) {
                return $v;
            }
        }
        return false;
    }
    function PriKey (?string $table = null): array|string
    {
        $table || $table = $this->DB_TABLE;
        $pk = $this->DB_BASE[$table]['prikey'];
        if ($pk) {
            return $this->DB_BASE[$table]['prikey'];
        } elseif (null !== $pk) {
            return '';
        }
        if (!isset($this->PRIKEYS[$table])) {
            $cols = $this->Columns($table);
            $pks = [];
            foreach ($cols as $col) {
                if ($col['pk']) {
                    $pks[] = $col['name'];
                }
            }
            $this->PRIKEYS[$table] = 1 === count($pks) ? $pks[0] : $pks;
        }
        if (empty($this->PRIKEYS[$table])) {
            return '';
        }
        return $this->PRIKEYS[$table];
    }

    protected function DB_lockRows (string $sql, int|bool $lockExpire = null): string|null
    {
        return null;
    }

    // 统计数据量（忽略重复）
    protected function DB_dcount (string $from, string $sfield, string $field): int|false
    {
        $sql = "SELECT COUNT({$sfield}) FROM {$from}";
        return $this->PDO->Stmt($sql, $this->DB_BIND)->Row(\PDO::FETCH_COLUMN);
    }

    protected function exist (array $data, ?string $table = null, bool $returnWhere = false): array|int
    {
        $table || $table = $this->DB_TABLE;
        if (!$prikey = $this->PriKey($table)) {
            throw new \Exception('Missing primary key');
        }
        $where = [];
        $bind = [];
        $qw = [];
        if (is_array($prikey)) {
            foreach ($prikey as $k) {
                if (isset($data[$k])) {
                    $where[] = "`{$k}`=?";
                    $bind[] = $data[$k];
                    $qw[$k] = $data[$k];
                }
            }
            $sql = "SELECT COUNT(*) FROM `{$table}` WHERE " . implode(' AND ', $where);
        } elseif (isset($data[$prikey])) {
            $bind[] = $data[$prikey];
            $qw[$prikey] = $data[$prikey];
            $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$prikey}`=?";
        }
        if (empty($sql)) {
            throw new \Exception('Missing primary key or data');
        }

        $q = $this->PDO->Stmt($sql, $bind);
        $n = intval($q->Row(\PDO::FETCH_COLUMN));
        return $returnWhere ? ($n ? $qw : []) : $n;
    }

    // 插入数据（数据已存在时不插入）
    protected function DB_iinsert(string $table, array $data): stmt|false
    {
        if ($this->exist($data, $table)) {
            return false;
        }
        if (!$sql = $this->DB_bindData($data, 1)) {
            return false;
        }
        $sql = "INSERT INTO {$table} {$sql}";
        return $this->PDO->Stmt($sql, $this->DB_BIND);
    }

    // 批量插入数据（忽略已存在数据）
    protected function DB_ibatchInsert(array &$data): string
    {
        // 不支持的情况下可先查询主键是否存在: $this->PriKey($this->DB_TABLE)
        // 然后删除$data中的已存在数据或重建$data，返回空字符串
        if (!$prikey = $this->PriKey($this->DB_TABLE)) {
            throw new \Exception('Missing primary key');
        }
        $where = [];
        $bind = [];
        if (is_array($prikey)) {
            foreach ($prikey as $k) {
                if (isset($data[0][$k])) {
                    $values = array_column($data, $k);
                    $where[] = "`{$k}` IN(" . str_repeat(',?', count($values) - 1) . ')';
                    array_push($bind, ...$values);
                }
            }
            $fields = '`' . implode('`,`', $prikey) . '`';
            $sql = "SELECT {$fields} FROM `{$this->DB_TABLE}` WHERE " . implode(' AND ', $where);
        } elseif (isset($data[0][$prikey])) {
            $values = array_column($data, $prikey);
            $sql = "SELECT `{$prikey}` FROM `{$this->DB_TABLE}` WHERE `{$prikey}` IN(" . str_repeat(',?', count($values) - 1) . ')';
            array_push($bind, ...$values);
        }

        if ($sql && $res = $this->PDO->Stmt($sql, $bind)->Rows(\PDO::FETCH_COLUMN)) {
            $map = [];
            if (is_array($prikey)) {
                foreach ($res as $v) {
                    $map[implode("\r\n", $v)] = true;
                }
                foreach ($data as $k=>$v) {
                    $pk = [];
                    foreach($prikey as $p) {
                        isset($v[$p]) && $pk[] = $v[$p];
                    }
                    if ($pk && ($pk = implode("\r\n", $pk)) && isset($map[$pk])) {
                        unset($data[$k]);
                    }
                }
            } else {
                foreach ($res as $v) {
                    $map[$v[$prikey]] = true;
                }
                foreach ($data as $k=>$v) {
                    if (($pk = $v[$prikey] ?? '') && isset($map[$pk])) {
                        unset($data[$k]);
                    }
                }
            }
        }
        return '';
    }

    // 存在数据则更新，否则就插入新数据
    protected function DB_duplicate(array $insert, array $update = null): array|string|bool|int
    {
        $table = $this->DB_table();
        if ($where = $this->exist($insert, $this->DB_TABLE, true)) {
            $update || $update = $insert;
            $this->DB_WHERE[] = [$where, null];
            $where = $this->DB_sqlWhere();
            $sql = $this->DB_bindData($update, 0);
            $sql = "UPDATE {$table} SET {$sql}{$where}";
            $q = $this->PDO->Stmt($sql, $this->DB_BIND);
            $result = $q->Exec() && $q->RowCount() ? -1 : 0;
        } else {
            $sql = $this->DB_bindData($insert, 1);
            $q = $this->PDO->Stmt("INSERT INTO {$table} {$sql}", $this->DB_BIND);
            if ($result = $q->Exec() && $q->RowCount()) {
                $prikey = $this->PriKey($this->DB_TABLE);
                if ($prikey && is_array($prikey)) {
                    $result = [];
                    foreach ($prikey as $key) {
                        $result[$key] = $data[$key] ?? $q->LastInsertId($key);
                    }
                } else {
                    $result = $q->LastInsertId() ?: true;
                }
            }
        }
        return $result;
    }

}
