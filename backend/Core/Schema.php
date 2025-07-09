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
        $account->email_verified = ColumnType::BOOLEAN;
        $account->email_verification_token = ColumnType::VARCHAR;
        $account->reset_token = ColumnType::VARCHAR;
        $account->reset_token_expires = ColumnType::DATETIME;
        $account->remember_token = ColumnType::VARCHAR;
        $account->last_login = ColumnType::DATETIME;
        $account->created_at = ColumnType::DATETIME;
        $account->updated_at = ColumnType::DATETIME;

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
        $account->email_verified->nullable = false;
        $account->email_verified->default = false;
        $account->email_verification_token->length = 64;
        $account->email_verification_token->nullable = true;

        // Configure reset token
        $account->reset_token->length = 64;
        $account->reset_token->nullable = true;
        $account->reset_token_expires->nullable = true;

        // Configure remember token
        $account->remember_token->length = 64;
        $account->remember_token->nullable = true;

        // Configure timestamps
        $account->last_login->nullable = true;
        $account->created_at->nullable = false;
        $account->created_at->default = 'CURRENT_TIMESTAMP';
        $account->updated_at->nullable = true;

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
        $configuration->default_value = ColumnType::TEXT;
        $configuration->label = ColumnType::VARCHAR;
        $configuration->description = ColumnType::TEXT;
        $configuration->type = ColumnType::VARCHAR;
        $configuration->created_at = ColumnType::DATETIME;
        $configuration->updated_at = ColumnType::DATETIME;

        // Configure id column
        $configuration->id->autoIncrement = true;

        // Configure key column
        $configuration->name->length = 255;
        $configuration->name->nullable = false;
        $configuration->name->unique = true;

        // Configure value column (nullable for complex data types)
        $configuration->value->nullable = true;

        // Configure default_value column
        $configuration->default_value->nullable = true;

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
        $configuration->created_at->nullable = false;
        $configuration->created_at->default = 'CURRENT_TIMESTAMP';

        $configuration->updated_at->nullable = true;

        return $configuration;
    }
}