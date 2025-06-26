![](logo.jpg)
# SquareRouting

A powerful PHP MVC micro framework designed for clarity and flexibility. The best part: it's incredibly fast 🔥🔥. This project provides a robust foundation for building web applications with clean URL structures, route parameters, and essential features like rate limiting and caching and damn even a account system!

## Features

*   **Flexible Routing**: Define routes for GET, POST, and REROUTE (redirection) methods.
*   **Path Parameters**: Supports dynamic URL segments with predefined patterns (e.g., `num`, `alpha`, `slug`) and a special `:path` parameter for capturing entire sub-paths, including slashes.
*   **Route Filters**: Apply 'before' and 'after' filters to routes for tasks like authentication, logging, or data manipulation. Filters are classes with `before` and `after` methods that receive the `DependencyContainer`.
*   **Dependency Injection**: Integrates with a `DependencyContainer` for managing and injecting dependencies into controllers and filters.
*   **Built-in Rate Limiting**: Protect your endpoints from abuse with an easy-to-use rate limiting mechanism that stores data in a JSON file.
*   **Built-in Caching Mechanism**: Implement basic caching for frequently accessed data to improve performance.
*   **Flexible Response Handling**: Convenient methods for sending JSON, HTML, or performing redirects.
*   **CORS Protection**: Easily configure Cross-Origin Resource Sharing to control access to your resources.
*   **Input Validation**: Robust validation rules for various data types, including nested and array validation.
*   **Custom Database Class**: A handy way to modify your database.
*   **Template Engine**: Simple PHP-based template engine for rendering dynamic HTML views with built-in caching.

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
// backend/Routes/ApplicationRoutes.php
use SquareRouting\Controllers\ExampleController;
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Route;
use SquareRouting\Core\Interfaces\RoutableInterface;
use SquareRouting\Filters\ExampleFilter;

class ApplicationRoutes implements RoutableInterface
{
    public function getRoute(DependencyContainer $container) : Route
    {
        $route = new Route($container);

        // Add custom patterns
        $route->addPattern('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

        // Reroute Example
        $route->reroute('/old-path', '/new-path');

        // GET Routes
        $route->get('/hello/:name', ExampleController::class, 'sayHello', ['name' => 'alpha']);
        $route->get('/data/:id', ExampleController::class, 'getData', ['id' => 'num']);
        $route->get('/filtered-route', ExampleController::class, 'filteredMethod')
              ->filter([ExampleFilter::class]); // Apply a filter

        // POST Routes
        $route->post('/submit-form', ExampleController::class, 'processForm');

        return $route;
    }
}
```

### Route Collection

The `RouteCollector` class is responsible for gathering and merging all defined `Route` instances into a single, comprehensive route collection. This allows for modular route definitions across different files or modules, which are then combined for dispatching. This can be usefull if you implement a plugin system and allow your plugins to have there own routes.

```php
// public/index.php (Example usage)
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\RouteCollector;
use SquareRouting\Routes\ApplicationRoutes;

$container = new DependencyContainer();
$collector = new RouteCollector($container);

// Add your application routes
$collector->add((new ApplicationRoutes())->getRoute($container));

// Dispatch the collected routes
$collector->dispatch();
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
// backend/Controllers/ExampleController.php
namespace SquareRouting\Controllers;

use SquareRouting\Core\Response;

class ExampleController {
  // ...
  public function filteredMethod(): Response {
    return (new Response)->html("Content after filter.");
  }
  // ...
}
```


### Caching

The `Cache` class provides a simple mechanism for caching data. You can retrieve, store, delete, and clear cached items.

```php
// backend/Controllers/ExampleController.php
use SquareRouting\Core\Cache;
use SquareRouting\Core\Response;

class ExampleController {
    public Cache $cache;

    public function __construct(DependencyContainer $container) {
        $this->cache = $container->get(Cache::class);
    }

