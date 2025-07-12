<?php

declare(strict_types=1);

namespace SquareRouting\Core\Schema;

enum Permission: string
{
    // User management
    case USERS_CREATE = 'users.create';
    case USERS_READ = 'users.read';
    case USERS_UPDATE = 'users.update';
    case USERS_DELETE = 'users.delete';
    
    // Content management
    case CONTENT_CREATE = 'content.create';
    case CONTENT_READ = 'content.read';
    case CONTENT_UPDATE = 'content.update';
    case CONTENT_DELETE = 'content.delete';
    case CONTENT_MODERATE = 'content.moderate';
    
    // System settings
    case SETTINGS_READ = 'settings.read';
    case SETTINGS_UPDATE = 'settings.update';
    
    // Profile management
    case PROFILE_READ = 'profile.read';
    case PROFILE_UPDATE = 'profile.update';

    /**
     * Get the description for this permission
     */
    public function getDescription(): string
    {
        return match($this) {
            self::USERS_CREATE => 'Create new users',
            self::USERS_READ => 'View user profiles',
            self::USERS_UPDATE => 'Edit user profiles',
            self::USERS_DELETE => 'Delete users',
            self::CONTENT_CREATE => 'Create content',
            self::CONTENT_READ => 'View content',
            self::CONTENT_UPDATE => 'Edit content',
            self::CONTENT_DELETE => 'Delete content',
            self::CONTENT_MODERATE => 'Moderate content',
            self::SETTINGS_READ => 'View system settings',
            self::SETTINGS_UPDATE => 'Update system settings',
            self::PROFILE_READ => 'View own profile',
            self::PROFILE_UPDATE => 'Update own profile',
        };
    }

    /**
     * Get all permissions as array with descriptions
     */
    public static function getAllWithDescriptions(): array
    {
        $permissions = [];
        foreach (self::cases() as $permission) {
            $permissions[] = [
                ColumnName::NAME => $permission->value,
                ColumnName::DESCRIPTION => $permission->getDescription(),
            ];
        }
        return $permissions;
    }

    /**
     * Get permissions by resource category
     */
    public static function getByResource(string $resource): array
    {
        return array_filter(self::cases(), function($permission) use ($resource) {
            return str_starts_with($permission->value, $resource . '.');
        });
    }

    /**
     * Parse permission name into resource and action
     */
    public function parse(): array
    {
        $parts = explode('.', $this->value, 2);
        
        return [
            'resource' => $parts[0] ?? '',
            'action' => $parts[1] ?? '',
            'full' => $this->value
        ];
    }
}