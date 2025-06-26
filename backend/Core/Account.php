<?php

declare(strict_types=1);

namespace SquareRouting\Core;

use RuntimeException;
use InvalidArgumentException;

class Account
{
    private Database $db;
    private string $tableName = 'users';
    private string $sessionKey = 'user_id';
    private int $passwordMinLength = 8;
    private int $maxLoginAttempts = 5;
    private int $lockoutDuration = 900; // 15 minutes in seconds

    public function __construct(DependencyContainer $container)
    {
        $this->db = $container->get(Database::class);
        $this->ensureUserTableExists();
    }

    /**
     * Register a new user account
     */
    public function register(string $email, string $password, array $additionalData = []): bool
    {
        $this->validateEmail($email);
        $this->validatePassword($password);

        if ($this->emailExists($email)) {
            throw new InvalidArgumentException('Email address already exists');
        }

        $userData = array_merge([
            'email' => strtolower(trim($email)),
            'password' => $this->hashPassword($password),
            'created_at' => date('Y-m-d H:i:s'),
            'email_verified' => 0,
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
        $this->validateEmail($email);
        
        if (empty($password)) {
            throw new InvalidArgumentException('Password cannot be empty');
        }

        $email = strtolower(trim($email));

        // Check for rate limiting
        if ($this->isAccountLocked($email)) {
            throw new RuntimeException('Account temporarily locked due to too many failed login attempts');
        }

        $user = $this->getUserByEmail($email);
        
        if (!$user || !$this->verifyPassword($password, $user['password'])) {
            $this->recordFailedLogin($email);
            throw new InvalidArgumentException('Invalid email or password');
        }

        if ($user['status'] !== 'active') {
            throw new RuntimeException('Account is not active');
        }

        // Successful login
        $this->clearFailedLoginAttempts($email);
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
        if (isset($_COOKIE['remember_token'])) {
            return $this->validateRememberToken($_COOKIE['remember_token']);
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

        $this->validatePassword($newPassword);

        $result = $this->db->update($this->tableName, [
            'password' => $this->hashPassword($newPassword),
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $userId]);

        return $result > 0;
    }

    /**
     * Reset password with token
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        $this->validatePassword($newPassword);

        $user = $this->getUserByResetToken($token);
        if (!$user) {
            throw new InvalidArgumentException('Invalid or expired reset token');
        }

        $result = $this->db->update($this->tableName, [
            'password' => $this->hashPassword($newPassword),
            'reset_token' => null,
            'reset_token_expires' => null,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $user['id']]);

        return $result > 0;
    }

    /**
     * Generate password reset token
     */
    public function generateResetToken(string $email): string
    {
        $this->validateEmail($email);
        $email = strtolower(trim($email));

        $user = $this->getUserByEmail($email);
        if (!$user) {
            throw new InvalidArgumentException('Email address not found');
        }

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $this->db->update($this->tableName, [
            'reset_token' => $token,
            'reset_token_expires' => $expires,
            'updated_at' => date('Y-m-d H:i:s')
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
        unset($data['id'], $data['password'], $data['reset_token'], $data['reset_token_expires']);

        if (isset($data['email'])) {
            $this->validateEmail($data['email']);
            $data['email'] = strtolower(trim($data['email']));
            
            if ($this->emailExistsForOtherUser($data['email'], $userId)) {
                throw new InvalidArgumentException('Email address already exists');
            }
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

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
            'email_verified' => 1,
            'email_verification_token' => null,
            'updated_at' => date('Y-m-d H:i:s')
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
            'email_verification_token' => $token,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $userId]);

        return $token;
    }

    // Private helper methods

    private function validateEmail(string $email): void
    {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address');
        }
    }

    private function validatePassword(string $password): void
    {
        if (strlen($password) < $this->passwordMinLength) {
            throw new InvalidArgumentException("Password must be at least {$this->passwordMinLength} characters long");
        }

        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
            throw new InvalidArgumentException('Password must contain at least one lowercase letter, one uppercase letter, and one digit');
        }
    }

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
        $sql = "SELECT id FROM {$this->tableName} WHERE email = :email AND id != :user_id LIMIT 1";
        return $this->db->fetch($sql, ['email' => $email, 'user_id' => $excludeUserId]) !== false;
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
        $sql = "SELECT * FROM {$this->tableName} WHERE reset_token = :token AND reset_token_expires > :now";
        $user = $this->db->fetch($sql, ['token' => $token, 'now' => date('Y-m-d H:i:s')]);
        return $user ?: null;
    }

    private function getUserByVerificationToken(string $token): ?array
    {
        $user = $this->db->fetch("SELECT * FROM {$this->tableName} WHERE email_verification_token = :token", ['token' => $token]);
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
            'remember_token' => hash('sha256', $token),
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $userId]);

