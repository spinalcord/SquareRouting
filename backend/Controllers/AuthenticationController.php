<?php
namespace SquareRouting\Controllers;

use SquareRouting\Core\View;
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Request;
use SquareRouting\Core\Response;
use SquareRouting\Core\RateLimiter;
use SquareRouting\Core\Account;

class AuthenticationController {
  public Request $request;
  public RateLimiter $rateLimiter;
  public View $view;
  public Account $account;

  public function __construct(DependencyContainer $container) {
   $this->rateLimiter = $container->get(RateLimiter::class);
   $this->request = $container->get(Request::class);
   $this->view = $container->get(View::class);
   $this->account = $container->get(Account::class);
  }

  public function accountExample(): Response {
      $messages = [];
      $isLoggedIn = false;
      $currentUser = null;

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
      $messages[] = "Remaining account operation attempts: " . $remainingAttempts;

      // 1. Attempt to register a new user
      $testEmail = 'test_user_' . uniqid() . '@example.com';
      $testPassword = 'Password123';
      try {
          $registered = $this->account->register($testEmail, $testPassword, ['username' => 'test']);
          if ($registered) {
              $messages[] = "User '{$testEmail}' registered successfully.";
          } else {
              $messages[] = "Failed to register user '{$testEmail}'.";
          }
      } catch (\InvalidArgumentException $e) {
          $messages[] = "Registration failed: " . $e->getMessage();
      } catch (\RuntimeException $e) {
          $messages[] = "Registration runtime error: " . $e->getMessage();
      }

      // 2. Attempt to log in the newly registered user
      try {
          $loggedIn = $this->account->login($testEmail, $testPassword);
          if ($loggedIn) {
              $messages[] = "User '{$testEmail}' logged in successfully.";
          } else {
              $messages[] = "Failed to log in user '{$testEmail}'.";
          }
      } catch (\InvalidArgumentException $e) {
          $messages[] = "Login failed: " . $e->getMessage();
      } catch (\RuntimeException $e) {
          $messages[] = "Login runtime error: " . $e->getMessage();
      }

      // 3. Check if user is logged in
      $isLoggedIn = $this->account->isLoggedIn();
      if ($isLoggedIn) {
          $messages[] = "User is currently logged in.";
          $currentUser = $this->account->getCurrentUser();
          if ($currentUser) {
              $messages[] = "Current user: " . ($currentUser['email'] ?? 'N/A');
          }
      } else {
          $messages[] = "User is not logged in.";
      }

      // 4. Attempt to log out
      if ($isLoggedIn) {
          try {
              $loggedOut = $this->account->logout();
              if ($loggedOut) {
                  $messages[] = "User logged out successfully.";
              } else {
                  $messages[] = "Failed to log out user.";
              }
          } catch (\Exception $e) {
              $messages[] = "Logout failed: " . $e->getMessage();
          }
      }

      // Re-check login status after logout
      $isLoggedInAfterLogout = $this->account->isLoggedIn();
      if (!$isLoggedInAfterLogout) {
          $messages[] = "User is confirmed logged out.";
      } else {
          $messages[] = "User is still logged in after logout attempt (unexpected).";
      }


      $data = [
          'pageTitle' => 'Account Example',
          'messages' => $messages,
          'isLoggedIn' => $isLoggedIn,
          'currentUser' => $currentUser,
      ];

      $this->view->setMultiple($data);
      $output = $this->view->render("account_example.tpl");
      return (new Response)->html($output);
  }
}