<?php

declare(strict_types=1);

namespace SquareRouting\Core;

use RuntimeException;
use InvalidArgumentException;
use SquareRouting\Core\Database\DatabaseDialect;
use SquareRouting\Core\Database\ForeignKey;
use SquareRouting\Core\Database\ForeignKeyAction;
use SquareRouting\Core\Database\ColumnType;
use SquareRouting\Core\Database\Table;
class Account
{
    private Database $db;
    private string $tableName = 'users';
    private string $sessionKey = 'userId';
    private int $passwordMinLength = 8;
    private RateLimiter $rateLimiter;
    private string $loginRateLimitKey = 'loginAttempts';

    public function __construct(DependencyContainer $container)
    {
        $this->db = $container->get(Database::class);
        $this->rateLimiter = $container->get(RateLimiter::class);
        // Define rate limit for login attempts: 5 attempts within 15 minutes (900 seconds)
        $this->rateLimiter->setLimit($this->loginRateLimitKey, 5, 900);

        if (!$this->db->isConnectionActive()) {
            throw new RuntimeException('Account feature needs a Database. Database connection not established or is inactive. Ensure the Database class is correctly configured and connected in the DependencyContainer.');
        }
        
        $this->ensureUserTableExists();
    }

    /**
     * Register a new user account
     */
    public function register(string $email, string $password, array $additionalData = []): bool
    {

        if ($this->emailExists($email)) {
            throw new InvalidArgumentException('Email address already exists');
        }

        $userData = array_merge([
            'email' => strtolower(trim($email)),
            'password' => $this->hashPassword($password),
            'createdAt' => date('Y-m-d H:i:s'),
            'emailVerified' => 0,
            'status' => 'active'
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
        
        if (!$user || !$this->verifyPassword($password, $user['password'])) {
            $this->rateLimiter->registerAttempt($this->loginRateLimitKey, $email);
            throw new InvalidArgumentException('Invalid email or password');
        }

        if ($user['status'] !== 'active') {
            throw new RuntimeException('Account is not active');
        }

        // Successful login
        $this->rateLimiter->unblockClient($this->loginRateLimitKey, $email); // Clear any previous failed attempts
        $this->updateLastLogin($user['id']);
        $this->startSession($user['id']);

        if ($rememberMe) {
            $this->setRememberToken($user['id']);
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
     * Get current logged in user data
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $userId = $_SESSION[$this->sessionKey] ?? null;
        if (!$userId) {
            return null;
        }

        return $this->getUserById($userId);
    }

    /**
     * Change user password
     */
    public function changePassword(string $currentPassword, string $newPassword, ?int $userId = null): bool
    {
        $userId = $userId ?? $this->getCurrentUserId();
        
        if (!$userId) {
            throw new RuntimeException('User not logged in');
        }

        $user = $this->getUserById($userId);
        if (!$user) {
            throw new RuntimeException('User not found');
        }

        if (!$this->verifyPassword($currentPassword, $user['password'])) {
            throw new InvalidArgumentException('Current password is incorrect');
        }


        $result = $this->db->update($this->tableName, [
            'password' => $this->hashPassword($newPassword),
            'updatedAt' => date('Y-m-d H:i:s')
        ], ['id' => $userId]);

        return $result > 0;
    }

    /**
     * Reset password with token
     */
    public function resetPassword(string $token, string $newPassword): bool
    {

        $user = $this->getUserByResetToken($token);
        if (!$user) {
            throw new InvalidArgumentException('Invalid or expired reset token');
        }

        $result = $this->db->update($this->tableName, [
            'password' => $this->hashPassword($newPassword),
            'resetToken' => null,
            'resetTokenExpires' => null,
            'updatedAt' => date('Y-m-d H:i:s')
        ], ['id' => $user['id']]);

        return $result > 0;
    }

    /**
     * Generate password reset token
     */
    public function generateResetToken(string $email): string
    {
        $email = strtolower(trim($email));

        $user = $this->getUserByEmail($email);
        if (!$user) {
            throw new InvalidArgumentException('Email address not found');
        }

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $this->db->update($this->tableName, [
            'resetToken' => $token,
            'resetTokenExpires' => $expires,
            'updatedAt' => date('Y-m-d H:i:s')
        ], ['id' => $user['id']]);

        return $token;
    }

    /**
     * Update user profile
     */
    public function updateProfile(array $data, ?int $userId = null): bool
    {
        $userId = $userId ?? $this->getCurrentUserId();
        
        if (!$userId) {
            throw new RuntimeException('User not logged in');
        }

        // Remove sensitive fields
        unset($data['id'], $data['password'], $data['resetToken'], $data['resetTokenExpires']);

        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
            
            if ($this->emailExistsForOtherUser($data['email'], $userId)) {
                throw new InvalidArgumentException('Email address already exists');
            }
        }

        $data['updatedAt'] = date('Y-m-d H:i:s');

        $result = $this->db->update($this->tableName, $data, ['id' => $userId]);
        return $result > 0;
    }

    /**
     * Delete user account
     */
    public function deleteAccount(?int $userId = null): bool
    {
        $userId = $userId ?? $this->getCurrentUserId();
        
        if (!$userId) {
            throw new RuntimeException('User not logged in');
        }

        $result = $this->db->delete($this->tableName, ['id' => $userId]);
        
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
        if (!$user) {
            throw new InvalidArgumentException('Invalid verification token');
        }

        $result = $this->db->update($this->tableName, [
            'emailVerified' => 1,
            'emailVerificationToken' => null,
            'updatedAt' => date('Y-m-d H:i:s')
        ], ['id' => $user['id']]);

        return $result > 0;
    }

    /**
     * Generate email verification token
     */
    public function generateVerificationToken(?int $userId = null): string
    {
        $userId = $userId ?? $this->getCurrentUserId();
        
        if (!$userId) {
            throw new RuntimeException('User not logged in');
        }

        $token = bin2hex(random_bytes(32));

        $this->db->update($this->tableName, [
            'emailVerificationToken' => $token,
            'updatedAt' => date('Y-m-d H:i:s')
        ], ['id' => $userId]);

        return $token;
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
        return $this->db->exists($this->tableName, ['email' => $email]);
    }

    private function emailExistsForOtherUser(string $email, int $excludeUserId): bool
    {
        $sql = "SELECT id FROM {$this->tableName} WHERE email = :email AND id != :userId LIMIT 1";
        return $this->db->fetch($sql, ['email' => $email, 'userId' => $excludeUserId]) !== false;
    }

    private function userExists(int $userId): bool
    {
        return $this->db->exists($this->tableName, ['id' => $userId]);
    }

    private function getUserByEmail(string $email): ?array
    {
        $user = $this->db->fetch("SELECT * FROM {$this->tableName} WHERE email = :email", ['email' => $email]);
        return $user ?: null;
    }

    private function getUserById(int $userId): ?array
    {
        $user = $this->db->fetch("SELECT * FROM {$this->tableName} WHERE id = :id", ['id' => $userId]);
        return $user ?: null;
    }

    private function getUserByResetToken(string $token): ?array
    {
        $sql = "SELECT * FROM {$this->tableName} WHERE resetToken = :token AND resetTokenExpires > :now";
        $user = $this->db->fetch($sql, ['token' => $token, 'now' => date('Y-m-d H:i:s')]);
        return $user ?: null;
    }

    private function getUserByVerificationToken(string $token): ?array
    {
        $user = $this->db->fetch("SELECT * FROM {$this->tableName} WHERE emailVerificationToken = :token", ['token' => $token]);
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
            'rememberToken' => hash('sha256', $token),
            'updatedAt' => date('Y-m-d H:i:s')
        ], ['id' => $userId]);

        setcookie('rememberToken', $token, $expires, '/', '', true, true);
    }

