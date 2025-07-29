<?php

declare(strict_types=1);

namespace SquareRouting\Core;

use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use SquareRouting\Core\Database\DatabaseDialect;
use SquareRouting\Core\Database\Table;
use Throwable;
use Exception;

class Database
{
    public DatabaseDialect $type = DatabaseDialect::MYSQL;

    private PDO $pdo;
    private bool $inTransaction = false;
    private array $queryLog = [];
    private bool $enableQueryLogging = false;

    // Cache Integration
    private ?Cache $cache = null;
    private bool $enableCaching = false;
    private int $defaultCacheTtl = 300; // 5 minutes default
    private bool $isDirty = false;
    private string $cachePrefix = 'db';
    private DotEnv $dotEnv;
    private string $dsn;
    private string $sqlitePath;
    private string $dbType;

    public function __construct(DotEnv $dotEnv, string $sqlitePath = '', ?Cache $cache = null)
    {
        $this->dotEnv = $dotEnv;
        $this->dbType = $this->dotEnv->get('DB_CONNECTION', 'mysql');
        $this->sqlitePath = $sqlitePath;
        $this->cache = $cache;

        if ($this->dbType == 'mysql') {
            $this->type = DatabaseDialect::MYSQL;
        } elseif ($this->dbType == 'sqlite') {
            $this->type = DatabaseDialect::SQLITE;
        }
    }

