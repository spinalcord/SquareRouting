<?php

namespace SquareRouting\Controllers;

use Exception;
use InvalidArgumentException;
use RuntimeException;
use SquareRouting\Core\Account;
use SquareRouting\Core\Database;
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Language;
use SquareRouting\Core\RateLimiter;
use SquareRouting\Core\Request;
use SquareRouting\Core\Response;
use SquareRouting\Core\View;

class AuthenticationController
{
    private Request $request;
    private RateLimiter $rateLimiter;
    private View $view;
    private Account $account;
    private Language $language;
    private Database $db;

    public function __construct(DependencyContainer $container)
    {
        $this->rateLimiter = $container->get(RateLimiter::class);
        $this->request = $container->get(Request::class);
        $this->view = $container->get(View::class);
        $this->account = $container->get(Account::class);
        $this->language = $container->get(Language::class);
        $this->db = $container->get(Database::class);
    }

    public function accountExample(): Response
    {
        $messages = [];
        $isLoggedIn = false;
        $currentUser = null;
        $permissionResults = [];

        $clientId = $this->request->getClientIp(); // Get client IP address
        $key = 'account_operations'; // Define a key for account operations

        // Set rate limit: 5 attempts per 60 seconds for account operations
        $this->rateLimiter->setLimit($key, 5, 60);

        if ($this->rateLimiter->isLimitExceeded($key, $clientId)) {
            $remainingTime = $this->rateLimiter->getRemainingTimeToReset($key, $clientId);

            return (new Response)->json(['status' => 'error', 'message' => 'Rate limit exceeded for account operations. Try again in ' . $remainingTime . ' seconds.'], 429);
        }

        $this->rateLimiter->registerAttempt($key, $clientId);
        $remainingAttempts = $this->rateLimiter->getRemainingAttempts($key, $clientId);
        $messages[] = 'Remaining account operation attempts: ' . $remainingAttempts;

        // 0. Initialize default roles and permissions if not already done
        try {
            if (!$this->account->areDefaultRolesInitialized()) {
                $this->account->initializeDefaultRoles(true);
                $messages[] = 'Default roles and permissions initialized successfully.';
            } else {
                $messages[] = 'Default roles and permissions are already initialized.';
            }
        } catch (RuntimeException $e) {
            $messages[] = 'Failed to initialize roles: ' . $e->getMessage();
        }

        // 1. Attempt to register a new user
        $testEmail = 'test_user_' . uniqid() . '@example.com';
        $testPassword = 'Password123';
        $registeredUserId = null;
        
        try {
            $registered = $this->account->register($testEmail, $testPassword, ['username' => 'test' . uniqid()]);
            if ($registered) {
                $messages[] = "User '{$testEmail}' registered successfully.";
            } else {
                $messages[] = "Failed to register user '{$testEmail}'.";
            }
        } catch (InvalidArgumentException $e) {
            $messages[] = 'Registration failed: ' . $e->getMessage();
        } catch (RuntimeException $e) {
            $messages[] = 'Registration runtime error: ' . $e->getMessage();
        }

        // 2. Attempt to log in the newly registered user
        try {
            $loggedIn = $this->account->login($testEmail, $testPassword);
            if ($loggedIn) {
                $messages[] = "User '{$testEmail}' logged in successfully.";
            } else {
                $messages[] = "Failed to log in user '{$testEmail}'.";
            }
        } catch (InvalidArgumentException $e) {
            $messages[] = 'Login failed: ' . $e->getMessage();
        } catch (RuntimeException $e) {
            $messages[] = 'Login runtime error: ' . $e->getMessage();
        }

        // 3. Check if user is logged in and get current user info
        $isLoggedIn = $this->account->isLoggedIn();
        if ($isLoggedIn) {
            $messages[] = 'User is currently logged in.';
            $currentUser = $this->account->getCurrentUser();
            if ($currentUser) {
                $messages[] = 'Current user: ' . ($currentUser['email'] ?? 'N/A');
                $messages[] = 'Current user role: ' . ($currentUser['role_name'] ?? 'No role assigned');
                $registeredUserId = $currentUser['id'] ?? null;
            }
        } else {
            $messages[] = 'User is not logged in.';
        }

        // 4. Test Permission and Role System (if user is logged in)
        if ($isLoggedIn && $registeredUserId) {
            $messages[] = '=== TESTING PERMISSION & ROLE SYSTEM ===';
            
            // 4.1. Test default user permissions
            $messages[] = '--- Testing Default User Permissions ---';
            $permissionResults['default_user'] = $this->testUserPermissions();
            
            // 4.2. Test role assignment - assign moderator role
            try {
                $roleAssigned = $this->account->assignRole($registeredUserId, 'moderator');
                if ($roleAssigned) {
                    $messages[] = 'Successfully assigned moderator role to user.';
                    
                    // Test moderator permissions
                    $messages[] = '--- Testing Moderator Permissions ---';
                    $permissionResults['moderator'] = $this->testUserPermissions();
                } else {
                    $messages[] = 'Failed to assign moderator role to user.';
                }
            } catch (Exception $e) {
                $messages[] = 'Role assignment failed: ' . $e->getMessage();
            }
            
            // 4.3. Test admin role assignment
            try {
                $adminRoleAssigned = $this->account->assignRole($registeredUserId, 'admin');
                if ($adminRoleAssigned) {
                    $messages[] = 'Successfully assigned admin role to user.';
                    
                    // Test admin permissions
                    $messages[] = '--- Testing Admin Permissions ---';
                    $permissionResults['admin'] = $this->testUserPermissions();
                } else {
                    $messages[] = 'Failed to assign admin role to user.';
                }
            } catch (Exception $e) {
                $messages[] = 'Admin role assignment failed: ' . $e->getMessage();
            }

            // 4.4. Test permission requirements
            $messages[] = '--- Testing Permission Requirements ---';
            try {
                $this->account->requirePermission('users.create');
                $messages[] = '✓ User has required permission: users.create';
            } catch (RuntimeException $e) {
                $messages[] = '✗ Permission requirement failed: ' . $e->getMessage();
            }

            try {
                $this->account->requireRole('admin');
                $messages[] = '✓ User has required role: admin';
            } catch (RuntimeException $e) {
                $messages[] = '✗ Role requirement failed: ' . $e->getMessage();
            }

            try {
                $this->account->requireAdmin();
                $messages[] = '✓ User has admin privileges';
            } catch (RuntimeException $e) {
                $messages[] = '✗ Admin requirement failed: ' . $e->getMessage();
            }

            // 4.5. Test role and permission information
            $messages[] = '--- Role and Permission Information ---';
            $userRole = $this->account->getUserRole();
            if ($userRole) {
                $messages[] = "User's current role: {$userRole['name']} (Level: {$userRole['level']})";
            }

            $userPermissions = $this->account->getUserPermissions();
            $messages[] = 'User has ' . count($userPermissions) . ' permissions:';
            foreach ($userPermissions as $permission) {
                $messages[] = "  - {$permission['name']}: {$permission['description']}";
            }

            // 4.6. Test minimum role level
            $hasMinLevel1 = $this->account->hasMinimumRoleLevel(1); // Admin level
            $hasMinLevel2 = $this->account->hasMinimumRoleLevel(2); // Moderator level
            $hasMinLevel3 = $this->account->hasMinimumRoleLevel(3); // User level
            
            $messages[] = "Has minimum role level 1 (Admin): " . ($hasMinLevel1 ? 'Yes' : 'No');
            $messages[] = "Has minimum role level 2 (Moderator): " . ($hasMinLevel2 ? 'Yes' : 'No');
            $messages[] = "Has minimum role level 3 (User): " . ($hasMinLevel3 ? 'Yes' : 'No');

            // 4.7. Test role information methods
            $allRoles = $this->account->getAllRoles();
            $messages[] = 'Available roles in system: ' . implode(', ', array_column($allRoles, 'name'));

            $allPermissions = $this->account->getAllPermissions();
            $messages[] = 'Total permissions in system: ' . count($allPermissions);

            $permissionsByResource = $this->account->getPermissionsByResource();
            $messages[] = 'Permissions grouped by resource:';
            foreach ($permissionsByResource as $resource => $perms) {
                $messages[] = "  - {$resource}: " . count($perms) . ' permissions';
            }
        }

        // 5. Attempt to log out
        if ($isLoggedIn) {
            try {
                $loggedOut = $this->account->logout();
                if ($loggedOut) {
                    $messages[] = 'User logged out successfully.';
                } else {
                    $messages[] = 'Failed to log out user.';
                }
            } catch (Exception $e) {
                $messages[] = 'Logout failed: ' . $e->getMessage();
            }
        }

        // Re-check login status after logout
        $isLoggedInAfterLogout = $this->account->isLoggedIn();
        if (! $isLoggedInAfterLogout) {
            $messages[] = 'User is confirmed logged out.';
        } else {
            $messages[] = 'User is still logged in after logout attempt (unexpected).';
        }

        $data = [
            'pageTitle' => 'Account & Permission Example',
            'messages' => $messages,
            'isLoggedIn' => $isLoggedIn,
            'currentUser' => $currentUser,
            'permissionResults' => $permissionResults,
        ];

        $this->view->setMultiple($data);
        $output = $this->view->render('account_example.tpl');

        return (new Response)->html($output);
    }

