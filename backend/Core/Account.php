<?php

declare(strict_types=1);

namespace SquareRouting\Core;

use InvalidArgumentException;
use RuntimeException;
use SquareRouting\Core\Database\Table;
use SquareRouting\Core\Scheme\ColumnName;
use SquareRouting\Core\Scheme\TableName;

class Account
{
    private Database $db;
    private string $tableName = TableName::USER;
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

        if (! $this->db->isConnectionActive()) {
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
     * Get current logged in user data
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

        return $this->getUserById($userId);
    }

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
        unset($data[ColumnName::ID], $data[ColumnName::PASSWORD], $data[ColumnName::RESET_TOKEN], $data[ColumnName::RESET_TOKEN_EXPIRES]);

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
        $sql = "SELECT " . ColumnName::ID . " FROM {$this->tableName} WHERE " . ColumnName::EMAIL . " = :email AND " . ColumnName::ID . " != :userId LIMIT 1";

        return $this->db->fetch($sql, [ColumnName::EMAIL => $email, 'userId' => $excludeUserId]) !== false;
    }

    private function userExists(int $userId): bool
    {
        return $this->db->exists($this->tableName, [ColumnName::ID => $userId]);
    }

    private function getUserByEmail(string $email): ?array
    {
        $user = $this->db->fetch("SELECT * FROM {$this->tableName} WHERE " . ColumnName::EMAIL . " = :email", [ColumnName::EMAIL => $email]);

        return $user ?: null;
    }

    private function getUserById(int $userId): ?array
    {
        $user = $this->db->fetch("SELECT * FROM {$this->tableName} WHERE " . ColumnName::ID . " = :id", [ColumnName::ID => $userId]);

        return $user ?: null;
    }

    private function getUserByResetToken(string $token): ?array
    {
        $sql = "SELECT * FROM {$this->tableName} WHERE " . ColumnName::RESET_TOKEN . " = :token AND " . ColumnName::RESET_TOKEN_EXPIRES . " > :now";
        $user = $this->db->fetch($sql, ['token' => $token, 'now' => date('Y-m-d H:i:s')]);

        return $user ?: null;
    }

    private function getUserByVerificationToken(string $token): ?array
    {
        $user = $this->db->fetch("SELECT * FROM {$this->tableName} WHERE " . ColumnName::EMAIL_VERIFICATION_TOKEN . " = :token", ['token' => $token]);

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
        $user = $this->db->fetch("SELECT * FROM {$this->tableName} WHERE " . ColumnName::REMEMBER_TOKEN . " = :token", ['token' => $hashedToken]);

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

    private function ensureUserTableExists(): void
    {
        $this->createUserTable();
    }

    private function createUserTable(): void
    {
        // Create users table using ORM-style pattern
        $scheme = new Scheme;
        $accountScheme = $scheme->account();
        // Create the table
        $this->db->createTableIfNotExists($accountScheme);
    }
}
