<?php

declare(strict_types=1);

namespace SquareRouting\Core;

use InvalidArgumentException;
use RuntimeException;
use SquareRouting\Core\Schema\ColumnName;
use SquareRouting\Core\Schema\TableName;
use Exception;

class Account
{
    private Database $db;
    private string $tableName = TableName::ACCOUNT;
    private string $sessionKey = 'userId';
    private int $passwordMinLength = 8;
    private RateLimiter $rateLimiter;
    private string $loginRateLimitKey = 'loginAttempts';
    
    // Permission cache TTL (5 minutes)
    private int $permissionCacheTtl = 300;

    public function __construct(DependencyContainer $container)
    {
        $this->db = $container->get(Database::class);
        $this->rateLimiter = $container->get(RateLimiter::class);
        // Define rate limit for login attempts: 5 attempts within 15 minutes (900 seconds)
        $this->rateLimiter->setLimit($this->loginRateLimitKey, 5, 900);

        if (! $this->db->isConnectionActive()) {
            throw new RuntimeException('Account feature needs a Database. Database connection not established or is inactive. Ensure the Database class is correctly configured and connected in the DependencyContainer.');
        }
    }

    /**
     * Register a new user account
     */
    public function register(string $email, string $password, array $additionalData = []): bool
    {
        if ($this->emailExists($email)) {
            throw new InvalidArgumentException('Email address already exists');
        }

        // Set default role if not specified
        if (!isset($additionalData[ColumnName::ROLE_ID])) {
            $userRole = $this->getRoleByName('user');
            if (!$userRole) {
                throw new RuntimeException('Default user role not found in database. Please run initializeDefaultRoles() first.');
            }
            $additionalData[ColumnName::ROLE_ID] = $userRole[ColumnName::ID];
        }

        $userData = array_merge([
            ColumnName::EMAIL => strtolower(trim($email)),
            ColumnName::PASSWORD => $this->hashPassword($password),
            ColumnName::CREATED_AT => date('Y-m-d H:i:s'),
            ColumnName::EMAIL_VERIFIED => 0,
            ColumnName::STATUS => 'active',
        ], $additionalData);

        $userId = $this->db->insert($this->tableName, $userData);

        return $userId !== false;
    }

    /**
     * Authenticate user login
     */
    public function login(string $email, string $password, bool $rememberMe = false): bool
    {
        if (empty($password)) {
            throw new InvalidArgumentException('Password cannot be empty');
        }

        $email = strtolower(trim($email));

        // Check for rate limiting before attempting login
        if ($this->rateLimiter->isLimitExceeded($this->loginRateLimitKey, $email)) {
            throw new RuntimeException('Too many failed login attempts. Please try again after ' . $this->rateLimiter->getRemainingTimeToReset($this->loginRateLimitKey, $email) . ' seconds.');
        }

        $user = $this->getUserByEmail($email);

        if (! $user || ! $this->verifyPassword($password, $user[ColumnName::PASSWORD])) {
            $this->rateLimiter->registerAttempt($this->loginRateLimitKey, $email);
            throw new InvalidArgumentException('Invalid email or password');
        }

        if ($user[ColumnName::STATUS] !== 'active') {
            throw new RuntimeException('Account is not active');
        }

        // Successful login
        $this->rateLimiter->unblockClient($this->loginRateLimitKey, $email); // Clear any previous failed attempts
        $this->updateLastLogin($user[ColumnName::ID]);
        $this->startSession($user[ColumnName::ID]);

        if ($rememberMe) {
            $this->setRememberToken($user[ColumnName::ID]);
        }

        return true;
    }

    /**
     * Log out current user
     */
    public function logout(): bool
    {
        $this->clearRememberToken();
        $this->destroySession();

        return true;
    }

    /**
     * Check if user is currently logged in
     */
    public function isLoggedIn(): bool
    {
        if (isset($_SESSION[$this->sessionKey])) {
            return $this->userExists($_SESSION[$this->sessionKey]);
        }

        // Check remember me token
        if (isset($_COOKIE['rememberToken'])) {
            return $this->validateRememberToken($_COOKIE['rememberToken']);
        }

        return false;
    }