    private function clearRememberToken(): void
    {
        if (isset($_COOKIE['rememberToken'])) {
            $hashedToken = hash('sha256', $_COOKIE['rememberToken']);
            $this->db->update($this->tableName, ['rememberToken' => null], ['rememberToken' => $hashedToken]);
            setcookie('rememberToken', '', time() - 3600, '/', '', true, true);
        }
    }

    private function validateRememberToken(string $token): bool
    {
        $hashedToken = hash('sha256', $token);
        $user = $this->db->fetch("SELECT * FROM {$this->tableName} WHERE rememberToken = :token", ['token' => $hashedToken]);
        
        if ($user) {
            $this->startSession($user['id']);
            return true;
        }

        return false;
    }

    private function updateLastLogin(int $userId): void
    {
        $this->db->update($this->tableName, [
            'lastLogin' => date('Y-m-d H:i:s')
        ], ['id' => $userId]);
    }

    private function ensureUserTableExists(): void
    {
            $this->createUserTable();
    }

    private function createUserTable(): void
    {
        // Create users table using ORM-style pattern
        $users = new Table('users');
        
        // Define columns
        $users->id = ColumnType::INT;
        $users->email = ColumnType::VARCHAR;
        $users->password = ColumnType::VARCHAR;
        $users->username = ColumnType::VARCHAR;
        $users->status = ColumnType::VARCHAR;
        $users->emailVerified = ColumnType::BOOLEAN;
        $users->emailVerificationToken = ColumnType::VARCHAR;
        $users->resetToken = ColumnType::VARCHAR;
        $users->resetTokenExpires = ColumnType::DATETIME;
        $users->rememberToken = ColumnType::VARCHAR;
        $users->lastLogin = ColumnType::DATETIME;
        $users->createdAt = ColumnType::DATETIME;
        $users->updatedAt = ColumnType::DATETIME;

        // Configure id column
        $users->id->autoIncrement = true;

        // Configure email column
        $users->email->length = 255;
        $users->email->nullable = false;
        $users->email->unique = true;

        // Configure password column
        $users->password->length = 255;
        $users->password->nullable = false;

        // Configure username column
        $users->username->length = 100;
        $users->username->nullable = false;
        $users->username->unique = true;

        // Configure status column
        $users->status->length = 20;
        $users->status->nullable = false;
        $users->status->default = 'active';

        // Configure email verification
        $users->emailVerified->nullable = false;
        $users->emailVerified->default = false;
        $users->emailVerificationToken->length = 64;
        $users->emailVerificationToken->nullable = true;

        // Configure reset token
        $users->resetToken->length = 64;
        $users->resetToken->nullable = true;
        $users->resetTokenExpires->nullable = true;

        // Configure remember token
        $users->rememberToken->length = 64;
        $users->rememberToken->nullable = true;

        // Configure timestamps
        $users->lastLogin->nullable = true;
        $users->createdAt->nullable = false;
        $users->createdAt->default = 'CURRENT_TIMESTAMP';
        $users->updatedAt->nullable = true;

        // Create the table
        $this->db->createTableIfNotExists($users);
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
}