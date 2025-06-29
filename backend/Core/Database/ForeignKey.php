<?php
namespace SquareRouting\Core\Database;

use SquareRouting\Core\Database\ForeignKeyAction;

class ForeignKey {
    private Table $referencedTable;
    private Column $referencedColumn;
    private ForeignKeyAction $onDelete = ForeignKeyAction::RESTRICT;
    private ForeignKeyAction $onUpdate = ForeignKeyAction::RESTRICT;
    private ?string $constraintName = null;
    
    public function __construct(Table $referencedTable, Column $referencedColumn) {
        $this->referencedTable = $referencedTable;
        $this->referencedColumn = $referencedColumn;
    }
    
    public function __set(string $property, mixed $value): void {
        switch ($property) {
            case 'onDelete':
                if ($value instanceof ForeignKeyAction) {
                    $this->onDelete = $value;
                }
                break;
            case 'onUpdate':
                if ($value instanceof ForeignKeyAction) {
                    $this->onUpdate = $value;
                }
                break;
            case 'constraintName':
                $this->constraintName = (string)$value;
                break;
        }
    }
    
    public function __get(string $property): mixed {
        return match ($property) {
            'onDelete' => $this->onDelete,
            'onUpdate' => $this->onUpdate,
            'constraintName' => $this->constraintName,
            'referencedTable' => $this->referencedTable,
            'referencedColumn' => $this->referencedColumn,
            default => null
        };
    }
    
    public function getReferencedTable(): Table {
        return $this->referencedTable;
    }
    
    public function getReferencedColumn(): Column {
        return $this->referencedColumn;
    }
    
    public function getOnDelete(): ForeignKeyAction {
        return $this->onDelete;
    }
    
    public function getOnUpdate(): ForeignKeyAction {
        return $this->onUpdate;
    }
    
    public function getConstraintName(): ?string {
        return $this->constraintName;
    }
    
    public function setConstraintName(string $name): self {
        $this->constraintName = $name;
        return $this;
    }
    
    public function toSQL(DatabaseDialect $dialect = DatabaseDialect::MYSQL, string $columnName = ''): string {
        $constraintName = $this->constraintName ?? "fk_{$columnName}_{$this->referencedTable->getTableName()}_{$this->referencedColumn->getName()}";
        
        if ($dialect === DatabaseDialect::MYSQL) {
            $sql = "CONSTRAINT `{$constraintName}` FOREIGN KEY (`{$columnName}`) ";
            $sql .= "REFERENCES `{$this->referencedTable->getTableName()}` (`{$this->referencedColumn->getName()}`)";
        } else {
            $sql = "CONSTRAINT \"{$constraintName}\" FOREIGN KEY (\"{$columnName}\") ";
            $sql .= "REFERENCES \"{$this->referencedTable->getTableName()}\" (\"{$this->referencedColumn->getName()}\")";
        }
        
        $sql .= " ON DELETE {$this->onDelete->value}";
        $sql .= " ON UPDATE {$this->onUpdate->value}";
        
        return $sql;
    }
}
