<?php
namespace SquareRouting\Controllers;

use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Request;
use SquareRouting\Core\Response; // Important for the Return Type Hint
use SquareRouting\Core\RateLimiter;
use SquareRouting\Core\Cache;

class ExampleController  {
  public Request $request;
  public RateLimiter $rateLimiter;
  public Cache $cache;

  public function __construct(DependencyContainer $container) {
   $this->rateLimiter = $container->get(RateLimiter::class);
   $this->cache = $container->get(Cache::class);
   $this->request = $container->get(Request::class);
  }

  public function someTest(int $mynum): Response {
    $data = [
        'status' => 'success',
        'received_number' => $mynum,
        'message' => 'This is a proper JSON response!'
    ];
    
    return (new Response)->json($data, 200);
  }
  
  public function showHtmlPage(): Response {
      $html = "<h1>Hello World!</h1><p>This is an HTML page.</p>
               <form action=\"/post-example\" method=\"POST\">
                   <button type=\"submit\">Send POST Request</button>
               </form>";
      return (new Response)->html($html);
  }

  public function redirectToGoogle(): Response {
      return (new Response)->reroute('https://www.google.com');
  }

  public function rateLimiterExample(): Response {
    $clientId = $_SERVER['REMOTE_ADDR']; // Get client IP address
    $key = 'api_access'; // Define a key for the rate limit

    $this->rateLimiter->setLimit($key, 5, 60); // 5 attempts per 60 seconds

    if ($this->rateLimiter->isLimitExceeded($key, $clientId)) {
        $remainingTime = $this->rateLimiter->getRemainingTimeToReset($key, $clientId);
        return (new Response)->json(['status' => 'error', 'message' => 'Rate limit exceeded. Try again in ' . $remainingTime . ' seconds.'], 429);
    }

    $this->rateLimiter->registerAttempt($key, $clientId);
    $remainingAttempts = $this->rateLimiter->getRemainingAttempts($key, $clientId);

    return (new Response)->json(['status' => 'success', 'message' => 'API access granted.', 'remaining_attempts' => $remainingAttempts], 200);
  }

  public function cacheExample(): Response {
    $cacheKey = 'my_cached_data';
    $prefix = 'example_prefix';
    $ttl = 15; // Cache for 15 seconds

    $data = $this->cache->get($prefix, $cacheKey, function() {
        sleep(2); // Simulate a delay
        return [
            'message' => 'Data fetched from source at ' . date('Y-m-d H:i:s'),
            'generated_at' => time() // Store Unix timestamp
        ];
    }, $ttl);

    $generatedAt = $data['generated_at'] ?? null;
    $expiresAt = null;
    $remainingSeconds = null;
    if ($generatedAt !== null) {
        $expiresAt = $generatedAt + $ttl;
        $remainingSeconds = $expiresAt - time();
        if ($remainingSeconds < 0) {
            $remainingSeconds = 0; // Cache has already expired
        }
    }

    return (new Response)->json([
        'status' => 'success',
        'data' => $data,
        'source' => 'cache',
        'remaining_seconds_until_expiry' => $remainingSeconds
    ], 200);
  }

  public function dashboardExample(?string $location): Response {
    // location for instance home, home/settings, etc
    $availableSections = [
        'home' => 'Welcome to the Dashboard! This is your home page.',
        'settings' => 'Dashboard Settings. Adjust your preferences here.',
        'profile' => 'User Profile. View and edit your profile information.'
    ];

    $htmlContent = "";

    switch ($location) {
        case 'home':
            $htmlContent = "<h1>Welcome to the Dashboard!</h1><p>{$availableSections['home']}</p>";
            break;
        case 'settings':
            $htmlContent = "<h1>Dashboard Settings</h1><p>{$availableSections['settings']}</p>";
            break;
        case 'profile':
            $htmlContent = "<h1>User Profile</h1><p>{$availableSections['profile']}</p>";
            break;
        default:
            $htmlContent = "<h1>Dashboard Overview</h1><p>Available sections:</p><ul>";
            foreach ($availableSections as $key => $value) {
                $htmlContent .= "<li><a href=\"/dashboard/{$key}\">" . ucfirst($key) . "</a></li>";
            }
            $htmlContent .= "</ul>";
            break;
    }

    return (new Response)->html($htmlContent);
  }

  public function handlePostRequest(): Response {
    $data = $this->request->post();
    return (new Response)->json(['status' => 'success', 'message' => 'POST request received!', 'data' => $data], 200);
  }
}

