<?php

declare(strict_types=1);

namespace SquareRouting\Core;

use SquareRouting\Core\Database\ColumnType;
use SquareRouting\Core\Database\TableSchema;
use SquareRouting\Core\Database\Table;

class Schema
{
    #[TableSchema]
    public function account(): Table
    {
        $account = null;
        $account = new Table(nameof($account));
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

    #[TableSchema]
    public function configuration(): Table
    {
        $configuration = null;
        $configuration = new Table(nameof($configuration));

        // Define columns
        $configuration->id = ColumnType::INT;
        $configuration->name = ColumnType::VARCHAR;
        $configuration->value = ColumnType::TEXT;
        $configuration->defaultValue = ColumnType::TEXT;
        $configuration->label = ColumnType::VARCHAR;
        $configuration->description = ColumnType::TEXT;
        $configuration->type = ColumnType::VARCHAR;
        $configuration->createdAt = ColumnType::DATETIME;
        $configuration->updatedAt = ColumnType::DATETIME;

        // Configure id column
        $configuration->id->autoIncrement = true;

        // Configure key column
        $configuration->name->length = 255;
        $configuration->name->nullable = false;
        $configuration->name->unique = true;

        // Configure value column (nullable for complex data types)
        $configuration->value->nullable = true;

        // Configure default_value column
        $configuration->defaultValue->nullable = true;

        // Configure label column
        $configuration->label->length = 255;
        $configuration->label->nullable = true;

        // Configure description column
        $configuration->description->nullable = true;

        // Configure type column (data type info)
        $configuration->type->length = 50;
        $configuration->type->nullable = false;
        $configuration->type->default = 'string';

        // Configure timestamps
        $configuration->createdAt->nullable = false;
        $configuration->createdAt->default = 'CURRENT_TIMESTAMP';

        $configuration->updatedAt->nullable = true;

        return $configuration;
    }
}
