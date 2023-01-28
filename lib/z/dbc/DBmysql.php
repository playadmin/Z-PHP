<?php
declare(strict_types=1);
namespace lib\z\dbc;

class DBmysql extends db
{
    const WRAP_L = '`', WRAP_R = '`';

    protected array $COLUMNS = [];
    protected array $PRIKEYS = [];

    protected function DB_approximateRows (string $table): int
    {
        $Status = $this->PDO->Stmt("show table Status WHERE Name = '{$table}'")->Row();
        return (int)$Status['Rows'];
    }

    function Columns (string $table): array | false
    {
        /**
         * Field    Type                Null    Key         Default     Extra
         * ID	    int(10) unsigned	NO	    PRI     	NULL	    auto_increment
         */
        if (empty($this->COLUMNS[$table])) {
            $sql = "SHOW COLUMNS FROM `{$table}`";
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
            if ($v['Field'] === $field) {
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
            $pks = [];
            $cols = $this->Columns($table);
            foreach ($cols as $col) {
                if ('PRI' === $col['Key']) {
                    $pks[] = $col['Field'];
                }
            }
            $this->PRIKEYS[$table] = 1 === count($pks) ? $pks[0] : $pks;
        }
        if (empty($this->PRIKEYS[$table])) {
            return '';
        }
        return $this->PRIKEYS[$table];
    }

    protected function DB_lockRows (string $sql): string
    {
        return "{$sql} FOR UPDATE";
    }

    // 统计数据量（忽略重复）
    protected function DB_dcount (string $from, string $sfield, string $field): int|false
    {
        $sql = "SELECT COUNT({$sfield}) FROM {$from}";
        return $this->PDO->Stmt($sql, $this->DB_BIND)->Row(\PDO::FETCH_COLUMN);
    }

    // 插入数据（数据已存在时不插入）
    protected function DB_iinsert(string $table, array $data): stmt|false
    {
        if (!$sql = $this->DB_bindData($data, 1)) {
            return false;
        }
        $sql = "INSERT IGNORE INTO {$table} {$sql}";
        return $this->PDO->Stmt($sql, $this->DB_BIND);
    }

    // 批量插入数据（忽略已存在数据）
    protected function DB_ibatchInsert(array &$data): string
    {
        // 不支持的情况下可先查询主键是否存在: $this->PriKey($this->DB_TABLE)
        // 然后删除$data中的已存在数据或重建$data，返回空字符串
        return ' IGNORE';
    }

    // 存在数据则更新，否则就插入新数据
    protected function DB_duplicate(array $insert, array $update = null): array|string|bool|int
    {
        // 不支持的情况下可查询主键是否存在: $this->PriKey($this->DB_TABLE)
        $table = $this->DB_table();
        $update || $update = $insert;
        if ((!$update_sql = $this->DB_bindData($update, 0)) || (!$add_sql = $this->DB_bindData($insert, 1))) {
            return false;
        }

        $sql = "INSERT INTO {$table} {$add_sql} ON DUPLICATE KEY UPDATE {$update_sql}";
        $q = $this->PDO->Stmt($sql, $this->DB_BIND);
        if (!$q->Exec()) {
            return false;
        }
        $result = match ($q->RowCount()) {
            1 => $this->PDO->LastInsertId() ?: true,
            2 => -1,
            default => 0,
        };
        return $result;
    }

}