    /**
     * Get current logged in user data with role information
     */
    public function getCurrentUser(): ?array
    {
        if (! $this->isLoggedIn()) {
            return null;
        }

        $userId = $_SESSION[$this->sessionKey] ?? null;
        if (! $userId) {
            return null;
        }

        return $this->getUserWithRole($userId);
    }

    // =================================================================
    // ROLE & PERMISSION METHODS
    // =================================================================

    /**
     * Check if current user has a specific permission
     */
    public function hasPermission(string $permissionName, ?int $userId = null): bool
    {
        $userId = $userId ?? $this->getCurrentUserId();
        if (!$userId) {
            return false;
        }

        $permissions = $this->getUserPermissions($userId);
        return in_array($permissionName, array_column($permissions, ColumnName::NAME));
    }

    /**
     * Check if current user has a specific role
     */
    public function hasRole(string $roleName, ?int $userId = null): bool
    {
        $userId = $userId ?? $this->getCurrentUserId();
        if (!$userId) {
            return false;
        }

        $userRole = $this->getUserRole($userId);
        return $userRole && $userRole[ColumnName::NAME] === $roleName;
    }

    /**
     * Check if current user has permission for resource and action
     * @deprecated Use hasPermission() with full permission name instead (e.g., 'users.create')
     */
    public function can(string $resource, string $action, ?int $userId = null): bool
    {
        return $this->hasPermission("{$resource}.{$action}", $userId);
    }

    /**
     * Check if current user is admin
     */
    public function isAdmin(?int $userId = null): bool
    {
        return $this->hasRole('admin', $userId);
    }

    /**
     * Check if current user is moderator or higher
     */
    public function isModerator(?int $userId = null): bool
    {
        $userId = $userId ?? $this->getCurrentUserId();
        if (!$userId) {
            return false;
        }

        $userRole = $this->getUserRole($userId);
        return $userRole && in_array($userRole[ColumnName::NAME], ['admin', 'moderator']);
    }

    /**
     * Get user's role
     */
    public function getUserRole(?int $userId = null): ?array
    {
        $userId = $userId ?? $this->getCurrentUserId();
        if (!$userId) {
            return null;
        }

        $cacheKey = "user_role_{$userId}";
        
        $sql = "
            SELECT r.* 
            FROM " . TableName::ROLE . " r 
            INNER JOIN " . TableName::ACCOUNT . " a ON a." . ColumnName::ROLE_ID . " = r." . ColumnName::ID . " 
            WHERE a." . ColumnName::ID . " = :userId
        ";

        return $this->db->fetch($sql, ['userId' => $userId], $this->permissionCacheTtl) ?: null;
    }

    /**
     * Get all permissions for a user
     */
    public function getUserPermissions(?int $userId = null): array
    {
        $userId = $userId ?? $this->getCurrentUserId();
        if (!$userId) {
            return [];
        }

        $sql = "
            SELECT DISTINCT p.* 
            FROM " . TableName::PERMISSION . " p
            INNER JOIN " . TableName::ROLE_PERMISSIONS . " rp ON rp." . ColumnName::PERMISSION_ID . " = p." . ColumnName::ID . "
            INNER JOIN " . TableName::ROLE . " r ON r." . ColumnName::ID . " = rp." . ColumnName::ROLE_ID . "
            INNER JOIN " . TableName::ACCOUNT . " a ON a." . ColumnName::ROLE_ID . " = r." . ColumnName::ID . "
            WHERE a." . ColumnName::ID . " = :userId
        ";

        return $this->db->fetchAll($sql, ['userId' => $userId], $this->permissionCacheTtl);
    }

    /**
     * Get user with role information
     */
    public function getUserWithRole(?int $userId = null): ?array
    {
        $userId = $userId ?? $this->getCurrentUserId();
        if (!$userId) {
            return null;
        }

        $sql = "
            SELECT a.*, r." . ColumnName::NAME . " as role_name, r." . ColumnName::LEVEL . " as role_level
            FROM " . TableName::ACCOUNT . " a 
            LEFT JOIN " . TableName::ROLE . " r ON r." . ColumnName::ID . " = a." . ColumnName::ROLE_ID . " 
            WHERE a." . ColumnName::ID . " = :userId
        ";

        return $this->db->fetch($sql, ['userId' => $userId], $this->permissionCacheTtl) ?: null;
    }

