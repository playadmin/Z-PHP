<?php
declare(strict_types=1);

namespace lib\z\dbc;

use Exception;

class dbc {
    private static array $instances = [];

    public static function Init (?array $cfg = null): pdo {
        if (($null = !$cfg) && !$cfg = $GLOBALS['ZPHP_CONFIG']['DB'] ?? null) {
            throw new Exception('缺少数据库配置文件');
        }
        $key = $null ? '' : pdo::InitCfg($cfg);
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new pdo($cfg);
        }
        return self::$instances[$key];
    }
}

class pdo {
    private $isTry = false;
    protected int $pdoIndex = -1;
    protected int $readerIndexs = 0;
    protected string $SQL = '';
    protected array $pdoCfg = [], $instanceMap = [], $instances = [], $pdoParams = [];

    public function __construct(
        array|null $cfg,
    ){
        if (!$cfg && !$cfg = $GLOBALS['ZPHP_CONFIG']['DB'] ?? null) {
            throw new Exception('缺少数据库配置文件');
        }
        $this->pdoCfg = isset($cfg[0]) ? $cfg : [$cfg];
        isset($this->pdoCfg[0]['__key']) || self::InitCfg($this->pdoCfg);
        $this->readerIndexs = count($this->pdoCfg) - 1;
    }

    public static function InitCfg (array &$cfg): string {
        if (isset($cfg[0])) {
            $keys = [];
            foreach($cfg as &$c) {
                $c['dsn'] ?? $c['dsn'] = self::dsn($c);
                if (empty($c['dsn'])) {
                    $c['dsn'] = self::dsn($c);
                } elseif (empty($c['drive'])) {
                    $c['drive'] = strstr($c['dsn'], ':', true);
                }
                $key = md5($c['dsn'] . '|' . ($c['user'] ?? ''));
                $c['__key'] = $key;
                $keys[] = $key;
            }
            $s = md5(implode(',', $keys));
        } else {
            if (empty($cfg['dsn'])) {
                $cfg['dsn'] = self::dsn($cfg);
            } elseif (empty($cfg['drive'])) {
                $cfg['drive'] = strstr($cfg['dsn'], ':', true);
            }
            $key = md5($cfg['dsn'] . '|' . ($cfg['user'] ?? ''));
            $s = $cfg['__key'] = $key;
        }
        return $s;
    }

    private static function dsn (array $c): string
    {
        return match ($c['drive']) {
            'pgsql' => "pgsql:host={$c['host']};dbname={$c['dbname']};port={$c['port']}",
            'sqlsrv'=> "sqlsrv:Server={$c['host']},{$c['port']};Database={$c['dbname']}",
            'sqlite'=> "sqlite:{$c['file']}",
            'oci' => "oci:dbname={$c['dbname']}",
            default => "mysql:host={$c['host']};dbname={$c['dbname']};port={$c['port']}",
        };
    }

    public function Read (bool $reConnect = false): \PDO
    {
        if ($this->pdoIndex < 0) {
            $this->pdoIndex = $this->readerIndexs > 1 ? rand(1, $this->readerIndexs) : $this->readerIndexs;
        }
        return $this->Conn($this->pdoIndex, $reConnect);
    }

    public function Writer (bool $reConnect = false): \PDO
    {
        return $this->Conn(0, $reConnect);
    }

    public function Conn (int $index, bool $reConnect): \PDO
    {
        if ($reConnect || !isset($this->instances[$index])) {
            if (!$c = $this->pdoCfg[$index] ?? null) {
                throw new Exception("数据库配置错误: {$index}");
            }
            $h = $this->GetConnect($c, $reConnect);
            $this->instances[$index] = &$h;
        }
        $this->pdoIndex = $index;
        return $this->instances[$index];
    }

    public function Connect (array $c): \PDO {
        $config = [
            \PDO::ATTR_PERSISTENT => $c['persistent'] ?? false, // 长连接
            \PDO::ATTR_TIMEOUT => $c['timeout'] ?? 10,
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . ($c['charset'] ?? 'utf8'),
            \PDO::ATTR_EMULATE_PREPARES => $c['emulate'] ?? false, //是否模拟预处理
            \PDO::ATTR_STRINGIFY_FETCHES => $c['stringify'] ?? false, //是否将数值转换为字符串
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ];
        return new \PDO($c['dsn'], $c['user'] ?? null, $c['pass'] ?? null, $config);
    }

