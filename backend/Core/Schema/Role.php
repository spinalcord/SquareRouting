<?php

declare(strict_types=1);

namespace SquareRouting\Core\Schema;

enum Role: string
{
    case ADMIN = 'admin';
    case MODERATOR = 'moderator';
    case USER = 'user';

    /**
     * Get the description for this role
     */
    public function getDescription(): string
    {
        return match($this) {
            self::ADMIN => 'Full system administrator access',
            self::MODERATOR => 'Content moderation and user management',
            self::USER => 'Basic user access',
        };
    }

    /**
     * Get the level for this role (lower = higher privilege)
     */
    public function getLevel(): int
    {
        return match($this) {
            self::ADMIN => 1,
            self::MODERATOR => 2,
            self::USER => 3,
        };
    }

    /**
     * Get default permissions for this role
     */
    public function getDefaultPermissions(): array
    {
        return match($this) {
            self::ADMIN => [
                Permission::CLI_ACCESS,
                Permission::USERS_CREATE,
                Permission::USERS_READ,
                Permission::USERS_UPDATE,
                Permission::USERS_DELETE,
                Permission::CONTENT_CREATE,
                Permission::CONTENT_READ,
                Permission::CONTENT_UPDATE,
                Permission::CONTENT_DELETE,
                Permission::CONTENT_MODERATE,
                Permission::SETTINGS_READ,
                Permission::SETTINGS_UPDATE,
                Permission::PROFILE_READ,
                Permission::PROFILE_UPDATE,
            ],
            self::MODERATOR => [
                Permission::USERS_READ,
                Permission::USERS_UPDATE,
                Permission::CONTENT_CREATE,
                Permission::CONTENT_READ,
                Permission::CONTENT_UPDATE,
                Permission::CONTENT_DELETE,
                Permission::CONTENT_MODERATE,
                Permission::PROFILE_READ,
                Permission::PROFILE_UPDATE,
            ],
            self::USER => [
                Permission::CONTENT_READ,
                Permission::PROFILE_READ,
                Permission::PROFILE_UPDATE,
            ],
        };
    }

    /**
     * Get all roles as array with descriptions and levels
     */
    public static function getAllWithDetails(): array
    {
        $roles = [];
        foreach (self::cases() as $role) {
            $roles[] = [
                ColumnName::NAME => $role->value,
                ColumnName::DESCRIPTION => $role->getDescription(),
                ColumnName::LEVEL => $role->getLevel(),
            ];
        }
        return $roles;
    }

    /**
     * Check if this role has minimum level (lower level = higher privilege)
     */
    public function hasMinimumLevel(int $minLevel): bool
    {
        return $this->getLevel() <= $minLevel;
    }

    /**
     * Check if this role is admin or moderator
     */
    public function isModeratorOrHigher(): bool
    {
        return in_array($this, [self::ADMIN, self::MODERATOR]);
    }

    /**
     * Get role by name (case-insensitive)
     */
    public static function fromName(string $name): ?self
    {
        return self::tryFrom(strtolower($name));
    }

    /**
     * Get all role names as array
     */
    public static function getAllNames(): array
    {
        return array_map(fn($role) => $role->value, self::cases());
    }
}