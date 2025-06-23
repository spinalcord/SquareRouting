![](logo.jpg)
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
### Cors Protection
The `CorsMiddleware` class handles Cross-Origin Resource Sharing (CORS) in PHP applications, allowing you to control access to your resources. To use the middleware, open the `.env` file and add a comma-separated string of allowed origins (Or let it empty, to allow all hosts route calls):

```
ALLOWED_ORIGINS="https://my-domain.com, https://another-example.com"   
```
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
        $route->reroute('/reroute-test', '/redirect-to-google'); // Reroutes, which then redirects to Google.com
        $route->get('/redirect-to-google', ExampleController::class, 'redirectToGoogle');

        // GET Routes
        $route->get('/html', ExampleController::class, 'showHtmlPage');
        $route->get('/test/:myid', ExampleController::class, 'someTest', ['myid' => 'num']);
        $route->get('/rate-limit-example', ExampleController::class, 'rateLimiterExample');
        $route->get('/cache-example', ExampleController::class, 'cacheExample');
        $route->get('/dashboard/:location', ExampleController::class, 'dashboardExample', ['location' => 'path']);
        $route->get('/dotenv-example', ExampleController::class, 'envExample');
        $route->get('/pdo-read', ExampleController::class, 'pdoReadTableExample');
        $route->get('/pdo-create', ExampleController::class, 'pdoCreateTableExample');
        $route->get('/filtertest', ExampleController::class, 'filterTest')
              ->filter([ExampleFilter::class]); // Apply the ExampleFilter to this route

        // POST Routes
        $route->post('/post-example', ExampleController::class, 'handlePostRequest');
        $route->post('/validate-example', ExampleController::class, 'validateExample');

        return $route;
    }
}
```



### Controllers

Controllers handle the logic for your routes. They receive the `DependencyContainer` in their constructor.

```php
// app/Controllers/ExampleController.php
namespace SquareRouting\Controllers;

use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Response;

class ExampleController {

  public function __construct(DependencyContainer $container) {
  }

  public function someTest(int $mynum): Response {
    $data = [
        'status' => 'success',
        'received_number' => $mynum,
        'message' => 'This is a proper JSON response!'
    ];
    return (new Response)->json($data, 200);
  }
}


```

### Route Filters
This is idea comes from Lightpack MVC. Filters allow you to execute code before and after a route is processed. They are defined as classes with `before` and `after` methods.

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

After that a filter will executed before and after the specific get method.

```php
namespace SquareRouting\Controllers;

use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Response;

class ExampleController {

  public function __construct(DependencyContainer $container) {
  }

  public function filterTest(): Response {
    return (new Response)->html(" Filter Test ");
    // Output: some text before... Filter Test ...some text after.
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
}
```

### HTML Page Example

The `showHtmlPage` method demonstrates how to return a simple HTML response.

```php
// app/Controllers/ExampleController.php
class ExampleController {
    // ...
    public function showHtmlPage(): Response {
        $html = "<h1>Hello World!</h1><p>This is an HTML page.</p>
                 <form action=\"/post-example\" method=\"POST\">
                     <button type=\"submit\">Send POST Request</button>
                 </form>";
        return (new Response)->html($html);
    }
    // ...
}
```

### Redirection Example

The `redirectToGoogle` method shows how to perform a redirection to an external URL.

```php
// app/Controllers/ExampleController.php
class ExampleController {
    // ...
    public function redirectToGoogle(): Response {
        return (new Response)->reroute('https://www.google.com');
    }
    // ...
}
```

### Rate Limiting Example

The `rateLimiterExample` method demonstrates how to use the built-in `RateLimiter` to protect your endpoints from excessive requests.

```php
// app/Controllers/ExampleController.php
class ExampleController {
    public RateLimiter $rateLimiter;

    public function __construct(DependencyContainer $container) {
        $this->rateLimiter = $container->get(RateLimiter::class);
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
}
```

### .env Example

The `envExample` method demonstrates how to access environment variables defined in the `.env` file using the `DotEnv` class.

```php
// app/Controllers/ExampleController.php
class ExampleController {
    public DotEnv $dotEnv;

    public function __construct(DependencyContainer $container) {
        $this->dotEnv = $container->get(DotEnv::class);
    }

