<?php

declare(strict_types=1);

namespace SquareRouting\Core;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

class DatabaseConnection
{
    private PDO $pdo;

    public function __construct(DotEnv $dotEnv, string $sqlitePath = "")
    {
        $dbType = $dotEnv->get('DB_CONNECTION', 'mysql');
        $dsn = $this->buildDsn($dbType, $dotEnv, $sqlitePath);

        try {
            $username = $dotEnv->get('DB_USERNAME');
            $password = $dotEnv->get('DB_PASSWORD');
            
            $this->pdo = new PDO($dsn, $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection failed: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    private function buildDsn(string $dbType, DotEnv $dotEnv, string $sqlitePath): string
    {
        switch (strtolower($dbType)) {
            case 'mysql':
                return $this->buildMysqlDsn($dotEnv);
            case 'sqlite':
                return $this->buildSqliteDsn($dotEnv, $sqlitePath);
            default:
                throw new InvalidArgumentException("Unsupported database type: {$dbType}. Supported types are 'mysql' and 'sqlite'.");
        }
    }

    private function buildMysqlDsn(DotEnv $dotEnv): string
    {
        $host = $dotEnv->get('DB_HOST');
        $database = $dotEnv->get('DB_DATABASE');
        $port = $dotEnv->get('DB_PORT');

        if (!$host || !$database) {
            throw new InvalidArgumentException("MySQL connection requires DB_HOST and DB_DATABASE.");
        }

        $dsn = "mysql:host={$host};dbname={$database}";
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
}