    /**
     * Test various user permissions and roles
     */
    private function testUserPermissions(): array
    {
        $results = [];
        
        // Test specific permissions
        $permissionsToTest = [
            'users.create',
            'users.read', 
            'users.update',
            'users.delete',
            'content.create',
            'content.read',
            'content.update',
            'content.delete',
            'content.moderate',
            'settings.read',
            'settings.update',
            'profile.read',
            'profile.update'
        ];

        foreach ($permissionsToTest as $permission) {
            $results['permissions'][$permission] = $this->account->hasPermission($permission);
        }

        // Test roles
        $rolesToTest = ['admin', 'moderator', 'user'];
        foreach ($rolesToTest as $role) {
            $results['roles'][$role] = $this->account->hasRole($role);
        }

        // Test helper methods
        $results['is_admin'] = $this->account->isAdmin();
        $results['is_moderator'] = $this->account->isModerator();

        // Test deprecated 'can' method
        $results['can_users_create'] = $this->account->can('users', 'create');
        $results['can_content_moderate'] = $this->account->can('content', 'moderate');

        return $results;
    }

    /**
     * Admin-only example method to demonstrate permission requirements
     */
    public function adminOnlyAction(): Response
    {
        try {
            // This will throw an exception if user doesn't have admin role
            $this->account->requireAdmin();
            
            return (new Response)->json([
                'status' => 'success',
                'message' => 'Admin action executed successfully',
                'user' => $this->account->getCurrentUser()
            ]);
            
        } catch (RuntimeException $e) {
            return (new Response)->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 403);
        }
    }

    /**
     * Permission-specific example method
     */
    public function createUserAction(): Response
    {
        try {
            // This will throw an exception if user doesn't have 'users.create' permission
            $this->account->requirePermission('users.create');
            
            return (new Response)->json([
                'status' => 'success',
                'message' => 'User creation permission verified',
                'user' => $this->account->getCurrentUser()
            ]);
            
        } catch (RuntimeException $e) {
            return (new Response)->json([
                'status' => 'error', 
                'message' => $e->getMessage()
            ], 403);
        }
    }

    /**
     * Role management example
     */
    public function roleManagement(): Response
    {
        $messages = [];
        
        try {
            // Only allow admin users to access role management
            $this->account->requireAdmin();
            
            // Get all roles and permissions for display
            $allRoles = $this->account->getAllRoles();
            $allPermissions = $this->account->getAllPermissions();
            $permissionsByResource = $this->account->getPermissionsByResource();
            
            $data = [
                'pageTitle' => 'Role Management',
                'roles' => $allRoles,
                'permissions' => $allPermissions,
                'permissionsByResource' => $permissionsByResource,
                'currentUser' => $this->account->getCurrentUser()
            ];
            
            $this->view->setMultiple($data);
            $output = $this->view->render('role_management.tpl');
            
            return (new Response)->html($output);
            
        } catch (RuntimeException $e) {
            return (new Response)->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 403);
        }
    }
}