        setcookie('remember_token', $token, $expires, '/', '', true, true);
    }

    private function clearRememberToken(): void
    {
        if (isset($_COOKIE['remember_token'])) {
            $hashedToken = hash('sha256', $_COOKIE['remember_token']);
            $this->db->update($this->tableName, ['remember_token' => null], ['remember_token' => $hashedToken]);
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
    }

    private function validateRememberToken(string $token): bool
    {
        $hashedToken = hash('sha256', $token);
        $user = $this->db->fetch("SELECT * FROM {$this->tableName} WHERE remember_token = :token", ['token' => $hashedToken]);
        
        if ($user) {
            $this->startSession($user['id']);
            return true;
        }

        return false;
    }

    private function updateLastLogin(int $userId): void
    {
        $this->db->update($this->tableName, [
            'last_login' => date('Y-m-d H:i:s')
        ], ['id' => $userId]);
    }

    private function isAccountLocked(string $email): bool
    {
        $sql = "SELECT failed_login_attempts, last_failed_login FROM {$this->tableName} WHERE email = :email";
        $user = $this->db->fetch($sql, ['email' => $email]);

        if (!$user || $user['failed_login_attempts'] < $this->maxLoginAttempts) {
            return false;
        }

        $lastFailedLogin = strtotime($user['last_failed_login']);
        return (time() - $lastFailedLogin) < $this->lockoutDuration;
    }

    private function recordFailedLogin(string $email): void
    {
        $sql = "UPDATE {$this->tableName} SET 
                failed_login_attempts = COALESCE(failed_login_attempts, 0) + 1,
                last_failed_login = :now
                WHERE email = :email";
        
        $this->db->query($sql, ['email' => $email, 'now' => date('Y-m-d H:i:s')]);
    }

    private function clearFailedLoginAttempts(string $email): void
    {
        $this->db->update($this->tableName, [
            'failed_login_attempts' => 0,
            'last_failed_login' => null
        ], ['email' => $email]);
    }

    private function ensureUserTableExists(): void
    {
        if (!$this->db->tableExists($this->tableName)) {
            $this->createUserTable();
        }
    }

    private function createUserTable(): void
    {
        $driver = $this->db->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        
        $sql = match ($driver) {
            'mysql' => "CREATE TABLE {$this->tableName} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                first_name VARCHAR(100),
                last_name VARCHAR(100),
                status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
                email_verified TINYINT(1) DEFAULT 0,
                email_verification_token VARCHAR(64),
                reset_token VARCHAR(64),
                reset_token_expires DATETIME,
                remember_token VARCHAR(64),
                failed_login_attempts INT DEFAULT 0,
                last_failed_login DATETIME,
                last_login DATETIME,
                created_at DATETIME NOT NULL,
                updated_at DATETIME,
                INDEX idx_email (email),
                INDEX idx_reset_token (reset_token),
                INDEX idx_remember_token (remember_token)
            )",
            'sqlite' => "CREATE TABLE {$this->tableName} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                first_name TEXT,
                last_name TEXT,
                status TEXT DEFAULT 'active',
                email_verified INTEGER DEFAULT 0,
                email_verification_token TEXT,
                reset_token TEXT,
                reset_token_expires TEXT,
                remember_token TEXT,
                failed_login_attempts INTEGER DEFAULT 0,
                last_failed_login TEXT,
                last_login TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT
            )",
            default => throw new RuntimeException("Unsupported database driver: {$driver}")
        };

        $this->db->query($sql);
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

    public function setMaxLoginAttempts(int $attempts): self
    {
        $this->maxLoginAttempts = $attempts;
        return $this;
    }

    public function setLockoutDuration(int $seconds): self
    {
        $this->lockoutDuration = $seconds;
        return $this;
    }
}