    public function cacheExample(): Response {
        $data = $this->cache->get('my_prefix', 'my_key', function() {
            // This callback runs if data is not in cache
            return ['message' => 'Data fetched from source.'];
        }, 60); // Cache for 60 seconds

        return (new Response)->json($data);
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
// backend/Controllers/ExampleController.php
use SquareRouting\Core\RateLimiter;
use SquareRouting\Core\Response;

class ExampleController {
    public RateLimiter $rateLimiter;

    public function __construct(DependencyContainer $container) {
        $this->rateLimiter = $container->get(RateLimiter::class);
    }

    public function rateLimiterExample(): Response {
        $clientId = $_SERVER['REMOTE_ADDR'];
        $key = 'api_access';
        $this->rateLimiter->setLimit($key, 5, 60); // 5 attempts per 60 seconds

        if ($this->rateLimiter->isLimitExceeded($key, $clientId)) {
            return (new Response)->json(['message' => 'Rate limit exceeded.'], 429);
        }

        $this->rateLimiter->registerAttempt($key, $clientId);
        return (new Response)->json(['message' => 'Access granted.']);
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
// backend/Controllers/ExampleController.php
namespace SquareRouting\Controllers;

use SquareRouting\Core\Request;
use SquareRouting\Core\Response;
use SquareRouting\Core\Validation\Validator;
use SquareRouting\Core\Validation\Rules\Required;
use SquareRouting\Core\Validation\Rules\Email;
use SquareRouting\Core\Validation\Rules\Min;

class ExampleController {
    public Request $request;

    public function __construct(DependencyContainer $container) {
        $this->request = $container->get(Request::class);
    }

    public function validateExample(): Response {
        $data = $this->request->post();
        $rules = [
            'email' => [new Required(), new Email()],
            'password' => [new Required(), new Min(8)],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return (new Response)->json(['status' => 'error', 'errors' => $validator->errors()], 400);
        } else {
            return (new Response)->json(['status' => 'success', 'message' => 'Validation passed.']);
        }
    }
}
```

### Database Examples (Using `Database` Class)

The `ExampleController` includes a concise `databaseExamples` method that demonstrates core database operations using the custom `Database` class, including creating tables, inserting, selecting, updating, deleting, and managing transactions.

```php
// backend/Controllers/ExampleController.php
namespace SquareRouting\Controllers;

use PDOException;
use SquareRouting\Core\Database; // Make sure to import the Database class
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Response;

class ExampleController
{
    private Database $db; // Use the custom Database class

    public function __construct(DependencyContainer $container)
    {
        $this->db = $container->get(Database::class); // Get the Database instance
    }

    public function databaseExamples(): Response
    {
        $results = [];

        try {
            // 1. Create Table Example
            $tableName = 'users';
            $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $this->db->query($sql);
            $results['create_table'] = ['status' => 'success', 'message' => "Table '{$tableName}' ensured to exist."];

            // 2. Insert Example
            $insertedId1 = $this->db->insert('users', [
                'username' => 'test_user_' . uniqid(),
                'email' => 'test_' . uniqid() . '@example.com',
                'password_hash' => password_hash('password123', PASSWORD_DEFAULT)
            ]);
            $insertedId2 = $this->db->insert('users', [
                'username' => 'another_user_' . uniqid(),
                'email' => 'another_' . uniqid() . '@example.com',
                'password_hash' => password_hash('securepass', PASSWORD_DEFAULT)
            ]);
            $results['insert'] = ['status' => 'success', 'message' => 'Users inserted.', 'ids' => [$insertedId1, $insertedId2]];

            // 3. Select All Example
            $allUsers = $this->db->select('users', ['id', 'username', 'email', 'created_at'], [], 'created_at DESC');
            $results['select_all'] = ['status' => 'success', 'message' => 'All users retrieved.', 'count' => count($allUsers)];

            // 4. Select with WHERE Example
            $specificUser = $this->db->select('users', ['id', 'username', 'email'], ['username' => $allUsers[0]['username'] ?? 'nonexistent'], '', 1);
            $results['select_where'] = ['status' => 'success', 'message' => 'Specific user retrieved.', 'data' => $specificUser];

            // 5. Update Example
            if (!empty($allUsers)) {
                $updatedRows = $this->db->update('users', ['email' => 'updated_' . uniqid() . '@example.com'], ['id' => $allUsers[0]['id']]);
                $results['update'] = ['status' => 'success', 'message' => "Updated {$updatedRows} row(s)."];
            } else {
                $results['update'] = ['status' => 'info', 'message' => 'Skipped update, no users to update.'];
            }

            // 6. Transaction Example
            $transactionResult = $this->db->transaction(function (Database $db) {
                $db->insert('users', [
                    'username' => 'transaction_user_' . uniqid(),
                    'email' => 'transaction_' . uniqid() . '@example.com',
                    'password_hash' => password_hash('txpass', PASSWORD_DEFAULT)
                ]);
                // throw new \Exception("Simulating transaction rollback"); // Uncomment to test rollback
                return "Transaction successful (or rolled back)";
            });
            $results['transaction'] = ['status' => 'success', 'message' => $transactionResult];

            // 7. Delete Example (clean up some data)
            $deletedRows = $this->db->delete('users', ['username' => 'transaction_user_' . uniqid()]); // This will likely delete nothing unless the transaction user was committed
            $results['delete'] = ['status' => 'success', 'message' => "Deleted {$deletedRows} row(s)."];

        } catch (PDOException $e) {
            $results['overall_error'] = ['status' => 'error', 'message' => 'Database operation failed: ' . $e->getMessage()];
        }

        return (new Response)->json($results, 200);
    }
}
```


### View (Template Engine) Example

The `View` class provides a simple yet powerful PHP-based (Twig-like) template engine for rendering dynamic HTML views. It supports variable interpolation, loops (foreach), conditional statements (if/else), and inclusion of partial templates. The engine also includes a built-in caching mechanism to improve performance by storing compiled templates. And the best part is: it is fast!

**Key Features:**
*   **Control Structures**: Use `{% control structure %}` for logic (e.g., `if`, `foreach`, `while`).
*   **Escaped Output**: Display dynamic data with HTML escaping using `{{ $variableName }}`.
*   **Raw Output**: Display dynamic data without HTML escaping using `{{ $variableName|raw }}`.
*   **Template Comments**: Add comments that are ignored during compilation using `{# comment #}`.
*   **Partial Includes**: Reuse template parts with `{% include "partial_template.tpl" %}`.
*   **Template Inheritance**: Extend layouts and define blocks using `{% extends "layout.tpl" %}` and `{% block blockName %}`.
*   **Caching**: Automatically caches compiled templates for faster rendering (Default: disabled).

**Usage Example:**

You can render a view by creating an instance of `View` (which can be injected via the `DependencyContainer`) and then using its `render` method. The output of the `render` method should then be returned as an HTML response.

```php
// backend/Controllers/ExampleController.php
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Response;
use SquareRouting\Core\View;

class ExampleController {
    public View $view;

    public function __construct(DependencyContainer $container) {
        $this->view = $container->get(View::class);
    }

    public function templateExample(): Response {
        $data = [
            'pageTitle' => 'My Page',
            'userName' => 'Guest',
            'items' => ['Item 1', 'Item 2', 'Item 3'],
            'isLoggedIn' => true,
        ];

        $this->view->setMultiple($data);
        $output = $this->view->render("demo.tpl"); // Renders backend/Templates/demo.tpl
        return (new Response)->html($output);
    }
}
```

**`backend/Templates/demo.tpl` content (simplified):**

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $pageTitle }}</title>
</head>
<body>
    <h1>Hello, {{ $userName }}!</h1>
    {% if $isLoggedIn %}
        <p>Welcome back!</p>
    {% else %}
        <p>Please log in.</p>
    {% endif %}
    <ul>
        {% foreach $items as $item %}
            <li>{{ $item }}</li>
        {% endforeach %}
    </ul>
    {% include "partial_info.tpl" %}
</body>
</html>
```


### Running the Application

You can use a PHP development server to run the application:

```bash
php -S localhost:8000 -t public
```

Then, open your browser and navigate to `http://localhost:8000`.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
