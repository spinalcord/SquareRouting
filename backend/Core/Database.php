<?php
declare(strict_types=1);

namespace SquareRouting\Core;

use InvalidArgumentException;
use PDO;
use PDOStatement;
use PDOException;
use RuntimeException;
use SquareRouting\Core\Database\DatabaseDialect;

class Database
{
    private PDO $pdo;
    private bool $inTransaction = false;
    private array $queryLog = [];
    private bool $enableQueryLogging = false;
    public DatabaseDialect $type = DatabaseDialect::MYSQL;

    public function __construct(DotEnv $dotEnv, string $sqlitePath = "")
    {
        $dbType =  $dotEnv->get('DB_CONNECTION', 'mysql');



        $dsn = $this->buildDsn($dbType, $dotEnv, $sqlitePath);
        
        try {
            $username = $dotEnv->get('DB_USERNAME');
            $password = $dotEnv->get('DB_PASSWORD');
            
            $this->pdo = new PDO($dsn, $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection failed: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    private function buildDsn(string $dbType, DotEnv $dotEnv, string $sqlitePath): string
    {
        $dbType = strtolower($dbType);
        if ($dbType == "mysql"){
          $this->type == DatabaseDialect::MYSQL;
        }
        elseif ($dbType == "sqlite") {
          $this->type == DatabaseDialect::SQLITE;
         
        }

        return match ($dbType) {
            'mysql' => $this->buildMysqlDsn($dotEnv),
            'sqlite' => $this->buildSqliteDsn($dotEnv, $sqlitePath),
            default => throw new InvalidArgumentException("Unsupported database type: {$dbType}. Supported types are 'mysql' and 'sqlite'.")
        };
    }

    private function buildMysqlDsn(DotEnv $dotEnv): string
    {
        $host = $dotEnv->get('DB_HOST');
        $database = $dotEnv->get('DB_DATABASE');
        $port = $dotEnv->get('DB_PORT');
        $charset = $dotEnv->get('DB_CHARSET', 'utf8mb4');

        if (!$host || !$database) {
            throw new InvalidArgumentException("MySQL connection requires DB_HOST and DB_DATABASE.");
        }

        $dsn = "mysql:host={$host};dbname={$database};charset={$charset}";
        
        if ($port) {
            $dsn .= ";port={$port}";
        }

        return $dsn;
    }

    private function buildSqliteDsn(DotEnv $dotEnv, string $sqlitePath): string
    {
        $database = $dotEnv->get('DB_DATABASE');
        
        if (!$database) {
            throw new InvalidArgumentException("SQLite connection requires DB_DATABASE (path to file).");
        }

        if (!empty($sqlitePath)) {
            if (!$this->isAbsolutePath($sqlitePath)) {
                $projectRoot = dirname(dirname(__DIR__));
                $sqlitePath = $projectRoot . DIRECTORY_SEPARATOR . $sqlitePath;
            }
            
            if (!str_ends_with($sqlitePath, DIRECTORY_SEPARATOR)) {
                $sqlitePath .= DIRECTORY_SEPARATOR;
            }
            
            if (!is_dir($sqlitePath) && !mkdir($sqlitePath, 0777, true)) {
                throw new RuntimeException("Could not create directory: {$sqlitePath}");
            }
            
            $database = $sqlitePath . $database;
        } else {
            if (!$this->isAbsolutePath($database)) {
                $projectRoot = dirname(dirname(__DIR__));
                $database = $projectRoot . DIRECTORY_SEPARATOR . $database;
            }
        }

        if (!str_ends_with(strtolower($database), '.db')) {
            $database .= '.db';
        }

        if (empty($sqlitePath)) {
            $directory = dirname($database);
            if (!is_dir($directory) && !mkdir($directory, 0777, true)) {
                throw new RuntimeException("Could not create directory: {$directory}");
            }
        }

        return "sqlite:{$database}";
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:\\\\/', $path);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    // Query Logging
    public function enableQueryLogging(bool $enable = true): self
    {
        $this->enableQueryLogging = $enable;
        return $this;
    }

    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    public function clearQueryLog(): self
    {
        $this->queryLog = [];
        return $this;
    }

    private function logQuery(string $sql, array $params = [], float $executionTime = 0.0): void
    {
        if (!$this->enableQueryLogging) {
            return;
        }

        $this->queryLog[] = [
            'sql' => $sql,
            'params' => $params,
            'execution_time' => $executionTime,
            'timestamp' => microtime(true)
        ];
    }

    // Transaction Management
    public function beginTransaction(): bool
    {
        if ($this->inTransaction) {
            throw new RuntimeException('Transaction already active');
        }

        $result = $this->pdo->beginTransaction();
        $this->inTransaction = $result;
        return $result;
    }

    public function commit(): bool
    {
        if (!$this->inTransaction) {
            throw new RuntimeException('No active transaction to commit');
        }

        $result = $this->pdo->commit();
        $this->inTransaction = false;
        return $result;
    }

    public function rollback(): bool
    {
        if (!$this->inTransaction) {
            throw new RuntimeException('No active transaction to rollback');
        }

        $result = $this->pdo->rollBack();
        $this->inTransaction = false;
        return $result;
    }

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function isInTransaction(): bool
    {
        return $this->inTransaction;
    }

    // Query Execution Methods
    public function query(string $sql, array $params = []): PDOStatement
    {
        $startTime = microtime(true);
        
        try {
            if (empty($params)) {
                $stmt = $this->pdo->query($sql);
            } else {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            }
            
            $this->logQuery($sql, $params, microtime(true) - $startTime);
            return $stmt;
        } catch (PDOException $e) {
            throw new RuntimeException("Query failed: " . $e->getMessage() . " | SQL: " . $sql, (int)$e->getCode(), $e);
        }
    }

    public function prepare(string $sql): PDOStatement
    {
        try {
            return $this->pdo->prepare($sql);
        } catch (PDOException $e) {
            throw new RuntimeException("Prepare failed: " . $e->getMessage() . " | SQL: " . $sql, (int)$e->getCode(), $e);
        }
    }

    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount() > 0;
    }

    // Fetch Methods
    public function fetch(string $sql, array $params = []): array|false
    {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        return $this->query($sql, $params)->fetchColumn($column);
    }

    public function fetchObject(string $sql, array $params = [], ?string $className = null): object|false
    {
        $stmt = $this->query($sql, $params);
        return $className ? $stmt->fetchObject($className) : $stmt->fetchObject();
    }

    // CRUD Operations
    public function insert(string $table, array $data): string|false
    {
        if (empty($data)) {
            throw new InvalidArgumentException('Data array cannot be empty');
        }

        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":{$col}", $columns);
        
        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $this->query($sql, $data);
        return $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, array $where): int
    {
        if (empty($data)) {
            throw new InvalidArgumentException('Data array cannot be empty');
        }
        
        if (empty($where)) {
            throw new InvalidArgumentException('WHERE conditions cannot be empty');
        }

        $setParts = array_map(fn($col) => "{$col} = :{$col}", array_keys($data));
        $whereParts = array_map(fn($col) => "{$col} = :where_{$col}", array_keys($where));
        
        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE " . implode(' AND ', $whereParts);
        
        // Prefix where parameters to avoid conflicts
        $whereParams = [];
        foreach ($where as $key => $value) {
            $whereParams["where_{$key}"] = $value;
        }
        
        $params = array_merge($data, $whereParams);
        
        return $this->query($sql, $params)->rowCount();
    }

    public function delete(string $table, array $where): int
    {
        if (empty($where)) {
            throw new InvalidArgumentException('WHERE conditions cannot be empty');
        }

        $whereParts = array_map(fn($col) => "{$col} = :{$col}", array_keys($where));
        $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $whereParts);
        
        return $this->query($sql, $where)->rowCount();
    }

    public function select(string $table, array $columns = ['*'], array $where = [], string $orderBy = '', int $limit = 0): array
    {
        $columnList = implode(', ', $columns);
        $sql = "SELECT {$columnList} FROM {$table}";
        
        $params = [];
        if (!empty($where)) {
            $whereParts = array_map(fn($col) => "{$col} = :{$col}", array_keys($where));
            $sql .= " WHERE " . implode(' AND ', $whereParts);
            $params = $where;
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }
        
        return $this->fetchAll($sql, $params);
    }

    public function exists(string $table, array $where): bool
    {
        if (empty($where)) {
            throw new InvalidArgumentException('WHERE conditions cannot be empty');
        }

        $whereParts = array_map(fn($col) => "{$col} = :{$col}", array_keys($where));
        $sql = "SELECT 1 FROM {$table} WHERE " . implode(' AND ', $whereParts) . " LIMIT 1";
        
        return $this->fetch($sql, $where) !== false;
    }

    public function count(string $table, array $where = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$table}";
        $params = [];
        
        if (!empty($where)) {
            $whereParts = array_map(fn($col) => "{$col} = :{$col}", array_keys($where));
            $sql .= " WHERE " . implode(' AND ', $whereParts);
            $params = $where;
        }
        
        return (int) $this->fetchColumn($sql, $params);
    }

