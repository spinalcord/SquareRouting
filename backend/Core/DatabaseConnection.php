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
        $host = $dotEnv->get('DB_HOST');
        $port = $dotEnv->get('DB_PORT');
        $database = $dotEnv->get('DB_DATABASE');
        $username = $dotEnv->get('DB_USERNAME');
        $password = $dotEnv->get('DB_PASSWORD');
        $dsn = $this->buildDsn($dbType, $host, $port, $sqlitePath, $database);

        try {
            $this->pdo = new PDO($dsn, $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection failed: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    private function buildDsn(string $dbType, ?string $host, ?string $port, ?string $sqlitePath, ?string $database): string
    {
        switch (strtolower($dbType)) {
            case 'mysql':
                if (!$host || !$database) {
                    throw new InvalidArgumentException("MySQL connection requires DB_HOST and DB_DATABASE.");
                }
                $dsn = "mysql:host={$host};dbname={$database}";
                if ($port) {
                    $dsn .= ";port={$port}";
                }
                return $dsn;
            case 'sqlite':
                if (!$database) {
                    throw new InvalidArgumentException("SQLite connection requires DB_DATABASE (path to file).");
                }

                $fullDbPath = $database;

                // Normalize sqlitePath if provided
                if (!empty($sqlitePath)) {
                    // Remove leading slash
                    if (str_starts_with($sqlitePath, '/')) {
                        $sqlitePath = substr($sqlitePath, 1);
                    }
                    // Add trailing slash if missing
                    if (!str_ends_with($sqlitePath, '/')) {
                        $sqlitePath .= '/';
                    }

                    // Create directory if it doesn't exist
                    if (!is_dir($sqlitePath)) {
                        if (!mkdir($sqlitePath, 0777, true) && !is_dir($sqlitePath)) {
                            throw new RuntimeException(sprintf('Directory "%s" was not created', $sqlitePath));
                        }
                    }
                    $fullDbPath = $sqlitePath . $database;
                }

                // Ensure .db extension is only added if not already present in $database
                if (!str_ends_with(strtolower($fullDbPath), '.db')) {
                    $fullDbPath .= '.db';
                }

                return "sqlite:{$fullDbPath}";
            default:
                throw new InvalidArgumentException("Unsupported database type: {$dbType}. Supported types are 'mysql' and 'sqlite'.");
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
