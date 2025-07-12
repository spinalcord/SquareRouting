<?php

declare(strict_types=1);

namespace SquareRouting\Tests\Core;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use SquareRouting\Core\Account;
use SquareRouting\Core\Database;
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\RateLimiter;
use SquareRouting\Core\Schema\ColumnName;
use SquareRouting\Core\Schema\TableName;
use InvalidArgumentException;
use RuntimeException;

class AccountTest extends TestCase
{
    private Account $account;
    private MockObject|Database $databaseMock;
    private MockObject|RateLimiter $rateLimiterMock;
    private MockObject|DependencyContainer $containerMock;

    protected function setUp(): void
    {
        // Session-Handling für Tests
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Mock-Objekte erstellen
        $this->databaseMock = $this->createMock(Database::class);
        $this->rateLimiterMock = $this->createMock(RateLimiter::class);
        $this->containerMock = $this->createMock(DependencyContainer::class);

        // Container-Mock konfigurieren
        $this->containerMock
            ->method('get')
            ->willReturnMap([
                [Database::class, $this->databaseMock],
                [RateLimiter::class, $this->rateLimiterMock]
            ]);

        // Database als aktiv markieren
        $this->databaseMock
            ->method('isConnectionActive')
            ->willReturn(true);

        // Account-Instanz erstellen
        $this->account = new Account($this->containerMock);
    }

    protected function tearDown(): void
    {
        // Session nach jedem Test zurücksetzen
        $_SESSION = [];
        $_COOKIE = [];
    }

    // =================================================================
    // CONSTRUCTOR TESTS
    // =================================================================

