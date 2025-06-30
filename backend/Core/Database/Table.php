<?php

namespace SquareRouting\Core\Database;

class Table
{
    private array $columns = [];
    private ?string $tableName = null;

    public function __construct(?string $tableName = null)
    {
        $this->tableName = $tableName;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getTableName(): string
    {
        return $this->tableName ?? 'unnamed_table';
    }

    public function setTableName(string $tableName): self
    {
        $this->tableName = $tableName;

        return $this;
    }

    public function toSQL(DatabaseDialect $dialect = DatabaseDialect::MYSQL): string
    {
        $tableName = $dialect === DatabaseDialect::MYSQL
            ? "`{$this->tableName}`"
            : "\"{$this->tableName}\"";

        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (\n";

        $columnSQLs = [];
        $foreignKeys = [];
        $primaryKeys = [];

        foreach ($this->columns as $column) {
            $columnSQLs[] = '  ' . $column->toSQL($dialect);

            // Foreign Keys sammeln
            if ($column->hasForeignKey()) {
                $foreignKeys[] = '  ' . $column->getForeignKey()->toSQL($dialect, $column->getName());
            }

            // SQLite: PRIMARY KEY separat sammeln
            if ($dialect === DatabaseDialect::SQLITE && $column->isPrimaryKey() && ! $column->isAutoIncrement()) {
                $primaryKeys[] = $column->getName();
            }
        }

        $sql .= implode(",\n", $columnSQLs);

        // Foreign Keys hinzufügen
        if (! empty($foreignKeys)) {
            $sql .= ",\n" . implode(",\n", $foreignKeys);
        }

        // SQLite: Separate PRIMARY KEY constraint
        if ($dialect === DatabaseDialect::SQLITE && ! empty($primaryKeys)) {
            $quotedKeys = array_map(fn ($key) => "\"{$key}\"", $primaryKeys);
            $sql .= ",\n  PRIMARY KEY (" . implode(', ', $quotedKeys) . ')';
        }

        $sql .= "\n);";

        return $sql;
    }

    public function getForeignKeys(): array
    {
        $foreignKeys = [];
        foreach ($this->columns as $column) {
            if ($column->hasForeignKey()) {
                $foreignKeys[$column->getName()] = $column->getForeignKey();
            }
        }

        return $foreignKeys;
    }

    public function __set(string $name, ColumnType|Column $value): void
    {
        if ($value instanceof ColumnType) {
            $column = new Column($value);
            $column->setName($name);
            $this->columns[$name] = $column;
        } elseif ($value instanceof Column) {
            $value->setName($name);
            $this->columns[$name] = $value;
        }
    }

    public function __get(string $name): ?Column
    {
        return $this->columns[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->columns[$name]);
    }
}