    public function connect(): void
    {
        $dsn = $this->buildDsn($this->dbType, $this->dotEnv, $this->sqlitePath);

        try {
            $username = $this->dotEnv->get('DB_USERNAME');
            $password = $this->dotEnv->get('DB_PASSWORD');

            $this->pdo = new PDO($dsn, $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            // Cache setup
            if ($this->cache !== null) {
                $this->enableCaching = true;
            }
        } catch (PDOException $e) {
            // throw new RuntimeException('Database connection failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
            echo "<style>h2 {color: red;}</style>";
            echo "<pre>";
            echo "<h2>Database connection failed</h2>";
            echo $e->getMessage() . "\n\n";
            echo "<b>Troubleshoting: open the `.env` file in the `Config` dir.
              \n 1. Check `Mysql` credentials
              \n 2. Or switch to sqlite (DB_CONNECTION=sqlite)</b>
              \n 3. If sqlite is not working, you need to install sqlite drivers with your package manager and add following to your `php.ini`
              \n extension=sqlite3
              \n extension=pdo_sqlite
              ";

            echo "</pre>";

            exit;
        }

    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    // Cache Management
    public function setCache(Cache $cache): self
    {
        $this->cache = $cache;
        $this->enableCaching = true;

        return $this;
    }

    public function enableCaching(bool $enable = true): self
    {
        $this->enableCaching = $enable && $this->cache !== null;

        return $this;
    }

    public function setCachePrefix(string $prefix): self
    {
        $this->cachePrefix = $prefix;

        return $this;
    }

    public function setCacheTtl(int $ttl): self
    {
        $this->defaultCacheTtl = $ttl;

        return $this;
    }

    public function clearCache(): self
    {
        if ($this->enableCaching && $this->cache) {
            $this->cache->clear($this->cachePrefix);
        }

        return $this;
    }

    public function markDirty(): self
    {
        $this->isDirty = true;
        if ($this->enableCaching && $this->cache) {
            $this->cache->clear($this->cachePrefix);
        }

        return $this;
    }

    public function isDirty(): bool
    {
        return $this->isDirty;
    }

    public function markClean(): self
    {
        $this->isDirty = false;

        return $this;
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
        if (! $this->inTransaction) {
            throw new RuntimeException('No active transaction to commit');
        }

        $result = $this->pdo->commit();
        $this->inTransaction = false;

        return $result;
    }

    public function rollback(): bool
    {
        if (! $this->inTransaction) {
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
        } catch (Throwable $e) {
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
            throw new RuntimeException('Query failed: ' . $e->getMessage() . ' | SQL: ' . $sql, (int) $e->getCode(), $e);
        }
    }

    public function prepare(string $sql): PDOStatement
    {
        try {
            return $this->pdo->prepare($sql);
        } catch (PDOException $e) {
            throw new RuntimeException('Prepare failed: ' . $e->getMessage() . ' | SQL: ' . $sql, (int) $e->getCode(), $e);
        }
    }

    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->query($sql, $params);
        $this->markDirty(); // Mark as dirty for write operations

        return $stmt->rowCount() > 0;
    }

    // Fetch Methods (with Cache)
    public function fetch(string $sql, array $params = [], int $cacheTtl = 0): array|false
    {
        // Try cache first
        $cached = $this->getCachedResult($sql, $params, $cacheTtl);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->query($sql, $params)->fetch();

        // Cache the result if it's a successful read
        if ($result !== false) {
            $this->setCachedResult($sql, $params, $result);
        }

        return $result;
    }

    public function fetchAll(string $sql, array $params = [], int $cacheTtl = 0): array
    {
        // Try cache first
        $cached = $this->getCachedResult($sql, $params, $cacheTtl);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->query($sql, $params)->fetchAll();

        // Cache the result
        $this->setCachedResult($sql, $params, $result);

        return $result;
    }

    public function fetchColumn(string $sql, array $params = [], int $column = 0, int $cacheTtl = 0): mixed
    {
        // Try cache first
        $cacheKey = $this->getCacheKey($sql . "_col_{$column}", $params);
        if ($this->enableCaching && $this->cache && ! $this->isDirty) {
            $ttl = $cacheTtl ?: $this->defaultCacheTtl;
            $cached = $this->cache->get($this->cachePrefix, $cacheKey, function () {
                return null;
            }, $ttl);

            if ($cached !== null) {
                return $cached;
            }
        }

        $result = $this->query($sql, $params)->fetchColumn($column);

        // Cache the result
        if ($this->enableCaching && $this->cache && ! $this->isDirty) {
            $this->cache->put($this->cachePrefix, $cacheKey, $result);
        }

        return $result;
    }

    public function fetchObject(string $sql, array $params = [], ?string $className = null, int $cacheTtl = 0): object|false
    {
        // Try cache first
        $cacheKey = $this->getCacheKey($sql . '_obj_' . ($className ?? 'stdClass'), $params);
        if ($this->enableCaching && $this->cache && ! $this->isDirty) {
            $ttl = $cacheTtl ?: $this->defaultCacheTtl;
            $cached = $this->cache->get($this->cachePrefix, $cacheKey, function () {
                return null;
            }, $ttl);

            if ($cached !== null) {
                return $cached;
            }
        }

        $stmt = $this->query($sql, $params);
        $result = $className ? $stmt->fetchObject($className) : $stmt->fetchObject();

        // Cache the result if successful
        if ($result !== false && $this->enableCaching && $this->cache && ! $this->isDirty) {
            $this->cache->put($this->cachePrefix, $cacheKey, $result);
        }

        return $result;
    }

    // CRUD Operations
    public function insert(string $table, array $data): string|false
    {
        if (empty($data)) {
            throw new InvalidArgumentException('Data array cannot be empty');
        }

        $columns = array_keys($data);
        $placeholders = array_map(fn ($col) => ":{$col}", $columns);

        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

        $this->query($sql, $data);
        $this->markDirty(); // Mark cache as dirty

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

        $setParts = array_map(fn ($col) => "{$col} = :{$col}", array_keys($data));
        $whereParts = array_map(fn ($col) => "{$col} = :where_{$col}", array_keys($where));

        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . ' WHERE ' . implode(' AND ', $whereParts);

        // Prefix where parameters to avoid conflicts
        $whereParams = [];
        foreach ($where as $key => $value) {
            $whereParams["where_{$key}"] = $value;
        }

        $params = array_merge($data, $whereParams);
        $result = $this->query($sql, $params)->rowCount();
        $this->markDirty(); // Mark cache as dirty

        return $result;
    }

    public function delete(string $table, array $where): int
    {
        if (empty($where)) {
            throw new InvalidArgumentException('WHERE conditions cannot be empty');
        }

        $whereParts = array_map(fn ($col) => "{$col} = :{$col}", array_keys($where));
        $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $whereParts);

        $result = $this->query($sql, $where)->rowCount();
        $this->markDirty(); // Mark cache as dirty

        return $result;
    }