    /**
     * Assign role to user
     */
    public function assignRole(int $userId, string $roleName): bool
    {
        $role = $this->getRoleByName($roleName);
        if (!$role) {
            throw new InvalidArgumentException("Role '{$roleName}' does not exist");
        }

        $result = $this->db->update($this->tableName, [
            ColumnName::ROLE_ID => $role[ColumnName::ID],
            ColumnName::UPDATED_AT => date('Y-m-d H:i:s'),
        ], [ColumnName::ID => $userId]);

        if ($result > 0) {
            // Clear permission cache for this user
            $this->clearUserPermissionCache($userId);
        }

        return $result > 0;
    }

    /**
     * Get role by name
     */
    public function getRoleByName(string $roleName): ?array
    {
        $sql = "SELECT * FROM " . TableName::ROLE . " WHERE " . ColumnName::NAME . " = :name";
        return $this->db->fetch($sql, ['name' => $roleName], $this->permissionCacheTtl) ?: null;
    }

    /**
     * Get all roles
     */
    public function getAllRoles(): array
    {
        $sql = "SELECT * FROM " . TableName::ROLE . " ORDER BY " . ColumnName::LEVEL . " ASC";
        return $this->db->fetchAll($sql, [], $this->permissionCacheTtl);
    }

    /**
     * Get permissions for a specific role
     */
    public function getRolePermissions(string $roleName): array
    {
        $sql = "
            SELECT p.* 
            FROM " . TableName::PERMISSION . " p
            INNER JOIN " . TableName::ROLE_PERMISSIONS . " rp ON rp." . ColumnName::PERMISSION_ID . " = p." . ColumnName::ID . "
            INNER JOIN " . TableName::ROLE . " r ON r." . ColumnName::ID . " = rp." . ColumnName::ROLE_ID . "
            WHERE r." . ColumnName::NAME . " = :roleName
        ";

        return $this->db->fetchAll($sql, ['roleName' => $roleName], $this->permissionCacheTtl);
    }

    /**
     * Check if user has minimum role level
     */
    public function hasMinimumRoleLevel(int $minLevel, ?int $userId = null): bool
    {
        $userId = $userId ?? $this->getCurrentUserId();
        if (!$userId) {
            return false;
        }

        $userRole = $this->getUserRole($userId);
        return $userRole && $userRole[ColumnName::LEVEL] <= $minLevel; // Lower level = higher privilege
    }

    /**
     * Require permission for current user (throws exception if not authorized)
     */
    public function requirePermission(string $permissionName, ?int $userId = null): void
    {
        if (!$this->hasPermission($permissionName, $userId)) {
            throw new RuntimeException("Access denied. Permission '{$permissionName}' required.");
        }
    }

    /**
     * Require role for current user (throws exception if not authorized)
     */
    public function requireRole(string $roleName, ?int $userId = null): void
    {
        if (!$this->hasRole($roleName, $userId)) {
            throw new RuntimeException("Access denied. Role '{$roleName}' required.");
        }
    }

    /**
     * Require admin access (throws exception if not authorized)
     */
    public function requireAdmin(?int $userId = null): void
    {
        if (!$this->isAdmin($userId)) {
            throw new RuntimeException("Access denied. Administrator privileges required.");
        }
    }

    /**
     * Clear permission cache for a specific user
     */
    private function clearUserPermissionCache(int $userId): void
    {
        // In a more sophisticated setup, you would clear specific cache keys
        // For now, we mark the database as dirty which clears all cache
        $this->db->markDirty();
    }

    // =================================================================
    // ROLE INITIALIZATION & VALIDATION
    // =================================================================

