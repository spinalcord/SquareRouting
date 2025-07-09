<?php

declare(strict_types=1);

namespace SquareRouting\Core;

use ReflectionClass;
use ReflectionMethod;
use SquareRouting\Core\Database\TableSchema;
use SquareRouting\Core\Schema;

readonly class SchemaGenerator
{
    public function __construct(
        private Schema $schema,
        private string $namespace = 'SquareRouting\\Core\\Schema',
        private string $outputDir = 'backend/Core/Schema/'
    ) {
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
        static $tableNames = null;
        
        if ($tableNames === null) {
            $reflection = new ReflectionClass($this->schema);
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
            
            $tableNames = array_map(
                fn(ReflectionMethod $method) => $method->getName(),
                array_filter(
                    $methods,
                    fn(ReflectionMethod $method) => !empty($method->getAttributes(TableSchema::class))
                )
            );
        }
        
        return $tableNames;
    }

    /**
     * Gets all column names from all tables
     */
    private function getAllColumnNames(): array
    {
        static $allColumns = null;
        
        if ($allColumns === null) {
            $reflection = new ReflectionClass($this->schema);
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
            
            $allColumns = [];
            foreach ($methods as $method) {
                if (!empty($method->getAttributes(TableSchema::class))) {
                    $table = $method->invoke($this->schema);
                    $allColumns = [...$allColumns, ...array_keys($table->getColumns())];
                }
            }
            
            $allColumns = array_unique($allColumns);
        }
        
        return $allColumns;
    }

    /**
     * Generates the TableName.php file content
     */
    private function generateTableNameFile(): string
    {
        $tableNames = $this->getTableNames();
        
        $constants = array_map(
            fn(string $tableName) => sprintf(
                "    const %s = '%s';",
                $this->toUpperSnakeCase($tableName),
                $tableName  // Ursprünglicher Wert ohne snake_case Umwandlung
            ),
            $tableNames
        );
        
        $constantsString = implode("\n", $constants);
        
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespace};

/**
 * Constants for database table names
 * Auto-generated from Schema.php
 */
class TableName
{
{$constantsString}
}
PHP;
    }

    /**
     * Generates the ColumnName.php file content
     */
    private function generateColumnNameFile(): string
    {
        $columnNames = $this->getAllColumnNames();
        
        $constants = array_map(
            fn(string $columnName) => sprintf(
                "    const %s = '%s';",
                $this->toUpperSnakeCase($columnName),
                $columnName  // Ursprünglicher Wert ohne snake_case Umwandlung
            ),
            $columnNames
        );
        
        $constantsString = implode("\n", $constants);
        
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$this->namespace};

/**
 * Constants for database column names
 * Auto-generated from Schema.php
 */
class ColumnName
{
{$constantsString}
}
PHP;
    }

    /**
     * Generates the TableName.ts file content
     */
    private function generateTableNameTsFile(): string
    {
        $tableNames = $this->getTableNames();
        
        $constants = array_map(
            fn(string $tableName) => sprintf(
                "  %s = '%s',",
                $this->toUpperSnakeCase($tableName),
                $tableName  // Ursprünglicher Wert ohne snake_case Umwandlung
            ),
            $tableNames
        );
        
        $constantsString = implode("\n", $constants);
        
        return <<<TS
/**
 * Constants for database table names
 * Auto-generated from Schema.php
 */
export enum TableName {
{$constantsString}
}
TS;
    }

    /**
     * Generates the ColumnName.ts file content
     */
    private function generateColumnNameTsFile(): string
    {
        $columnNames = $this->getAllColumnNames();
        
        $constants = array_map(
            fn(string $columnName) => sprintf(
                "  %s = '%s',",
                $this->toUpperSnakeCase($columnName),
                $columnName  // Ursprünglicher Wert ohne snake_case Umwandlung
            ),
            $columnNames
        );
        
        $constantsString = implode("\n", $constants);
        
        return <<<TS
/**
 * Constants for database column names
 * Auto-generated from Schema.php
 */
export enum ColumnName {
{$constantsString}
}
TS;
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
     * Writes content to file with error handling
     */
    private function writeFile(string $filename, string $content): void
    {
        $path = $this->outputDir . $filename;
        
        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException("Failed to write {$filename} to: {$path}");
        }
    }

    /**
     * Generates both TableName.php and ColumnName.php files
     */
    public function generate(): void
    {
        $this->ensureOutputDirectory();
        
        $files = [
            'TableName.php' => $this->generateTableNameFile(),
            'TableName.ts' => $this->generateTableNameTsFile(),
            'ColumnName.php' => $this->generateColumnNameFile(),
            'ColumnName.ts' => $this->generateColumnNameTsFile()
        ];
        
        foreach ($files as $filename => $content) {
            $this->writeFile($filename, $content);
        }
        
        $this->printSuccess(array_keys($files));
    }

    /**
     * Prints success message with generated files and constants
     */
    private function printSuccess(array $filenames): void
    {
        echo "Files generated successfully:\n";
        foreach ($filenames as $filename) {
            echo "- {$this->outputDir}{$filename}\n";
        }
        
        echo "\nGenerated table constants:\n";
        foreach ($this->getTableNames() as $tableName) {
            $constantName = $this->toUpperSnakeCase($tableName);
            echo "- {$constantName} = '{$tableName}'\n";  // Ursprünglicher Wert
        }
        
        echo "\nGenerated column constants:\n";
        foreach ($this->getAllColumnNames() as $columnName) {
            $constantName = $this->toUpperSnakeCase($columnName);
            echo "- {$constantName} = '{$columnName}'\n";  // Ursprünglicher Wert
        }
    }

    /**
     * Generates only the TableName.php and TableName.ts files
     */
    public function generateTableNames(): void
    {
        $this->ensureOutputDirectory();
        
        $this->writeFile('TableName.php', $this->generateTableNameFile());
        $this->writeFile('TableName.ts', $this->generateTableNameTsFile());
        
        echo "TableName.php generated successfully: {$this->outputDir}TableName.php\n";
        echo "TableName.ts generated successfully: {$this->outputDir}TableName.ts\n";
    }

    /**
     * Generates only the ColumnName.php and ColumnName.ts files
     */
    public function generateColumnNames(): void
    {
        $this->ensureOutputDirectory();
        
        $this->writeFile('ColumnName.php', $this->generateColumnNameFile());
        $this->writeFile('ColumnName.ts', $this->generateColumnNameTsFile());
        
        echo "ColumnName.php generated successfully: {$this->outputDir}ColumnName.php\n";
        echo "ColumnName.ts generated successfully: {$this->outputDir}ColumnName.ts\n";
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
     * Creates a new instance with custom namespace
     */
    public function withNamespace(string $namespace): self
    {
        return new self($this->schema, $namespace);
    }
}