    // Utility Methods
    public function tableExists(string $table): bool
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        return match ($driver) {
            'mysql' => $this->exists('information_schema.tables', [
                'table_schema' => $this->pdo->query('SELECT DATABASE()')->fetchColumn(),
                'table_name' => $table
            ]),
            'sqlite' => $this->exists('sqlite_master', [
                'type' => 'table',
                'name' => $table
            ]),
            default => throw new RuntimeException("Unsupported database driver: {$driver}")
        };
    }

    public function getTableColumns(string $table): array
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        return match ($driver) {
            'mysql' => $this->fetchAll("SHOW COLUMNS FROM {$table}"),
            'sqlite' => $this->fetchAll("PRAGMA table_info({$table})"),
            default => throw new RuntimeException("Unsupported database driver: {$driver}")
        };
    }

    public function getTables(): array
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        return match ($driver) {
            'mysql' => array_column($this->fetchAll("SHOW TABLES"), 0),
            'sqlite' => array_column($this->fetchAll("SELECT name FROM sqlite_master WHERE type='table'"), 'name'),
            default => throw new RuntimeException("Unsupported database driver: {$driver}")
        };
    }

    public function getLastInsertId(?string $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }

    public function quote(string $string): string
    {
        return $this->pdo->quote($string);
    }

    /**
     * Checks if the database connection is active.
     */
    public function isConnectionActive(): bool
    {
        try {
            // Attempt a simple query to check the connection status
            $this->pdo->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            // Connection is not active or query failed
            return false;
        }
    }
}