    public function select(string $table, array $columns = ['*'], array $where = [], string $orderBy = '', int $limit = 0, int $cacheTtl = 0): array
    {
        $columnList = implode(', ', $columns);
        $sql = "SELECT {$columnList} FROM {$table}";

        $params = [];
        if (! empty($where)) {
            $whereParts = array_map(fn ($col) => "{$col} = :{$col}", array_keys($where));
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
            $params = $where;
        }

        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }

        return $this->fetchAll($sql, $params, $cacheTtl);
    }

    public function exists(string $table, array $where, int $cacheTtl = 0): bool
    {
        if (empty($where)) {
            throw new InvalidArgumentException('WHERE conditions cannot be empty');
        }

        $whereParts = array_map(fn ($col) => "{$col} = :{$col}", array_keys($where));
        $sql = "SELECT 1 FROM {$table} WHERE " . implode(' AND ', $whereParts) . ' LIMIT 1';

        return $this->fetch($sql, $where, $cacheTtl) !== false;
    }

    public function count(string $table, array $where = [], int $cacheTtl = 0): int
    {
        $sql = "SELECT COUNT(*) FROM {$table}";
        $params = [];

        if (! empty($where)) {
            $whereParts = array_map(fn ($col) => "{$col} = :{$col}", array_keys($where));
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
            $params = $where;
        }

        return (int) $this->fetchColumn($sql, $params, 0, $cacheTtl);
    }

    // Utility Methods
    public function tableExists(string $table): bool
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return match ($driver) {
            'mysql' => $this->exists('information_schema.tables', [
                'table_schema' => $this->pdo->query('SELECT DATABASE()')->fetchColumn(),
                'table_name' => $table,
            ]),
            'sqlite' => $this->exists('sqlite_master', [
                'type' => 'table',
                'name' => $table,
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
            'mysql' => array_column($this->fetchAll('SHOW TABLES'), 0),
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

    /**
     * Creates a table in the database based on a Table object.
     */
    public function createTable(Table $table): bool
    {
        $tableName = $table->getTableName();

        // Debug: Ausgabe des aktuellen Dialekts
        error_log('Database type: ' . ($this->type->value ?? 'undefined'));

        if ($this->tableExists($tableName)) {
            throw new RuntimeException("Table '{$tableName}' already exists.");
        }

        $sql = $table->toSQL($this->type);

        // Debug: Ausgabe der generierten SQL
        error_log('Generated SQL: ' . $sql);

        try {
            $this->query($sql);
            $this->markDirty(); // Mark cache as dirty

            return true;
        } catch (PDOException $e) {
            throw new RuntimeException("Failed to create table '{$tableName}': " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Creates a table in the database based on a Table object if it doesn't already exist.
     */
    public function createTableIfNotExists(Table $table): bool
    {
        $tableName = $table->getTableName();

        // Debug: Ausgabe des aktuellen Dialekts
        error_log('Database type: ' . ($this->type->value ?? 'undefined'));

        // Check if table already exists
        if ($this->tableExists($tableName)) {
            return false; // Table already exists, nothing to do
        }

        $sql = $table->toSQL($this->type);

        // Debug: Ausgabe der generierten SQL
        error_log('Generated SQL: ' . $sql);

        try {
            $this->query($sql);
            $this->markDirty(); // Mark cache as dirty

            return true; // Table was created successfully
        } catch (PDOException $e) {
            throw new RuntimeException("Failed to create table '{$tableName}': " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    private function getCacheKey(string $sql, array $params = []): string
    {
        return md5($sql . serialize($params));
    }

    private function getCachedResult(string $sql, array $params = [], int $ttl = 0): mixed
    {
        if (! $this->enableCaching || ! $this->cache || $this->isDirty) {
            return null;
        }

        $cacheKey = $this->getCacheKey($sql, $params);
        $ttl = $ttl ?: $this->defaultCacheTtl;

        try {
            return $this->cache->get($this->cachePrefix, $cacheKey, function () {
                return null; // Return null if not in cache
            }, $ttl);
        } catch (Exception $e) {
            return null;
        }
    }

    private function setCachedResult(string $sql, array $params, $result): void
    {
        if (! $this->enableCaching || ! $this->cache || $this->isDirty) {
            return;
        }

        $cacheKey = $this->getCacheKey($sql, $params);
        $this->cache->put($this->cachePrefix, $cacheKey, $result);
    }

    private function buildDsn(string $dbType, DotEnv $dotEnv, string $sqlitePath): string
    {
        $dbType = strtolower($dbType);

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

        if (! $host || ! $database) {
            throw new InvalidArgumentException('MySQL connection requires DB_HOST and DB_DATABASE.');
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

        if (! $database) {
            throw new InvalidArgumentException('SQLite connection requires DB_DATABASE (path to file).');
        }

        if (! empty($sqlitePath)) {
            if (! $this->isAbsolutePath($sqlitePath)) {
                $projectRoot = dirname(dirname(__DIR__));
                $sqlitePath = $projectRoot . DIRECTORY_SEPARATOR . $sqlitePath;
            }

            if (! str_ends_with($sqlitePath, DIRECTORY_SEPARATOR)) {
                $sqlitePath .= DIRECTORY_SEPARATOR;
            }

            if (! is_dir($sqlitePath) && ! mkdir($sqlitePath, 0777, true)) {
                throw new RuntimeException("Could not create directory: {$sqlitePath}");
            }

            $database = $sqlitePath . $database;
        } else {
            if (! $this->isAbsolutePath($database)) {
                $projectRoot = dirname(dirname(__DIR__));
                $database = $projectRoot . DIRECTORY_SEPARATOR . $database;
            }
        }

        if (! str_ends_with(strtolower($database), '.db')) {
            $database .= '.db';
        }

        if (empty($sqlitePath)) {
            $directory = dirname($database);
            if (! is_dir($directory) && ! mkdir($directory, 0777, true)) {
                throw new RuntimeException("Could not create directory: {$directory}");
            }
        }

        return "sqlite:{$database}";
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:\\\\/', $path);
    }

    private function logQuery(string $sql, array $params = [], float $executionTime = 0.0): void
    {
        if (! $this->enableQueryLogging) {
            return;
        }

        $this->queryLog[] = [
            'sql' => $sql,
            'params' => $params,
            'execution_time' => $executionTime,
            'timestamp' => microtime(true),
        ];
    }
/**
 * Tests database connection with given parameters
 * 
 * @param string $dbType Database type ('mysql' or 'sqlite')
 * @param array $config Database configuration array
 * @return bool True if connection successful, false otherwise
 */
public function test(string $dbType, array $config): bool
{
    try {
        $dsn = $this->buildTestDsn($dbType, $config);
        
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        
        $testPdo = new PDO($dsn, $username, $password);
        $testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Simple test query
        $testPdo->query('SELECT 1');
        
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Builds DSN for test connection
 * 
 * @param string $dbType
 * @param array $config
 * @return string
 */
private function buildTestDsn(string $dbType, array $config): string
{
    $dbType = strtolower($dbType);
    
    return match ($dbType) {
        'mysql' => $this->buildTestMysqlDsn($config),
        'sqlite' => $this->buildTestSqliteDsn($config),
        default => throw new InvalidArgumentException("Unsupported database type: {$dbType}")
    };
}

/**
 * Builds MySQL DSN for testing
 */
private function buildTestMysqlDsn(array $config): string
{
    $host = $config['host'] ?? 'localhost';
    $database = $config['database'] ?? '';
    $port = $config['port'] ?? 3306;
    $charset = $config['charset'] ?? 'utf8mb4';
    
    if (!$database) {
        throw new InvalidArgumentException('MySQL test requires database name');
    }
    
    $dsn = "mysql:host={$host};dbname={$database};charset={$charset}";
    
    if ($port) {
        $dsn .= ";port={$port}";
    }
    
    return $dsn;
}

/**
 * Builds SQLite DSN for testing
 */
private function buildTestSqliteDsn(array $config): string
{
    $database = $config['database'] ?? '';
    
    if (!$database) {
        throw new InvalidArgumentException('SQLite test requires database path');
    }
    
    return "sqlite:{$database}";
}
}