    public function envExample(): Response {
        $testValue = $this->dotEnv->get("TESTVALUE");
        return (new Response)->html("The .env value is: ".$testValue);
    }
}
```

### Validation Example

The `validateExample` method showcases the use of the `Validator` class for input validation, including required fields, minimum/maximum lengths, `in` rule, nested validation, array validation, and JSON validation.

```php
// app/Controllers/ExampleController.php
namespace SquareRouting\Controllers;

use SquareRouting\Core\Request;
use SquareRouting\Core\Response;
use SquareRouting\Core\Validation\Validator;
use SquareRouting\Core\Validation\Rules\Required;
use SquareRouting\Core\Validation\Rules\Email;
use SquareRouting\Core\Validation\Rules\Min;
use SquareRouting\Core\Validation\Rules\In;
use SquareRouting\Core\Validation\Rules\IsArray;
use SquareRouting\Core\Validation\Rules\Json;

class ExampleController {
    public Request $request;

    public function __construct(DependencyContainer $container) {
        $this->request = $container->get(Request::class);
    }

    public function validateExample(): Response {
        $data = $this->request->post(); 

        // The rules for the data
        $rules = [
            'username' => [new Required(), new Min(5)],
            'password' => [new Required(), new Min(8)],
            'status' => [new Required(), new In(['active', 'inactive', 'pending'])],

            // Nested validation using dot notation
            'contact.email' => [new Required(), new Email()],
            'contact.address.city' => [new Required()],

            // Array validation using the '*' wildcard
            'tags' => [new IsArray(), new Min(1)], // The 'tags' field itself must be an array with at least 1 item.
            'tags.*.id' => [new Required()], // Rule for each item in the 'tags' array
            'tags.*.name' => [new Required(), new Min(3)],

            // JSON validation
            'metadata_json' => [new Json()],
            'invalid_json' => [new Json()],
        ];

        // Create a validator instance and run it
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return (new Response)->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'data_used_for_validation' => $data
            ], 400);
        } else {
            return (new Response)->json([
                'status' => 'success',
                'message' => 'Validation passed',
                'validated_data' => $validator->validated(),
                'data_used_for_validation' => $data
            ], 200);
        }
    }
}
```

### Database Examples (PDO)

The `ExampleController` includes methods demonstrating basic database operations using PDO.

#### Create Table and Insert Data Example

The `pdoCreateTableExample` method shows how to create a `users` table (if it doesn't exist) and insert sample data.

```php
// app/Controllers/ExampleController.php
class ExampleController {
    private PDO $db;

    public function __construct(DependencyContainer $container) {
        $this->db = $container->get(DatabaseConnection::class)->getPdo();
    }

    public function pdoCreateTableExample(): Response {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            
            $this->db->exec($sql);
            
            $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM users");
            $checkStmt->execute();
            $userCount = $checkStmt->fetchColumn();
            
            if ($userCount == 0) {
                $insertSql = "INSERT INTO users (username, email, password_hash) VALUES
                            ('john_doe', 'john@example.com', :password1),
                            ('jane_smith', 'jane@example.com', :password2),
                            ('bob_wilson', 'bob@example.com', :password3)";
                
                $insertStmt = $this->db->prepare($insertSql);
                $insertStmt->execute([
                    ':password1' => password_hash('password123', PASSWORD_DEFAULT),
                    ':password2' => password_hash('secret456', PASSWORD_DEFAULT),
                    ':password3' => password_hash('mypass789', PASSWORD_DEFAULT)
                ]);
                
                $insertedRows = $insertStmt->rowCount();
                
                return (new Response)->json([
                    'status' => 'success',
                    'message' => 'Table created and sample data inserted',
                    'table_name' => 'users',
                    'inserted_rows' => $insertedRows
                ], 201);
            } else {
                return (new Response)->json([
                    'status' => 'success',
                    'message' => 'Table already exists with data',
                    'table_name' => 'users',
                    'existing_rows' => $userCount
                ], 200);
            }
            
        } catch (PDOException $e) {
            return (new Response)->json([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }
}
```

#### Read Table Example

The `pdoReadTableExample` method demonstrates how to read all records from a `users` table.

```php
// app/Controllers/ExampleController.php
class ExampleController {
    private PDO $db;

    public function __construct(DependencyContainer $container) {
        $this->db = $container->get(DatabaseConnection::class)->getPdo();
    }

    public function pdoReadTableExample(): Response {
        try {
            $stmt = $this->db->prepare("SELECT id, username, email, created_at FROM users ORDER BY created_at DESC");
            $stmt->execute();
            
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return (new Response)->json([
                'status' => 'success',
                'message' => 'Users retrieved successfully',
                'data' => $users,
                'count' => count($users)
            ], 200);
            
        } catch (PDOException $e) {
            return (new Response)->json([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
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