    public function GetConnect(array $c, bool $reConnect = false): \PDO
    {
        if ($reConnect || !isset($this->instanceMap[$c['__key']])) {
            isset($this->instanceMap[$c['__key']]) && $this->instanceMap[$c['__key']] = null;
            $mtime = microtime(true);
            $this->instanceMap[$c['__key']] = $this->Connect($c);
            if (DEBUGER && $GLOBALS['ZPHP_CONFIG']['DEBUG']['level']) {
                $time = microtime(true) - $mtime;
                (DEBUGER)::pdotime($time);
                (DEBUGER)::setMsg(1120, "CONNECT [{$c['dsn']}] : " . round(1000 * $time, 3) . 'ms');
            }
        }
        return $this->instanceMap[$c['__key']];
    }

    public function Retry (\PDOException $e, array $bind = null): bool
    {
        if ($this->isTry) {
            throw $e;
        }
        if (DEBUGER && $GLOBALS['ZPHP_CONFIG']['DEBUG']['level']) {
            $msg = "{$this->SQL}; " . str_replace(['"', ','], ['\'', ', '], json_encode($bind, JSON_ENCODE_CFG)) . ' error';
            (DEBUGER)::setMsg(1120, $msg);
        }
        $this->isTry = true;
        $code = $e->getCode();
        if (
            ('0' === $code[0] && '8' === $code[1]) ||
            ('HY000' === $code)
        ) {
            return true;
        } else {
            throw $e;
        }
        return false;
    }

    public function sumTime (float $mtime, string $Mark = ''): void
    {
        if (DEBUGER && (int)$GLOBALS['ZPHP_CONFIG']['DEBUG']['level']) {
            $time = microtime(true) - $mtime;
            (DEBUGER)::pdotime($time);
            $msg = preg_replace('/\s/', ' ', $this->SQL) . '; ' . str_replace(['"', ','], ['\'', ', '], json_encode($this->pdoParams, JSON_ENCODE_CFG)) . ' : ' . round(1000 * $time, 3) . "ms{$Mark}";
            (DEBUGER)::setMsg(1120, $msg);
        }
    }

    public function GetConfig (int $index = 0): array {
        return $this->pdoCfg[$index];
    }

    public function GetSql (): string {
        return $this->SQL;
    }
    public function Exec (string $sql)
    {
        $mtime = microtime(true);
        $this->SQL = $sql;
        $this->pdoParams = [];
        $conn = $this->Writer();
        try {
            $ret = $conn->exec($sql);
            $this->sumTime($mtime);
        } catch (\PDOException $e) {
            $this->Retry($e) && $conn = $this->Writer(true);
            $mtime = microtime(true);
            $ret = $conn->exec($sql);
            $this->sumTime($mtime, '[retry]');
        }
        return $ret;
    }
    public function Query (string $sql, $mode = \PDO::FETCH_ASSOC, $rows = 0)
    {
        $mtime = microtime(true);
        $this->SQL = $sql;
        $this->pdoParams = [];
        $conn = $this->Read();
        try {
            $res = $conn->query($sql, $mode);
            $this->sumTime($mtime);
        } catch (\PDOException $e) {
            $this->Retry($e) && $conn = $this->Read(true);
            $mtime = microtime(true);
            $res = $conn->query($sql, $mode);
            $this->sumTime($mtime, '[retry]');
        }
        return match ($rows) {
            0=>$res,
            1=>$res->fetch($mode),
            default=>$res->fetchAll($mode),
        };
    }
    public function QueryColumn (string $sql, $mode = \PDO::FETCH_COLUMN)
    {
        return $this->Query($sql, $mode, 1);
    }
    public function QueryColumns (string $sql, $mode = \PDO::FETCH_COLUMN): false|array
    {
        return $this->Query($sql, $mode, 2);
    }
    public function QueryOne (string $sql, $mode = \PDO::FETCH_ASSOC): false|array
    {
        return $this->Query($sql, $mode, 1);
    }
    public function QueryAll (string $sql, $mode = \PDO::FETCH_ASSOC): false|array
    {
        return $this->Query($sql, $mode, 2);
    }

    public function LastInsertId(string|null $name = null): string
    {
        return $this->Writer()->lastInsertId($name);
    }

    public function Stmt(string $sql, ?array $bind = null): stmt {
        return new stmt($this, $sql, $bind);
    }
    
    public function SetSql (string $sql): static {
        $this->SQL = $sql;
        return $this;
    }

    public function GetParams (): array {
        return $this->pdoParams;
    }

    public function SetParams (array $params): static {
        $this->pdoParams = $params;
        return $this;
    }

    public function __call(string $func, $args = null)
    {
        return $this->Writer()->$func(...$args);
    }
    public function InTransaction (): bool
    {
        return $this->Writer()->inTransaction();
    }
    public function Begin()
    {
        return $this->Writer()->beginTransaction();
    }
    public function Rollback()
    {
        return $this->Writer()->rollback();
    }
    public function Commit()
    {
        return $this->Writer()->commit();
    }
}