    /**
     * Initialize default roles and permissions in the database
     */
    public function initializeDefaultRoles(bool $createPermissions = true): bool
    {
        $this->db->beginTransaction();

        try {
            // Create default roles
            $this->createDefaultRoles();
            
            if ($createPermissions) {
                // Create default permissions
                $this->createDefaultPermissions();
                
                // Assign permissions to roles
                $this->assignDefaultPermissions();
            }

            $this->db->commit();
            $this->db->markDirty(); // Clear cache
            
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw new RuntimeException('Failed to initialize default roles: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create default roles if they don't exist
     */
    public function createDefaultRoles(): array
    {
        $defaultRoles = [
            [ColumnName::NAME => 'admin', ColumnName::DESCRIPTION => 'Full system administrator access', ColumnName::LEVEL => 1],
            [ColumnName::NAME => 'moderator', ColumnName::DESCRIPTION => 'Content moderation and user management', ColumnName::LEVEL => 2],
            [ColumnName::NAME => 'user', ColumnName::DESCRIPTION => 'Basic user access', ColumnName::LEVEL => 3],
        ];

        $createdRoles = [];

        foreach ($defaultRoles as $roleData) {
            $existingRole = $this->getRoleByName($roleData[ColumnName::NAME]);
            
            if (!$existingRole) {
                $roleData[ColumnName::CREATED_AT] = date('Y-m-d H:i:s');
                $roleId = $this->db->insert(TableName::ROLE, $roleData);
                
                if ($roleId) {
                    $createdRoles[] = array_merge($roleData, [ColumnName::ID => $roleId]);
                }
            } else {
                $createdRoles[] = $existingRole;
            }
        }

        return $createdRoles;
    }

    /**
     * Create default permissions
     */
    public function createDefaultPermissions(): array
    {
        $defaultPermissions = [
            // User management
            [ColumnName::NAME => 'users.create', ColumnName::DESCRIPTION => 'Create new users'],
            [ColumnName::NAME => 'users.read', ColumnName::DESCRIPTION => 'View user profiles'],
            [ColumnName::NAME => 'users.update', ColumnName::DESCRIPTION => 'Edit user profiles'],
            [ColumnName::NAME => 'users.delete', ColumnName::DESCRIPTION => 'Delete users'],
            
            // Content management
            [ColumnName::NAME => 'content.create', ColumnName::DESCRIPTION => 'Create content'],
            [ColumnName::NAME => 'content.read', ColumnName::DESCRIPTION => 'View content'],
            [ColumnName::NAME => 'content.update', ColumnName::DESCRIPTION => 'Edit content'],
            [ColumnName::NAME => 'content.delete', ColumnName::DESCRIPTION => 'Delete content'],
            [ColumnName::NAME => 'content.moderate', ColumnName::DESCRIPTION => 'Moderate content'],
            
            // System settings
            [ColumnName::NAME => 'settings.read', ColumnName::DESCRIPTION => 'View system settings'],
            [ColumnName::NAME => 'settings.update', ColumnName::DESCRIPTION => 'Update system settings'],
            
            // Profile management
            [ColumnName::NAME => 'profile.read', ColumnName::DESCRIPTION => 'View own profile'],
            [ColumnName::NAME => 'profile.update', ColumnName::DESCRIPTION => 'Update own profile'],
        ];

        $createdPermissions = [];

        foreach ($defaultPermissions as $permissionData) {
            $existing = $this->getPermissionByName($permissionData[ColumnName::NAME]);
            
            if (!$existing) {
                $permissionData[ColumnName::CREATED_AT] = date('Y-m-d H:i:s');
                $permissionId = $this->db->insert(TableName::PERMISSION, $permissionData);
                
                if ($permissionId) {
                    $createdPermissions[] = array_merge($permissionData, [ColumnName::ID => $permissionId]);
                }
            } else {
                $createdPermissions[] = $existing;
            }
        }

        return $createdPermissions;
    }

    /**
     * Assign default permissions to roles
     */
    public function assignDefaultPermissions(): void
    {
        $rolePermissions = [
            'admin' => [
                'users.create', 'users.read', 'users.update', 'users.delete',
                'content.create', 'content.read', 'content.update', 'content.delete', 'content.moderate',
                'settings.read', 'settings.update',
                'profile.read', 'profile.update'
            ],
            'moderator' => [
                'users.read', 'users.update',
                'content.create', 'content.read', 'content.update', 'content.delete', 'content.moderate',
                'profile.read', 'profile.update'
            ],
            'user' => [
                'content.read',
                'profile.read', 'profile.update'
            ]
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = $this->getRoleByName($roleName);
            if (!$role) {
                continue;
            }

            foreach ($permissions as $permissionName) {
                $permission = $this->getPermissionByName($permissionName);
                if (!$permission) {
                    continue;
                }

                // Check if assignment already exists
                $existingAssignment = $this->db->fetch(
                    "SELECT " . ColumnName::ID . " FROM " . TableName::ROLE_PERMISSIONS . " WHERE " . ColumnName::ROLE_ID . " = :roleId AND " . ColumnName::PERMISSION_ID . " = :permissionId",
                    ['roleId' => $role[ColumnName::ID], 'permissionId' => $permission[ColumnName::ID]]
                );

                if (!$existingAssignment) {
                    $this->db->insert(TableName::ROLE_PERMISSIONS, [
                        ColumnName::ROLE_ID => $role[ColumnName::ID],
                        ColumnName::PERMISSION_ID => $permission[ColumnName::ID],
                        ColumnName::CREATED_AT => date('Y-m-d H:i:s')
                    ]);
                }
            }
        }
    }

    /**
     * Validate that required roles exist in the database
     */
    public function validateRequiredRoles(): bool
    {
        $requiredRoles = ['admin', 'moderator', 'user'];
        $missingRoles = [];

        foreach ($requiredRoles as $roleName) {
            if (!$this->getRoleByName($roleName)) {
                $missingRoles[] = $roleName;
            }
        }

        if (!empty($missingRoles)) {
            throw new RuntimeException(
                'Required roles missing from database: ' . implode(', ', $missingRoles) . 
                '. Please run initializeDefaultRoles() to create them.'
            );
        }

        return true;
    }

    /**
     * Check if default roles are initialized
     */
    public function areDefaultRolesInitialized(): bool
    {
        $requiredRoles = ['admin', 'moderator', 'user'];
        
        foreach ($requiredRoles as $roleName) {
            if (!$this->getRoleByName($roleName)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get permission by name
     */
    public function getPermissionByName(string $permissionName): ?array
    {
        $sql = "SELECT * FROM " . TableName::PERMISSION . " WHERE " . ColumnName::NAME . " = :name";
        return $this->db->fetch($sql, ['name' => $permissionName], $this->permissionCacheTtl) ?: null;
    }

    /**
     * Get all permissions
     */
    public function getAllPermissions(): array
    {
        $sql = "SELECT * FROM " . TableName::PERMISSION . " ORDER BY " . ColumnName::NAME;
        return $this->db->fetchAll($sql, [], $this->permissionCacheTtl);
    }

    /**
     * Parse permission name into resource and action
     * E.g., "users.create" -> ['resource' => 'users', 'action' => 'create']
     */
    public function parsePermissionName(string $permissionName): array
    {
        $parts = explode('.', $permissionName, 2);
        
        return [
            'resource' => $parts[0] ?? '',
            'action' => $parts[1] ?? '',
            'full' => $permissionName
        ];
    }

    /**
     * Get permissions grouped by resource
     */
    public function getPermissionsByResource(): array
    {
        $permissions = $this->getAllPermissions();
        $grouped = [];

        foreach ($permissions as $permission) {
            $parsed = $this->parsePermissionName($permission[ColumnName::NAME]);
            $resource = $parsed['resource'];
            
            if (!isset($grouped[$resource])) {
                $grouped[$resource] = [];
            }
            
            $grouped[$resource][] = $permission;
        }

        return $grouped;
    }

    /**
     * Reset roles and permissions (careful - this deletes all existing data!)
     */
    public function resetRolesAndPermissions(): bool
    {
        $this->db->beginTransaction();

        try {
            // Delete in correct order due to foreign keys
            $this->db->query("DELETE FROM " . TableName::ROLE_PERMISSIONS);
            $this->db->query("DELETE FROM " . TableName::PERMISSION);
            $this->db->query("DELETE FROM " . TableName::ROLE);

            // Reinitialize
            $this->initializeDefaultRoles(true);

            $this->db->commit();
            $this->db->markDirty();
            
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw new RuntimeException('Failed to reset roles and permissions: ' . $e->getMessage(), 0, $e);
        }
    }

    // =================================================================
    // EXISTING METHODS (unchanged)
    // =================================================================

    /**
     * Change user password
     */
    public function changePassword(string $currentPassword, string $newPassword, ?int $userId = null): bool
    {
        $userId = $userId ?? $this->getCurrentUserId();

        if (! $userId) {
            throw new RuntimeException('User not logged in');
        }

        $user = $this->getUserById($userId);
        if (! $user) {
            throw new RuntimeException('User not found');
        }

        if (! $this->verifyPassword($currentPassword, $user[ColumnName::PASSWORD])) {
            throw new InvalidArgumentException('Current password is incorrect');
        }

        $result = $this->db->update($this->tableName, [
            ColumnName::PASSWORD => $this->hashPassword($newPassword),
            ColumnName::UPDATED_AT => date('Y-m-d H:i:s'),
        ], [ColumnName::ID => $userId]);

        return $result > 0;
    }

    /**
     * Reset password with token
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        $user = $this->getUserByResetToken($token);
        if (! $user) {
            throw new InvalidArgumentException('Invalid or expired reset token');
        }

        $result = $this->db->update($this->tableName, [
            ColumnName::PASSWORD => $this->hashPassword($newPassword),
            ColumnName::RESET_TOKEN => null,
            ColumnName::RESET_TOKEN_EXPIRES => null,
            ColumnName::UPDATED_AT => date('Y-m-d H:i:s'),
        ], [ColumnName::ID => $user[ColumnName::ID]]);

        return $result > 0;
    }

    /**
     * Generate password reset token
     */
    public function generateResetToken(string $email): string
    {
        $email = strtolower(trim($email));

        $user = $this->getUserByEmail($email);
        if (! $user) {
            throw new InvalidArgumentException('Email address not found');
        }

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $this->db->update($this->tableName, [
            ColumnName::RESET_TOKEN => $token,
            ColumnName::RESET_TOKEN_EXPIRES => $expires,
            ColumnName::UPDATED_AT => date('Y-m-d H:i:s'),
        ], [ColumnName::ID => $user[ColumnName::ID]]);

        return $token;
    }

    /**
     * Update user profile
     */
    public function updateProfile(array $data, ?int $userId = null): bool
    {
        $userId = $userId ?? $this->getCurrentUserId();

        if (! $userId) {
            throw new RuntimeException('User not logged in');
        }

        // Remove sensitive fields
        unset($data[ColumnName::ID], $data[ColumnName::PASSWORD], $data[ColumnName::RESET_TOKEN], $data[ColumnName::RESET_TOKEN_EXPIRES], $data[ColumnName::ROLE_ID]);

        if (isset($data[ColumnName::EMAIL])) {
            $data[ColumnName::EMAIL] = strtolower(trim($data[ColumnName::EMAIL]));

            if ($this->emailExistsForOtherUser($data[ColumnName::EMAIL], $userId)) {
                throw new InvalidArgumentException('Email address already exists');
            }
        }

        $data[ColumnName::UPDATED_AT] = date('Y-m-d H:i:s');

        $result = $this->db->update($this->tableName, $data, [ColumnName::ID => $userId]);

        return $result > 0;
    }

    /**
     * Delete user account
     */
    public function deleteAccount(?int $userId = null): bool
    {
        $userId = $userId ?? $this->getCurrentUserId();

        if (! $userId) {
            throw new RuntimeException('User not logged in');
        }

        $result = $this->db->delete($this->tableName, [ColumnName::ID => $userId]);

        if ($result > 0) {
            $this->logout();
        }

        return $result > 0;
    }

    /**
     * Verify email address
     */
    public function verifyEmail(string $token): bool
    {
        $user = $this->getUserByVerificationToken($token);
        if (! $user) {
            throw new InvalidArgumentException('Invalid verification token');
        }

        $result = $this->db->update($this->tableName, [
            ColumnName::EMAIL_VERIFIED => 1,
            ColumnName::EMAIL_VERIFICATION_TOKEN => null,
            ColumnName::UPDATED_AT => date('Y-m-d H:i:s'),
        ], [ColumnName::ID => $user[ColumnName::ID]]);

        return $result > 0;
    }

    /**
     * Generate email verification token
     */
    public function generateVerificationToken(?int $userId = null): string
    {
        $userId = $userId ?? $this->getCurrentUserId();

        if (! $userId) {
            throw new RuntimeException('User not logged in');
        }

        $token = bin2hex(random_bytes(32));

        $this->db->update($this->tableName, [
            ColumnName::EMAIL_VERIFICATION_TOKEN => $token,
            ColumnName::UPDATED_AT => date('Y-m-d H:i:s'),
        ], [ColumnName::ID => $userId]);

        return $token;
    }

    // Configuration methods

    public function setTableName(string $tableName): self
    {
        $this->tableName = $tableName;

        return $this;
    }

    public function setSessionKey(string $sessionKey): self
    {
        $this->sessionKey = $sessionKey;

        return $this;
    }

    public function setPasswordMinLength(int $length): self
    {
        $this->passwordMinLength = $length;

        return $this;
    }

    public function setPermissionCacheTtl(int $ttl): self
    {
        $this->permissionCacheTtl = $ttl;

        return $this;
    }

    // Private helper methods

    private function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    private function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    private function emailExists(string $email): bool
    {
        return $this->db->exists($this->tableName, [ColumnName::EMAIL => $email]);
    }

    private function emailExistsForOtherUser(string $email, int $excludeUserId): bool
    {
        $sql = 'SELECT ' . ColumnName::ID . " FROM {$this->tableName} WHERE " . ColumnName::EMAIL . ' = :email AND ' . ColumnName::ID . ' != :userId LIMIT 1';

        return $this->db->fetch($sql, [ColumnName::EMAIL => $email, 'userId' => $excludeUserId]) !== false;
    }

    private function userExists(int $userId): bool
    {
        return $this->db->exists($this->tableName, [ColumnName::ID => $userId]);
    }

    private function getUserByEmail(string $email): ?array
    {
        $user = $this->db->fetch("SELECT * FROM {$this->tableName} WHERE " . ColumnName::EMAIL . ' = :email', [ColumnName::EMAIL => $email]);

        return $user ?: null;
    }

    private function getUserById(int $userId): ?array
    {
        $user = $this->db->fetch("SELECT * FROM {$this->tableName} WHERE " . ColumnName::ID . ' = :id', [ColumnName::ID => $userId]);

        return $user ?: null;
    }

    private function getUserByResetToken(string $token): ?array
    {
        $sql = "SELECT * FROM {$this->tableName} WHERE " . ColumnName::RESET_TOKEN . ' = :token AND ' . ColumnName::RESET_TOKEN_EXPIRES . ' > :now';
        $user = $this->db->fetch($sql, ['token' => $token, 'now' => date('Y-m-d H:i:s')]);

        return $user ?: null;
    }

    private function getUserByVerificationToken(string $token): ?array
    {
        $user = $this->db->fetch("SELECT * FROM {$this->tableName} WHERE " . ColumnName::EMAIL_VERIFICATION_TOKEN . ' = :token', ['token' => $token]);

        return $user ?: null;
    }

    private function getCurrentUserId(): ?int
    {
        return $_SESSION[$this->sessionKey] ?? null;
    }

    private function startSession(int $userId): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION[$this->sessionKey] = $userId;
    }

    private function destroySession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION[$this->sessionKey]);
            if (empty($_SESSION)) {
                session_destroy();
            }
        }
    }

    private function setRememberToken(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        $expires = time() + (30 * 24 * 60 * 60); // 30 days

        $this->db->update($this->tableName, [
            ColumnName::REMEMBER_TOKEN => hash('sha256', $token),
            ColumnName::UPDATED_AT => date('Y-m-d H:i:s'),
        ], [ColumnName::ID => $userId]);

        setcookie('rememberToken', $token, $expires, '/', '', true, true);
    }

    private function clearRememberToken(): void
    {
        if (isset($_COOKIE['rememberToken'])) {
            $hashedToken = hash('sha256', $_COOKIE['rememberToken']);
            $this->db->update($this->tableName, [ColumnName::REMEMBER_TOKEN => null], [ColumnName::REMEMBER_TOKEN => $hashedToken]);
            setcookie('rememberToken', '', time() - 3600, '/', '', true, true);
        }
    }

    private function validateRememberToken(string $token): bool
    {
        $hashedToken = hash('sha256', $token);
        $user = $this->db->fetch("SELECT * FROM {$this->tableName} WHERE " . ColumnName::REMEMBER_TOKEN . ' = :token', ['token' => $hashedToken]);

        if ($user) {
            $this->startSession($user[ColumnName::ID]);

            return true;
        }

        return false;
    }

    private function updateLastLogin(int $userId): void
    {
        $this->db->update($this->tableName, [
            ColumnName::LAST_LOGIN => date('Y-m-d H:i:s'),
        ], [ColumnName::ID => $userId]);
    }
}