<?php

declare(strict_types=1);

namespace SquareRouting\Core;

use SquareRouting\Core\Database\ColumnType;
use SquareRouting\Core\Database\ForeignKey;
use SquareRouting\Core\Database\ForeignKeyAction;
use SquareRouting\Core\Database\Table;
use SquareRouting\Core\Database\TableSchema;

class Schema
{
    #[TableSchema]
    public function role(): Table
    {
        $role = new Table('role');
        
        // Define columns
        $role->id = ColumnType::INT;
        $role->name = ColumnType::VARCHAR;
        $role->description = ColumnType::TEXT;
        $role->level = ColumnType::INT;
        $role->created_at = ColumnType::DATETIME;
        $role->updated_at = ColumnType::DATETIME;

        // Configure id column
        $role->id->autoIncrement = true;

        // Configure name column
        $role->name->length = 50;
        $role->name->nullable = false;
        $role->name->unique = true;

        // Configure description column
        $role->description->nullable = true;

        // Configure level column (for hierarchy: 1=admin, 2=moderator, 3=user)
        $role->level->nullable = false;
        $role->level->default = 3;

        // Configure timestamps
        $role->created_at->nullable = false;
        $role->created_at->default = 'CURRENT_TIMESTAMP';
        $role->updated_at->nullable = true;

        return $role;
    }

    #[TableSchema]
    public function permission(): Table
    {
        $permission = new Table('permission');
        
        // Define columns
        $permission->id = ColumnType::INT;
        $permission->name = ColumnType::VARCHAR;
        $permission->description = ColumnType::TEXT;
        $permission->created_at = ColumnType::DATETIME;
        $permission->updated_at = ColumnType::DATETIME;

        // Configure id column
        $permission->id->autoIncrement = true;

        // Configure name column (e.g., 'users.create', 'content.moderate')
        $permission->name->length = 100;
        $permission->name->nullable = false;
        $permission->name->unique = true;

        // Configure description column
        $permission->description->nullable = true;

        // Configure timestamps
        $permission->created_at->nullable = false;
        $permission->created_at->default = 'CURRENT_TIMESTAMP';
        $permission->updated_at->nullable = true;

        return $permission;
    }

    #[TableSchema]
    public function role_permissions(): Table
    {
        $role_permissions = new Table('role_permissions');
        
        // Define columns
        $role_permissions->id = ColumnType::INT;
        $role_permissions->role_id = ColumnType::INT;
        $role_permissions->permission_id = ColumnType::INT;
        $role_permissions->created_at = ColumnType::DATETIME;

        // Configure id column
        $role_permissions->id->autoIncrement = true;

        // Configure role_id with foreign key
        $roleTable = $this->role();
        $role_permissions->role_id->foreignKey = new ForeignKey($roleTable, $roleTable->id);
        $role_permissions->role_id->foreignKey->onDelete = ForeignKeyAction::CASCADE;
        $role_permissions->role_id->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
        $role_permissions->role_id->nullable = false;

        // Configure permission_id with foreign key
        $permissionTable = $this->permission();
        $role_permissions->permission_id->foreignKey = new ForeignKey($permissionTable, $permissionTable->id);
        $role_permissions->permission_id->foreignKey->onDelete = ForeignKeyAction::CASCADE;
        $role_permissions->permission_id->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
        $role_permissions->permission_id->nullable = false;

        // Configure timestamp
        $role_permissions->created_at->nullable = false;
        $role_permissions->created_at->default = 'CURRENT_TIMESTAMP';

        return $role_permissions;
    }

    #[TableSchema]
    public function account(): Table
    {
        $account = new Table('account');
        
        // Define columns
        $account->id = ColumnType::INT;
        $account->role_id = ColumnType::INT;
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

        // Configure role_id with foreign key
        $roleTable = $this->role();
        $account->role_id->foreignKey = new ForeignKey($roleTable, $roleTable->id);
        $account->role_id->foreignKey->onDelete = ForeignKeyAction::RESTRICT;
        $account->role_id->foreignKey->onUpdate = ForeignKeyAction::CASCADE;
        $account->role_id->nullable = false;
        $account->role_id->default = 3; // Default to user role

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

        return $account;
    }

    #[TableSchema]
    public function configuration(): Table
    {
        $configuration = new Table('configuration');

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