<?php
namespace SquareRouting\Core\Database;

class Column {
    private ?string $name = null;
    private ColumnType $type;
    private ?int $length = null;
    private bool $nullable = true;
    private mixed $default = null;
    private bool $primaryKey = false;
    private bool $autoIncrement = false;
    private ?ForeignKey $foreignKey = null;

    public function __construct(ColumnType $type = ColumnType::VARCHAR) {
        $this->type = $type;
    }
    
    public function __set(string $property, mixed $value): void {
        switch ($property) {
            case 'length':
                $this->length = (int)$value;
                break;
            case 'nullable':
                $this->nullable = (bool)$value;
                break;
            case 'default':
                $this->default = $value;
                break;
            case 'primaryKey':
                $this->primaryKey = (bool)$value;
                if ($this->primaryKey) {
                    $this->nullable = false;
                }
                break;
            case 'autoIncrement':
                $this->autoIncrement = (bool)$value;
                if ($this->autoIncrement) {
                    $this->type = ColumnType::INT;
                    $this->primaryKey = true;
                    $this->nullable = false;
                }
                break;
            case 'type':
                if ($value instanceof ColumnType) {
                    $this->type = $value;
                }
                break;
            case 'foreignKey':
                if ($value instanceof ForeignKey) {
                    $this->foreignKey = $value;
                    $this->adjustNullableForForeignKey();
                }
                break;
        }
    }
    private function adjustNullableForForeignKey(): void {
    if ($this->foreignKey) {
        // Bei SET_NULL muss die Spalte nullable sein
        if ($this->foreignKey->getOnDelete() === ForeignKeyAction::SET_NULL) {
            $this->nullable = true;
        }
        // Bei CASCADE kann die Spalte NOT NULL sein (empfohlen)
        elseif ($this->foreignKey->getOnDelete() === ForeignKeyAction::CASCADE) {
            // Nur automatisch setzen wenn nicht explizit konfiguriert
            if (!isset($this->explicitlySetNullable)) {
                $this->nullable = false;
            }
        }
    }
}
    public function __get(string $property): mixed {
        return match ($property) {
            'length' => $this->length,
            'nullable' => $this->nullable,
            'default' => $this->default,
            'primaryKey' => $this->primaryKey,
            'autoIncrement' => $this->autoIncrement,
            'type' => $this->type,
            'name' => $this->name,
            'foreignKey' => $this->foreignKey,

            default => null
        };
    }
    
    public function __isset(string $property): bool {
        return in_array($property, ['length', 'nullable', 'default', 'primaryKey', 'autoIncrement', 'type', 'name']);
    }
    
    public function setName(string $name): self {
        $this->name = $name;
        return $this;
    }
    
    public function getName(): string {
        return $this->name ?? 'unnamed_column';
    }
    
    public function getType(): ColumnType {
        return $this->type;
    }
    
    public function getLength(): ?int {
        return $this->length;
    }
    
    public function isNullable(): bool {
        return $this->nullable;
    }
    
    public function getDefault(): mixed {
        return $this->default;
    }
    
    public function isPrimaryKey(): bool {
        return $this->primaryKey;
    }
    
    public function isAutoIncrement(): bool {
        return $this->autoIncrement;
    }
    
    public function getDefinition(): array {
        return [
            'name' => $this->name,
            'type' => $this->type->value,
            'length' => $this->length,
            'nullable' => $this->nullable,
            'default' => $this->default,
            'primary_key' => $this->primaryKey,
            'auto_increment' => $this->autoIncrement,
            'foreignKey' => $this->foreignKey,
        ];
    }

public function getForeignKey(): ?ForeignKey {
        return $this->foreignKey;
    }
    
    public function hasForeignKey(): bool {
        return $this->foreignKey !== null;
    }
    
public function toSQL(DatabaseDialect $dialect = DatabaseDialect::MYSQL): string {
    $columnName = $dialect === DatabaseDialect::MYSQL 
        ? "`{$this->name}`" 
        : "\"{$this->name}\"";
    
    $sql = "{$columnName} ";
    
    // Typ-spezifische Behandlung
    switch ($this->type) {
        case ColumnType::VARCHAR:
            $length = $this->length ?? 255;
            $sql .= $dialect === DatabaseDialect::MYSQL ? "VARCHAR({$length})" : "TEXT";
            break;
        case ColumnType::INT:
            $sql .= $dialect === DatabaseDialect::MYSQL ? "INT" : "INTEGER";
            break;
        case ColumnType::BOOLEAN:
            $sql .= $dialect === DatabaseDialect::MYSQL ? "TINYINT(1)" : "INTEGER";
            break;
        case ColumnType::TEXT:
            $sql .= "TEXT";
            break;
        case ColumnType::DECIMAL:
            $precision = $this->length ?? '10,2';
            $sql .= $dialect === DatabaseDialect::MYSQL ? "DECIMAL({$precision})" : "REAL";
            break;
        case ColumnType::DATETIME:
            $sql .= $dialect === DatabaseDialect::MYSQL ? "DATETIME" : "TEXT";
            break;
        case ColumnType::JSON:
            $sql .= "TEXT"; // SQLite stores JSON as TEXT
            break;
        default:
            $sql .= strtoupper($this->type->value);
    }
    
    // NULL/NOT NULL
    $sql .= $this->nullable ? " NULL" : " NOT NULL";
    
    // DEFAULT value - CURRENT_TIMESTAMP ohne Anführungszeichen
    if ($this->default !== null) {
        $sql .= " DEFAULT ";
        if ($this->default === 'CURRENT_TIMESTAMP') {
    $sql .= $dialect === DatabaseDialect::MYSQL ? 'CURRENT_TIMESTAMP' : 'CURRENT_TIMESTAMP';
} elseif (is_string($this->default)) {
            $sql .= "'{$this->default}'";
        } elseif (is_bool($this->default)) {
            $sql .= $this->default ? '1' : '0';
        } else {
            $sql .= $this->default;
        }
    }
    
    // PRIMARY KEY and AUTO_INCREMENT
    if ($this->primaryKey) {
        if ($dialect === DatabaseDialect::MYSQL) {
            $sql .= " PRIMARY KEY";
            if ($this->autoIncrement) {
                $sql .= " AUTO_INCREMENT";
            }
        } elseif ($dialect === DatabaseDialect::SQLITE) {
            if ($this->autoIncrement) {
                $sql .= " PRIMARY KEY AUTOINCREMENT";
            } else {
                $sql .= " PRIMARY KEY";
            }
        }
    }
    
    return $sql;
}
}
