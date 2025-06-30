<?php

declare(strict_types=1);

namespace SquareRouting\Core;

use SquareRouting\Core\Database;
use SquareRouting\Core\Database\ColumnType;
use SquareRouting\Core\Database\ForeignKey;
use SquareRouting\Core\Database\ForeignKeyAction;
use SquareRouting\Core\Database\Table;

class Scheme
{
    public function account(): Table
    {
        $account = new Table('users');

        // Define columns
        $account->id = ColumnType::INT;
        $account->email = ColumnType::VARCHAR;
        $account->password = ColumnType::VARCHAR;
        $account->username = ColumnType::VARCHAR;
        $account->status = ColumnType::VARCHAR;
        $account->emailVerified = ColumnType::BOOLEAN;
        $account->emailVerificationToken = ColumnType::VARCHAR;
        $account->resetToken = ColumnType::VARCHAR;
        $account->resetTokenExpires = ColumnType::DATETIME;
        $account->rememberToken = ColumnType::VARCHAR;
        $account->lastLogin = ColumnType::DATETIME;
        $account->createdAt = ColumnType::DATETIME;
        $account->updatedAt = ColumnType::DATETIME;

        // Configure id column
        $account->id->autoIncrement = true;

        // Configure email column
        $account->email->length = 255;
        $account->email->nullable = false;
        $account->email->unique = true;

        // Configure password column
        $account->password->length = 255;
        $account->password->nullable = false;

        // Configure username column
        $account->username->length = 100;
        $account->username->nullable = false;
        $account->username->unique = true;

        // Configure status column
        $account->status->length = 20;
        $account->status->nullable = false;
        $account->status->default = 'active';

        // Configure email verification
        $account->emailVerified->nullable = false;
        $account->emailVerified->default = false;
        $account->emailVerificationToken->length = 64;
        $account->emailVerificationToken->nullable = true;

        // Configure reset token
        $account->resetToken->length = 64;
        $account->resetToken->nullable = true;
        $account->resetTokenExpires->nullable = true;

        // Configure remember token
        $account->rememberToken->length = 64;
        $account->rememberToken->nullable = true;

        // Configure timestamps
        $account->lastLogin->nullable = true;
        $account->createdAt->nullable = false;
        $account->createdAt->default = 'CURRENT_TIMESTAMP';
        $account->updatedAt->nullable = true;

        // Create the table
        // $this->db->createTableIfNotExists($users);
        return $account;
    }
}