class stmt {
    private bool $isRead = false;
    private string $sql = '';
    private \PDO $conn;
    private \PDOStatement $stmt;
    public function __construct(
        private pdo $pdo,
        string $sql,
        private array|null $bind,
    ){
        $this->sql = trim($sql);
        $this->isRead = 0 === stripos($this->sql, 'SELECT');
        $this->conn = $this->isRead ? $pdo->Read() : $pdo->Writer();
        $this->stmt = $this->conn->prepare($this->sql);
        $pdo->SetSql($this->sql);
        $pdo->SetParams($this->bind ?? []);
    }

    public function __call(string $func, $args = null)
    {
        return $this->stmt && method_exists($this->stmt, $func) ? $this->stmt->$func(...$args) : $this->conn->$func(...$args);
    }

    public function Stmt (): \PDOStatement
    {
        return $this->stmt;
    }

    private function _batch(array &$data, array|int &$result)
    {
        $this->pdo->SetParams($data);
        if (is_array($result)) {
            foreach ($data as $k=>$d) {
                if ($this->stmt->execute($d)) {
                    unset($data[$k]);
                    $result[$k] = $this->stmt->rowCount() ? $this->pdo->LastInsertId() : 0;
                }
            }
        } else {
            foreach ($data as $k=>$d) {
                if ($this->stmt->execute($d)) {
                    unset($data[$k]);
                    $this->stmt->rowCount() && ++$result;
                }
            }
        }
    }

    public function Batch (array $data, bool $returnKeys = false): int|array
    {
        $mtime = microtime(true);
        $result = $returnKeys ? [] : 0;
        try {
            $this->_batch($data, $result);
            $this->pdo->sumTime($mtime);
        } catch (\PDOException $e) {
            $this->pdo->Retry($e, $data) && $this->conn = $this->pdo->Writer(true);
            $mtime = microtime(true);
            $this->stmt = $this->conn->prepare($this->sql);
            $this->_batch($data, $result);
            $this->pdo->sumTime($mtime, '[retry]');
        }
        return $result;
    }

    public function Execute (): \PDOStatement
    {
        $this->stmt->execute($this->bind);
        return $this->stmt;
    }

    public function Exec(): bool
    {
        $mtime = microtime(true);
        try {
            $result = $this->stmt->execute($this->bind);
            $this->pdo->sumTime($mtime);
        } catch (\PDOException $e) {
            if ($this->pdo->Retry($e, $this->bind)) {
                $this->conn = $this->isRead ? $this->pdo->Read(true) : $this->pdo->Writer(true);
            }
            $this->stmt = $this->conn->prepare($this->sql);
            $mtime = microtime(true);
            $result = $this->Exec();
            $this->pdo->sumTime($mtime, '[retry]');
        }
        return $result;
    }
    public function RowCount(): int
    {
        return $this->stmt->rowCount();
    }
    public function LastInsertId(?string $name = null): string
    {
        return $this->conn->lastInsertId($name);
    }

    public function Rows (?int $fetch = null, ?callable $call = null): array
    {
        null === $fetch && $fetch = \PDO::FETCH_ASSOC;
        $mtime = microtime(true);
        try {
            if (!$this->stmt->execute($this->bind)) {
                $result = [];
            } elseif ($call) {
                $result = [];
                $i = 0;
                while ($r = $this->stmt->fetch($fetch)) {
                    list($k, $v) = $call($r, $i);
                    $result[$k] = $v;
                    ++$i;
                }
            } else {
                $result = $this->stmt->fetchAll($fetch);
            }
            $this->pdo->sumTime($mtime);
        } catch (\PDOException $e) {            
            $this->pdo->Retry($e, $this->bind) && $this->conn = $this->pdo->Read(true);
            $this->stmt = $this->conn->prepare($this->sql);
            $mtime = microtime(true);
            if (!$this->stmt->execute($this->bind)) {
                $result = [];
            } else {
                $result = $this->stmt->fetchAll($fetch);
            }
            $this->pdo->sumTime($mtime, '[retry]');
        }
        return $result;
    }
    public function Row (int $fetch = \PDO::FETCH_ASSOC)
    {
        $mtime = microtime(true);
        try {
            if (!$this->stmt->execute($this->bind)) {
                $result = false;
            } else {
                $result = $this->stmt->fetch($fetch);
            }
            $this->pdo->sumTime($mtime);
        } catch (\PDOException $e) {
            $this->pdo->Retry($e, $this->bind) && $this->conn = $this->pdo->Read(true);
            $this->stmt = $this->conn->prepare($this->sql);
            $mtime = microtime(true);
            if (!$this->stmt->execute($this->bind)) {
                $result = false;
            } else {
                $result = $this->stmt->fetch($fetch);
            }
            $this->pdo->sumTime($mtime, '[retry]');
        }
        return $result;
    }
}
