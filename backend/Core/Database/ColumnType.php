<?php
namespace SquareRouting\Core\Database;

enum ColumnType: string
{
    case INT = 'INT';
    case VARCHAR = 'VARCHAR';
    case TEXT = 'TEXT';
    case BOOLEAN = 'BOOLEAN';
    case DATE = 'DATE';
    case DATETIME = 'DATETIME';
    case DECIMAL = 'DECIMAL';
    case BLOB = 'BLOB';
    case JSON = 'JSON';
}