    public function testConstructorThrowsExceptionWhenDatabaseNotActive(): void
    {
        // Neues Mock-Objekt erstellen, das false zurückgibt
        $inactiveDatabaseMock = $this->createMock(Database::class);
        $inactiveDatabaseMock
            ->method('isConnectionActive')
            ->willReturn(false);

        // Neuen Container-Mock erstellen mit der inaktiven Database
        $containerMock = $this->createMock(DependencyContainer::class);
        $containerMock
            ->method('get')
            ->willReturnMap([
                [Database::class, $inactiveDatabaseMock],
                [RateLimiter::class, $this->rateLimiterMock]
            ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Account feature needs a Database');

        new Account($containerMock);
    }

    // =================================================================
    // REGISTRATION TESTS
    // =================================================================

    public function testRegisterSuccess(): void
    {
        $email = 'test@example.com';
        $password = 'password123';
        $userRole = [ColumnName::ID => 3, ColumnName::NAME => 'user'];

        // Mock email nicht existiert
        $this->databaseMock
            ->expects($this->once())
            ->method('exists')
            ->with(TableName::ACCOUNT, [ColumnName::EMAIL => $email])
            ->willReturn(false);

        // Mock getUserRole für Standard-Rolle
        $this->databaseMock
            ->expects($this->once())
            ->method('fetch')
            ->willReturn($userRole);

        // Mock erfolgreiches Insert
        $this->databaseMock
            ->expects($this->once())
            ->method('insert')
            ->with(
                TableName::ACCOUNT,
                $this->callback(function ($data) use ($email) {
                    return $data[ColumnName::EMAIL] === $email &&
                           password_verify('password123', $data[ColumnName::PASSWORD]) &&
                           $data[ColumnName::ROLE_ID] === 3;
                })
            )
            ->willReturn('1'); // String statt int

        $result = $this->account->register($email, $password);

        $this->assertTrue($result);
    }

    public function testRegisterFailsWhenEmailExists(): void
    {
        $this->databaseMock
            ->method('exists')
            ->willReturn(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Email address already exists');

        $this->account->register('existing@example.com', 'password123');
    }

    public function testRegisterFailsWhenDefaultRoleNotFound(): void
    {
        $this->databaseMock
            ->method('exists')
            ->willReturn(false);

        $this->databaseMock
            ->method('fetch')
            ->willReturn(false); // Keine Rolle gefunden

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Default user role not found');

        $this->account->register('test@example.com', 'password123');
    }

    // =================================================================
    // LOGIN TESTS
    // =================================================================

    public function testLoginSuccess(): void
    {
        $email = 'test@example.com';
        $password = 'password123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $userData = [
            ColumnName::ID => 1,
            ColumnName::EMAIL => $email,
            ColumnName::PASSWORD => $hashedPassword,
            ColumnName::STATUS => 'active'
        ];

        // Rate Limiter nicht überschritten
        $this->rateLimiterMock
            ->method('isLimitExceeded')
            ->willReturn(false);

        // User gefunden
        $this->databaseMock
            ->method('fetch')
            ->willReturn($userData);

        // User existiert
        $this->databaseMock
            ->method('exists')
            ->willReturn(true);

        // Login erfolgreich
        $result = $this->account->login($email, $password);

        $this->assertTrue($result);
        $this->assertEquals(1, $_SESSION['userId']);
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $email = 'test@example.com';
        $userData = [
            ColumnName::ID => 1,
            ColumnName::EMAIL => $email,
            ColumnName::PASSWORD => password_hash('correctpassword', PASSWORD_DEFAULT),
            ColumnName::STATUS => 'active'
        ];

        $this->rateLimiterMock
            ->method('isLimitExceeded')
            ->willReturn(false);

        $this->databaseMock
            ->method('fetch')
            ->willReturn($userData);

        $this->rateLimiterMock
            ->expects($this->once())
            ->method('registerAttempt');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email or password');

        $this->account->login($email, 'wrongpassword');
    }

    public function testLoginFailsWhenRateLimited(): void
    {
        $this->rateLimiterMock
            ->method('isLimitExceeded')
            ->willReturn(true);

        $this->rateLimiterMock
            ->method('getRemainingTimeToReset')
            ->willReturn(300);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Too many failed login attempts');

        $this->account->login('test@example.com', 'password');
    }

    public function testLoginFailsWithEmptyPassword(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password cannot be empty');

        $this->account->login('test@example.com', '');
    }

    public function testLoginFailsWithInactiveAccount(): void
    {
        $userData = [
            ColumnName::ID => 1,
            ColumnName::EMAIL => 'test@example.com',
            ColumnName::PASSWORD => password_hash('password', PASSWORD_DEFAULT),
            ColumnName::STATUS => 'inactive'
        ];

        $this->rateLimiterMock
            ->method('isLimitExceeded')
            ->willReturn(false);

        $this->databaseMock
            ->method('fetch')
            ->willReturn($userData);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Account is not active');

        $this->account->login('test@example.com', 'password');
    }

    // =================================================================
    // SESSION TESTS
    // =================================================================

    public function testIsLoggedInWithValidSession(): void
    {
        $_SESSION['userId'] = 1;

        $this->databaseMock
            ->method('exists')
            ->willReturn(true);

        $this->assertTrue($this->account->isLoggedIn());
    }

    public function testIsLoggedInWithInvalidSession(): void
    {
        $_SESSION['userId'] = 999;

        $this->databaseMock
            ->method('exists')
            ->willReturn(false);

        $this->assertFalse($this->account->isLoggedIn());
    }

    public function testIsLoggedInWithRememberToken(): void
    {
        $_COOKIE['rememberToken'] = 'valid_token';
        
        $hashedToken = hash('sha256', 'valid_token');
        $userData = [ColumnName::ID => 1];

        $this->databaseMock
            ->method('fetch')
            ->with(
                $this->stringContains('remember_token'),
                ['token' => $hashedToken]
            )
            ->willReturn($userData);

        $this->assertTrue($this->account->isLoggedIn());
        $this->assertEquals(1, $_SESSION['userId']);
    }

    public function testLogout(): void
    {
        $_SESSION['userId'] = 1;
        $_COOKIE['rememberToken'] = 'some_token';

        $result = $this->account->logout();

        $this->assertTrue($result);
        $this->assertArrayNotHasKey('userId', $_SESSION);
    }

    // =================================================================
    // ROLE AND PERMISSION TESTS
    // =================================================================

    public function testHasPermissionWithValidPermission(): void
    {
        $_SESSION['userId'] = 1;

        $permissions = [
            [ColumnName::NAME => 'users.read'],
            [ColumnName::NAME => 'users.create']
        ];

        $this->databaseMock
            ->method('fetchAll')
            ->willReturn($permissions);

        $this->assertTrue($this->account->hasPermission('users.read'));
        $this->assertFalse($this->account->hasPermission('users.delete'));
    }

    public function testHasRoleWithValidRole(): void
    {
        $_SESSION['userId'] = 1;

        $roleData = [ColumnName::NAME => 'admin'];

        $this->databaseMock
            ->method('fetch')
            ->willReturn($roleData);

        $this->assertTrue($this->account->hasRole('admin'));
    }

    public function testIsAdmin(): void
    {
        $_SESSION['userId'] = 1;

        $roleData = [ColumnName::NAME => 'admin'];

        $this->databaseMock
            ->method('fetch')
            ->willReturn($roleData);

        $this->assertTrue($this->account->isAdmin());
    }

    public function testIsModerator(): void
    {
        $_SESSION['userId'] = 1;

        $roleData = [ColumnName::NAME => 'moderator'];

        $this->databaseMock
            ->method('fetch')
            ->willReturn($roleData);

        $this->assertTrue($this->account->isModerator());
    }

    public function testRequirePermissionThrowsExceptionWhenNotAuthorized(): void
    {
        $_SESSION['userId'] = 1;

        $this->databaseMock
            ->method('fetchAll')
            ->willReturn([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Access denied. Permission 'users.delete' required.");

        $this->account->requirePermission('users.delete');
    }

    public function testRequireRoleThrowsExceptionWhenNotAuthorized(): void
    {
        $_SESSION['userId'] = 1;

        $this->databaseMock
            ->method('fetch')
            ->willReturn([ColumnName::NAME => 'user']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Access denied. Role 'admin' required.");

        $this->account->requireRole('admin');
    }

    public function testAssignRole(): void
    {
        $userId = 1;
        $roleName = 'moderator';
        $roleData = [ColumnName::ID => 2, ColumnName::NAME => $roleName];

        // Mock getRoleByName
        $this->databaseMock
            ->expects($this->once())
            ->method('fetch')
            ->with(
                $this->stringContains('SELECT * FROM ' . TableName::ROLE),
                ['name' => $roleName]
            )
            ->willReturn($roleData);

        // Mock update
        $this->databaseMock
            ->expects($this->once())
            ->method('update')
            ->with(
                TableName::ACCOUNT,
                $this->callback(function ($data) {
                    return $data[ColumnName::ROLE_ID] === 2;
                }),
                [ColumnName::ID => $userId]
            )
            ->willReturn(1);

        $result = $this->account->assignRole($userId, $roleName);

        $this->assertTrue($result);
    }

    // =================================================================
    // PASSWORD TESTS
    // =================================================================

    public function testChangePasswordSuccess(): void
    {
        $_SESSION['userId'] = 1;
        $currentPassword = 'oldpassword';
        $newPassword = 'newpassword123';
        
        $userData = [
            ColumnName::ID => 1,
            ColumnName::PASSWORD => password_hash($currentPassword, PASSWORD_DEFAULT)
        ];

        $this->databaseMock
            ->method('fetch')
            ->willReturn($userData);

        $this->databaseMock
            ->expects($this->once())
            ->method('update')
            ->willReturn(1); // update() kann int zurückgeben (Anzahl betroffene Zeilen)

        $result = $this->account->changePassword($currentPassword, $newPassword);

        $this->assertTrue($result);
    }

    public function testChangePasswordFailsWithWrongCurrentPassword(): void
    {
        $_SESSION['userId'] = 1;
        
        $userData = [
            ColumnName::ID => 1,
            ColumnName::PASSWORD => password_hash('correctpassword', PASSWORD_DEFAULT)
        ];

        $this->databaseMock
            ->method('fetch')
            ->willReturn($userData);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Current password is incorrect');

        $this->account->changePassword('wrongpassword', 'newpassword');
    }

    // =================================================================
    // ROLE INITIALIZATION TESTS
    // =================================================================

    public function testInitializeDefaultRolesSuccess(): void
    {
        $this->databaseMock
            ->expects($this->once())
            ->method('beginTransaction');

        $this->databaseMock
            ->expects($this->once())
            ->method('commit');

        // Mock für getRoleByName (Rollen existieren nicht)
        $this->databaseMock
            ->method('fetch')
            ->willReturn(false);

        // Mock für Insert-Operationen
        $this->databaseMock
            ->method('insert')
            ->willReturn('1'); // String statt int

        $result = $this->account->initializeDefaultRoles();

        $this->assertTrue($result);
    }

    public function testInitializeDefaultRolesRollbackOnError(): void
    {
        $this->databaseMock
            ->expects($this->once())
            ->method('beginTransaction');

        $this->databaseMock
            ->expects($this->once())
            ->method('rollback');

        // Fehler simulieren
        $this->databaseMock
            ->method('insert')
            ->willThrowException(new \Exception('Database error'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to initialize default roles');

        $this->account->initializeDefaultRoles();
    }

    // =================================================================
    // UTILITY TESTS
    // =================================================================

    public function testParsePermissionName(): void
    {
        $result = $this->account->parsePermissionName('users.create');

        $expected = [
            'resource' => 'users',
            'action' => 'create',
            'full' => 'users.create'
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetCurrentUserWhenLoggedIn(): void
    {
        $_SESSION['userId'] = 1;

        $userData = [
            ColumnName::ID => 1,
            ColumnName::EMAIL => 'test@example.com',
            'role_name' => 'admin',
            'role_level' => 1
        ];

        $this->databaseMock
            ->method('exists')
            ->willReturn(true);

        $this->databaseMock
            ->method('fetch')
            ->willReturn($userData);

        $result = $this->account->getCurrentUser();

        $this->assertEquals($userData, $result);
    }

    public function testGetCurrentUserWhenNotLoggedIn(): void
    {
        $result = $this->account->getCurrentUser();

        $this->assertNull($result);
    }

    // =================================================================
    // CONFIGURATION TESTS
    // =================================================================

    public function testSetTableName(): void
    {
        $result = $this->account->setTableName('custom_users');

        $this->assertInstanceOf(Account::class, $result);
    }

    public function testSetSessionKey(): void
    {
        $result = $this->account->setSessionKey('customUserId');

        $this->assertInstanceOf(Account::class, $result);
    }

    public function testSetPasswordMinLength(): void
    {
        $result = $this->account->setPasswordMinLength(12);

        $this->assertInstanceOf(Account::class, $result);
    }

    public function testSetPermissionCacheTtl(): void
    {
        $result = $this->account->setPermissionCacheTtl(600);

        $this->assertInstanceOf(Account::class, $result);
    }

    // =================================================================
    // EDGE CASES AND ERROR HANDLING
    // =================================================================

    public function testEmailVerification(): void
    {
        $token = 'verification_token';
        $userData = [ColumnName::ID => 1];

        $this->databaseMock
            ->method('fetch')
            ->willReturn($userData);

        $this->databaseMock
            ->expects($this->once())
            ->method('update')
            ->willReturn(1);

        $result = $this->account->verifyEmail($token);

        $this->assertTrue($result);
    }

    public function testEmailVerificationWithInvalidToken(): void
    {
        $this->databaseMock
            ->method('fetch')
            ->willReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid verification token');

        $this->account->verifyEmail('invalid_token');
    }

    public function testGenerateResetToken(): void
    {
        $email = 'test@example.com';
        $userData = [ColumnName::ID => 1];

        $this->databaseMock
            ->method('fetch')
            ->willReturn($userData);

        $this->databaseMock
            ->expects($this->once())
            ->method('update');

        $token = $this->account->generateResetToken($email);

        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // bin2hex(32 bytes) = 64 chars
    }
}