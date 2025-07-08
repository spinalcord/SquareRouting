<?php

declare(strict_types=1);

namespace SquareRouting\Core\CLI;

use SquareRouting\Core\Scheme;
use SquareRouting\Core\Database\TableSchema;
use ReflectionClass;
use ReflectionMethod;

/**
 * CLI generator for schema constants
 * Generates TableName.php and ColumnName.php files from Scheme definitions
 */
class SchemaGenerator
{
    private Scheme $scheme;
    private string $outputDir;
    private string $namespace;

    public function __construct(
        Scheme $scheme,
        string $outputDir = null,
        string $namespace = 'SquareRouting\\Core\\Scheme'
    ) {
        $this->scheme = $scheme;
        $this->outputDir = $outputDir ?? dirname(__DIR__) . '/Scheme/';
        $this->namespace = $namespace;
    }

    /**
     * Converts camelCase or PascalCase to snake_case
     */
    private function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $input));
    }

    /**
     * Converts snake_case to UPPER_SNAKE_CASE
     */
    private function toUpperSnakeCase(string $input): string
    {
        return strtoupper($this->toSnakeCase($input));
    }

    /**
     * Gets all table names from methods with TableSchema attribute
     */
    private function getTableNames(): array
    {
        $tableNames = [];
        
        $reflection = new ReflectionClass($this->scheme);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        foreach ($methods as $method) {
            $attributes = $method->getAttributes(TableSchema::class);
            if (!empty($attributes)) {
                $tableNames[] = $method->getName();
            }
        }
        
        return $tableNames;
    }

    /**
     * Gets all column names from all tables
     */
    private function getAllColumnNames(): array
    {
        $allColumns = [];
        
        $reflection = new ReflectionClass($this->scheme);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        foreach ($methods as $method) {
            $attributes = $method->getAttributes(TableSchema::class);
            if (!empty($attributes)) {
                $table = $method->invoke($this->scheme);
                $allColumns = array_merge($allColumns, array_keys($table->getColumns()));
            }
        }
        
        return array_unique($allColumns);
    }

    /**
     * Generates the TableName.php file content
     */
    private function generateTableNameFile(): string
    {
        $tableNames = $this->getTableNames();
        
        $content = "<?php\n\n";
        $content .= "declare(strict_types=1);\n\n";
        $content .= "namespace {$this->namespace};\n\n";
        $content .= "/**\n";
        $content .= " * Constants for database table names\n";
        $content .= " * Auto-generated from Scheme.php\n";
        $content .= " */\n";
        $content .= "class TableName\n";
        $content .= "{\n";
        
        foreach ($tableNames as $tableName) {
            $constantName = $this->toUpperSnakeCase($tableName);
            $tableNameSnake = $this->toSnakeCase($tableName);
            $content .= "    const {$constantName} = '{$tableNameSnake}';\n";
        }
        
        $content .= "}\n";
        
        return $content;
    }

    /**
     * Generates the ColumnName.php file content
     */
    private function generateColumnNameFile(): string
    {
        $columnNames = $this->getAllColumnNames();
        
        $content = "<?php\n\n";
        $content .= "declare(strict_types=1);\n\n";
        $content .= "namespace {$this->namespace};\n\n";
        $content .= "/**\n";
        $content .= " * Constants for database column names\n";
        $content .= " * Auto-generated from Scheme.php\n";
        $content .= " */\n";
        $content .= "class ColumnName\n";
        $content .= "{\n";
        
        foreach ($columnNames as $columnName) {
            $constantName = $this->toUpperSnakeCase($columnName);
            $columnNameSnake = $this->toSnakeCase($columnName);
            $content .= "    const {$constantName} = '{$columnNameSnake}';\n";
        }
        
        $content .= "}\n";
        
        return $content;
    }

    /**
     * Generates the TableName.ts file content
     */
    private function generateTableNameTsFile(): string
    {
        $tableNames = $this->getTableNames();
        
        $content = "/**
 * Constants for database table names
 * Auto-generated from Scheme.php
 */
export enum TableName {
";
        
        foreach ($tableNames as $tableName) {
            $constantName = $this->toUpperSnakeCase($tableName);
            $tableNameSnake = $this->toSnakeCase($tableName);
            $content .= "  {$constantName} = '{$tableNameSnake}',
";
        }
        
        $content .= "}
";
        
        return $content;
    }

    /**
     * Generates the ColumnName.ts file content
     */
    private function generateColumnNameTsFile(): string
    {
        $columnNames = $this->getAllColumnNames();
        
        $content = "/**
 * Constants for database column names
 * Auto-generated from Scheme.php
 */
export enum ColumnName {
";
        
        foreach ($columnNames as $columnName) {
            $constantName = $this->toUpperSnakeCase($columnName);
            $columnNameSnake = $this->toSnakeCase($columnName);
            $content .= "  {$constantName} = '{$columnNameSnake}',
";
        }
        
        $content .= "}
";
        
        return $content;
    }

    /**
     * Creates the output directory if it doesn't exist
     */
    private function ensureOutputDirectory(): void
    {
        if (!is_dir($this->outputDir)) {
            if (!mkdir($this->outputDir, 0755, true)) {
                throw new \RuntimeException("Failed to create output directory: {$this->outputDir}");
            }
        }
    }

    /**
     * Generates both TableName.php and ColumnName.php files
     */
    public function generate(): void
    {
        $this->ensureOutputDirectory();
        
        // Generate TableName.php
        $tableNameContent = $this->generateTableNameFile();
        $tableNamePath = $this->outputDir . 'TableName.php';
        
        if (file_put_contents($tableNamePath, $tableNameContent) === false) {
            throw new \RuntimeException("Failed to write TableName.php to: {$tableNamePath}");
        }

        // Generate TableName.ts
        $tableNameTsContent = $this->generateTableNameTsFile();
        $tableNameTsPath = $this->outputDir . 'TableName.ts';

        if (file_put_contents($tableNameTsPath, $tableNameTsContent) === false) {
            throw new \RuntimeException("Failed to write TableName.ts to: {$tableNameTsPath}");
        }
        
        // Generate ColumnName.php
        $columnNameContent = $this->generateColumnNameFile();
        $columnNamePath = $this->outputDir . 'ColumnName.php';
        
        if (file_put_contents($columnNamePath, $columnNameContent) === false) {
            throw new \RuntimeException("Failed to write ColumnName.php to: {$columnNamePath}");
        }

        // Generate ColumnName.ts
        $columnNameTsContent = $this->generateColumnNameTsFile();
        $columnNameTsPath = $this->outputDir . 'ColumnName.ts';

        if (file_put_contents($columnNameTsPath, $columnNameTsContent) === false) {
            throw new \RuntimeException("Failed to write ColumnName.ts to: {$columnNameTsPath}");
        }
        
        $this->printSuccess($tableNamePath, $columnNamePath, $tableNameTsPath, $columnNameTsPath);
    }

    /**
     * Prints success message with generated files and constants
     */
    /**
     * Prints success message with generated files and constants
     */
    private function printSuccess(string $tableNamePath, string $columnNamePath, string $tableNameTsPath, string $columnNameTsPath): void
    {
        echo "Files generated successfully:\n";
        echo "- {$tableNamePath}\n";
        echo "- {$columnNamePath}\n";
        echo "- {$tableNameTsPath}\n";
        echo "- {$columnNameTsPath}\n";
        
        echo "\nGenerated table constants:\n";
        foreach ($this->getTableNames() as $tableName) {
            $constantName = $this->toUpperSnakeCase($tableName);
            $tableNameSnake = $this->toSnakeCase($tableName);
            echo "- {$constantName} = '{$tableNameSnake}'\n";
        }
        
        echo "\nGenerated column constants:\n";
        foreach ($this->getAllColumnNames() as $columnName) {
            $constantName = $this->toUpperSnakeCase($columnName);
            $columnNameSnake = $this->toSnakeCase($columnName);
            echo "- {$constantName} = '{$columnNameSnake}'\n";
        }
    }

    /**
     * Generates only the TableName.php and TableName.ts files
     */
    public function generateTableNames(): void
    {
        $this->ensureOutputDirectory();
        
        $tableNameContent = $this->generateTableNameFile();
        $tableNamePath = $this->outputDir . 'TableName.php';
        
        if (file_put_contents($tableNamePath, $tableNameContent) === false) {
            throw new \RuntimeException("Failed to write TableName.php to: {$tableNamePath}");
        }

        $tableNameTsContent = $this->generateTableNameTsFile();
        $tableNameTsPath = $this->outputDir . 'TableName.ts';

        if (file_put_contents($tableNameTsPath, $tableNameTsContent) === false) {
            throw new \RuntimeException("Failed to write TableName.ts to: {$tableNameTsPath}");
        }
        
        echo "TableName.php generated successfully: {$tableNamePath}\n";
        echo "TableName.ts generated successfully: {$tableNameTsPath}\n";
    }

    /**
     * Generates only the ColumnName.php and ColumnName.ts files
     */
    public function generateColumnNames(): void
    {
        $this->ensureOutputDirectory();
        
        $columnNameContent = $this->generateColumnNameFile();
        $columnNamePath = $this->outputDir . 'ColumnName.php';
        
        if (file_put_contents($columnNamePath, $columnNameContent) === false) {
            throw new \RuntimeException("Failed to write ColumnName.php to: {$columnNamePath}");
        }

        $columnNameTsContent = $this->generateColumnNameTsFile();
        $columnNameTsPath = $this->outputDir . 'ColumnName.ts';

        if (file_put_contents($columnNameTsPath, $columnNameTsContent) === false) {
            throw new \RuntimeException("Failed to write ColumnName.ts to: {$columnNameTsPath}");
        }
        
        echo "ColumnName.php generated successfully: {$columnNamePath}\n";
        echo "ColumnName.ts generated successfully: {$columnNameTsPath}\n";
    }

    /**
     * Returns statistics about the schema
     */
    public function getStatistics(): array
    {
        return [
            'table_count' => count($this->getTableNames()),
            'column_count' => count($this->getAllColumnNames()),
            'table_names' => $this->getTableNames(),
            'column_names' => $this->getAllColumnNames(),
            'output_directory' => $this->outputDir,
            'namespace' => $this->namespace
        ];
    }

    /**
     * Prints schema statistics
     */
    public function printStatistics(): void
    {
        $stats = $this->getStatistics();
        
        echo "Schema Statistics:\n";
        echo "- Tables: {$stats['table_count']}\n";
        echo "- Columns: {$stats['column_count']}\n";
        echo "- Output Directory: {$stats['output_directory']}\n";
        echo "- Namespace: {$stats['namespace']}\n";
    }

    /**
     * Sets a custom output directory
     */
    public function setOutputDirectory(string $outputDir): self
    {
        $this->outputDir = rtrim($outputDir, '/') . '/';
        return $this;
    }

    /**
     * Sets a custom namespace
     */
    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }
}