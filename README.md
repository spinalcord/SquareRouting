# SquareRouting

A simple yet powerful PHP routing script designed for clarity and flexibility. This project provides a robust foundation for building web applications with clean URL structures, route parameters, and essential features like rate limiting and caching.

## Features

*   **Flexible Routing**: Define routes for GET, POST, and even REROUTE (redirection) methods.
*   **Path Parameters**: Supports dynamic URL segments with predefined patterns (e.g., `num`, `alpha`, `slug`) and a special `:path` parameter for capturing entire sub-paths, including slashes.
*   **Route Filters**: Apply 'before' and 'after' filters to routes for tasks like authentication, logging, or data manipulation. Filters are classes with `before` and `after` methods that receive the `DependencyContainer`.
*   **Dependency Injection**: Integrates with a `DependencyContainer` for managing and injecting dependencies into controllers and filters.
*   **Built-in Rate Limiting**: Protect your endpoints from abuse with an easy-to-use rate limiting mechanism that stores data in a JSON file.
*   **Simple Caching Mechanism**: Implement basic caching for frequently accessed data to improve performance.
*   **Response Handling**: Convenient methods for sending JSON, HTML, or performing redirects.

## Installation

This project uses Composer for dependency management.

1.  **Clone the repository**:
    ```bash
    git clone https://github.com/a7dev/SquareRouting.git
    cd SquareRouting
    ```
2.  **Install Composer dependencies**:
    ```bash
    composer install
    ```

## Usage

### Defining Routes

Routes are defined in `app/Routes/ApplicationRoutes.php`. You can add GET, POST, and REROUTE rules.

```php
// app/Routes/ApplicationRoutes.php
use SquareRouting\Controllers\ExampleController;
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Route;
use SquareRouting\Filters\ExampleFilter;

class ApplicationRoutes implements RoutableInterface
{
    public function getRoute(DependencyContainer $container) : Route
    {
        $route = new Route($container);

        // Add custom patterns
        $route->addPattern('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

        // Reroute Example
        $route->reroute('/reroute-test', '/google');
        
        // GET Route with a numeric parameter
        $route->get('/test/:myid', ExampleController::class, 'someTest', ['myid' => 'num']);
        
        // GET Route with a path parameter (can include slashes)
        $route->get('/dashboard/:location', ExampleController::class, 'dashboardExample', ['location' => 'path']);

        // GET Route with a filter
        $route->get('/filtertest', ExampleController::class, 'filterTest')
              ->filter([ExampleFilter::class]); // Apply the ExampleFilter to this route

        // POST Route
        $route->post('/post-example', ExampleController::class, 'handlePostRequest');

        return $route;
    }
}
```

### Route Filters

Filters allow you to execute code before and after a route is processed. They are defined as classes with `before` and `after` methods.

```php
// app/Filters/ExampleFilter.php
namespace SquareRouting\Filters;

use SquareRouting\Core\DependencyContainer;

class ExampleFilter
{
    public function before(DependencyContainer $container): void
    {
        echo "some text before...";
    }

    public function after(DependencyContainer $container): void
    {
        echo "...some text after.";
    }
}
```

To apply a filter to a route, use the `filter()` method:

```php
$route->get('/filtertest', ExampleController::class, 'filterTest')
      ->filter([ExampleFilter::class]); // Apply the ExampleFilter to this route
```

### Controllers

Controllers handle the logic for your routes. They receive the `DependencyContainer` in their constructor.

```php
// app/Controllers/ExampleController.php
namespace SquareRouting\Controllers;

use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Request;
use SquareRouting\Core\Response;
use SquareRouting\Core\RateLimiter;
use SquareRouting\Core\Cache;

class ExampleController {
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

  public function rateLimiterExample(): Response {
    $clientId = $_SERVER['REMOTE_ADDR'];
    $key = 'api_access';
    $this->rateLimiter->setLimit($key, 5, 60); // 5 attempts per 60 seconds

    if ($this->rateLimiter->isLimitExceeded($key, $clientId)) {
        $remainingTime = $this->rateLimiter->getRemainingTimeToReset($key, $clientId);
        return (new Response)->json(['status' => 'error', 'message' => 'Rate limit exceeded. Try again in ' . $remainingTime . ' seconds.'], 429);
    }
    $this->rateLimiter->registerAttempt($key, $clientId);
    $remainingAttempts = $this->rateLimiter->getRemainingAttempts($key, $clientId);
    return (new Response)->json(['status' => 'success', 'message' => 'API access granted.', 'remaining_attempts' => $remainingAttempts], 200);
  }
}
```

### Caching

The `Cache` class provides a simple mechanism for caching data. You can retrieve, store, delete, and clear cached items.

```php
// Example usage in a controller or service
use SquareRouting\Core\Cache;

class ExampleController {
    public Cache $cache;

    public function __construct(DependencyContainer $container) {
        $this->cache = $container->get(Cache::class);
    }

    public function cachedDataExample(): Response {
        $data = $this->cache->get(
            'my_data_prefix', // A prefix for your cache files
            'unique_key',     // A unique key for this specific data
            function() {
                // This callback function will be executed if the data is not in cache or has expired
                // Simulate fetching data from a database or external API
                sleep(2); // Simulate a delay
                return ['item1' => 'value1', 'item2' => 'value2', 'timestamp' => time()];
            },
            60 // TTL (Time-To-Live) in seconds for this specific cache entry
        );

        return (new Response)->json(['status' => 'success', 'data' => $data], 200);
    }

    public function clearCacheExample(): Response {
        // Clear all cache files with a specific prefix
        $this->cache->clear('my_data_prefix');
        return (new Response)->json(['status' => 'success', 'message' => 'Cache cleared for my_data_prefix'], 200);
    }
}
```

### Running the Application

You can use a PHP development server to run the application:

```bash
php -S localhost:8000 -t public
```

Then, open your browser and navigate to `http://localhost:8000`.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.