<?php
namespace SquareRouting\Core\Database;

enum DatabaseDialect: string {
    case MYSQL = 'mysql';
    case SQLITE = 'sqlite';
}
