![](logo.jpg)
# SquareRouting

SquareRouting (Approx 0.35 Mb without comments) is a powerful, fast, and flexible PHP MVC micro-framework designed to provide a robust foundation for building web applications. It emphasizes clean URL structures, efficient routing, and includes essential features like rate limiting, caching, and a built-in account system.

## Table of Contents
- [Features](#features)
- [Installation](#installation)
- [Usage](#usage)
    - [Core Concepts](#core-concepts)
        - [Defining Routes](#defining-routes)
        - [Route Collection](#route-collection)
        - [Controllers](#controllers)
        - [Route Filters (This idea is from Lightpack)](#route-filters)
    - [Built-in Features](#built-in-features)
        - [CORS Protection](#cors-protection)
        - [Caching](#caching)
        - [Rate Limiting](#rate-limiting)
        - [Environment Variables (.env)](#environment-variables-env)
        - [Input Validation](#input-validation)
        - [Database Operations](#database-operations)
        - [Account System](#account-system)
        - [ORM-like DB Table Generation](#orm-like-table-generation)
        - [Template Engine (Views)](#template-engine-views)
        - [Language Support](#language-support)
        - [Configuration System](#configuration-system)
        - [Markdown Renderer](#markdown-renderer)
    - [Response Handling](#response-handling)
        - [HTML Page Example](#html-page-example)
        - [Redirection Example](#redirection-example)
- [Running the Application](#running-the-application)
- [License](#license)

## Features
*   **Language Support**: Multi-language support via route segments (e.g. http://localhost:8000/en)
*   **Flexible Routing**: Define routes for GET, POST, PUT, DELETE, PATCH, and REROUTE (redirection) methods.
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
*   **Cached Markdown Rendere**: Built in markdown renderer. Up to 15x performance boost with my caching system.
*   **No `static` !**: You heard it right! No `static` is used in this project, which makes this project perfectly fine for testing.
## Installation

This project uses Composer for dependency management.

1.  **Clone the repository**:
    ```bash
    git clone https://github.com/a7dev/SquareRouting.git
    cd SquareRouting
    ```
2.  **Or with Composer**:
    ```bash
    composer create-project spinalcord/square-routing
    ```

## Usage

### Core Concepts

#### Defining Routes

Create your routes in `app/Routes/ApplicationRoutes.php`. Here's how to add different types of routes:

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

        // Add custom URL patterns
        $route->addPattern('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

        // Redirect old URLs to new ones
        $route->reroute('/old-path', '/new-path');

        // Basic routes with parameters
        $route->get('/hello/:name', ExampleController::class, 'sayHello', ['name' => 'alpha']);
        $route->get('/user/:id', ExampleController::class, 'getUser', ['id' => 'num']);
        
        // Routes with filters (middleware)
        $route->get('/admin', ExampleController::class, 'adminPanel')
              ->filter([ExampleFilter::class]);

        // Different HTTP methods
        $route->post('/users', ExampleController::class, 'createUser');
        $route->put('/users/:id', ExampleController::class, 'updateUser', ['id' => 'num']);
        $route->delete('/users/:id', ExampleController::class, 'deleteUser', ['id' => 'num']);

        return $route;
    }
}
```

#### Route Collection

Use RouteCollector to combine routes from different sources. This is useful for modular applications or plugin systems:

```php
// public/index.php
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\RouteCollector;
use SquareRouting\Routes\ApplicationRoutes;

$container = new DependencyContainer();
$collector = new RouteCollector($container);

// Add your main routes
$collector->add((new ApplicationRoutes())->getRoute($container));

// Start handling requests
$collector->dispatch();
```

#### Controllers

Controllers handle your application logic. They get the DependencyContainer automatically:

```php
// backend/Controllers/ExampleController.php
namespace SquareRouting\Controllers;

use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Response;

class ExampleController {

  public function __construct(DependencyContainer $container) {
      // Container is injected automatically
  }

  public function getUser(int $id): Response {
    $userData = [
        'id' => $id,
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ];
    
    return (new Response)->json($userData);
  }
}
```

#### Route Filters

Filters let you run code before and after your routes. Great for authentication or logging:

```php
// backend/Filters/AuthFilter.php
namespace SquareRouting\Filters;

use SquareRouting\Core\DependencyContainer;

class AuthFilter
{
    public function before(DependencyContainer $container): void
    {
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
    }

    public function after(DependencyContainer $container): void
    {
        // Log the request or clean up
        error_log("User accessed protected route");
    }
}
```

Apply filters to your routes:

```php
$route->get('/dashboard', ExampleController::class, 'dashboard')
      ->filter([AuthFilter::class]);
```

### Built-in Features

#### CORS Protection

Set allowed domains in your `.env` file:

```
ALLOWED_ORIGINS="https://my-domain.com, https://another-example.com"
```

Leave empty to allow all origins.

#### Caching

Cache expensive operations easily:

```php
class ExampleController {
    public Cache $cache;

    public function __construct(DependencyContainer $container) {
        $this->cache = $container->get(Cache::class);
    }

    public function expensiveOperation(): Response {
        // Try to get from cache, or calculate if not found
        $result = $this->cache->get('calculations', 'fibonacci_100', function() {
            // This only runs if not in cache
            return $this->calculateFibonacci(100);
        }, 300); // Cache for 5 minutes

        return (new Response)->json(['result' => $result]);
    }
}
```

#### Rate Limiting

Protect your API from abuse:

```php
class ApiController {
    public RateLimiter $rateLimiter;

    public function __construct(DependencyContainer $container) {
        $this->rateLimiter = $container->get(RateLimiter::class);
    }

    public function apiEndpoint(): Response {
        $clientIp = $_SERVER['REMOTE_ADDR'];
        
        // Allow 10 requests per minute
        $this->rateLimiter->setLimit('api_calls', 10, 60);

        if ($this->rateLimiter->isLimitExceeded('api_calls', $clientIp)) {
            return (new Response)->json(['error' => 'Too many requests'], 429);
        }

        $this->rateLimiter->registerAttempt('api_calls', $clientIp);
        
        return (new Response)->json(['data' => 'Your API response']);
    }
}
```

#### Environment Variables (.env)

Access your environment variables:

```php
class ExampleController {
    public DotEnv $env;

    public function __construct(DependencyContainer $container) {
        $this->env = $container->get(DotEnv::class);
    }

    public function showConfig(): Response {
        $apiKey = $this->env->get("API_KEY");
        $debug = $this->env->get("DEBUG_MODE", "false");
        
        return (new Response)->json([
            'api_configured' => !empty($apiKey),
            'debug_mode' => $debug === "true"
        ]);
    }
}
```

#### Input Validation

Validate user input with simple rules:

```php
use SquareRouting\Core\Validation\Validator;
use SquareRouting\Core\Validation\Rules\Required;
use SquareRouting\Core\Validation\Rules\Email;
use SquareRouting\Core\Validation\Rules\Min;

class UserController {
    public Request $request;

    public function __construct(DependencyContainer $container) {
        $this->request = $container->get(Request::class);
    }

    public function register(): Response {
        $data = $this->request->post();
        
        $rules = [
            'email' => [new Required(), new Email()],
            'password' => [new Required(), new Min(8)],
            'name' => [new Required()],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return (new Response)->json([
                'errors' => $validator->errors()
            ], 400);
        }

        // Process registration...
        return (new Response)->json(['success' => true]);
    }
}
```

#### Database Operations

Simple database operations. Ensures compatiblity with sqlite and mysql.

```php
class UserController {
    private Database $db;

    public function __construct(DependencyContainer $container) {
        $this->db = $container->get(Database::class);
    }

    public function createUser(): Response {
        // Insert a new user
        $userId = $this->db->insert('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => password_hash('secret', PASSWORD_DEFAULT)
        ]);

        // Get the user back
        $user = $this->db->select('users', ['id', 'name', 'email'], ['id' => $userId]);

        return (new Response)->json(['user' => $user[0]]);
    }

    public function updateUser(int $id): Response {
        // Update user data
        $updated = $this->db->update('users', 
            ['name' => 'Jane Doe'], 
            ['id' => $id]
        );

        return (new Response)->json(['updated' => $updated > 0]);
    }
}
```

#### Account System

Built-in user authentication (Needs a database connection, use sqlite in the `.env` if you don't have mysql ready):

```php
class AuthController {
    public Account $account;

    public function __construct(DependencyContainer $container) {
        $this->account = $container->get(Account::class);
    }

    public function register(): Response {
        $email = $_POST['email'];
        $password = $_POST['password'];

        try {
            $success = $this->account->register($email, $password, [
                'first_name' => $_POST['first_name']
            ]);

            if ($success) {
                return (new Response)->json(['message' => 'Account created']);
            }
        } catch (Exception $e) {
            return (new Response)->json(['error' => $e->getMessage()], 400);
        }
    }

    public function login(): Response {
        $email = $_POST['email'];
        $password = $_POST['password'];

        if ($this->account->login($email, $password)) {
            $user = $this->account->getCurrentUser();
            return (new Response)->json(['user' => $user]);
        }

        return (new Response)->json(['error' => 'Invalid credentials'], 401);
    }

    public function logout(): Response {
        $this->account->logout();
        return (new Response)->json(['message' => 'Logged out']);
    }
}
```

#### ORM-like Table Generation

Define your database schema with PHP objects, ensures compatiblity between Sqlite and Mysql:

```php
use SquareRouting\Core\Database\ColumnType;
use SquareRouting\Core\Database\ForeignKey;
use SquareRouting\Core\Database\ForeignKeyAction;
use SquareRouting\Core\Database\Table;

// Create a users table
$users = new Table('users');
$users->id = ColumnType::INT;
$users->email = ColumnType::VARCHAR;
$users->name = ColumnType::VARCHAR;

// Configure columns
$users->id->autoIncrement = true;
$users->email->length = 255;
$users->email->nullable = false;
$users->name->length = 100;

// Create posts table with foreign key
$posts = new Table('posts');
$posts->id = ColumnType::INT;
$posts->userId = ColumnType::INT;
$posts->title = ColumnType::VARCHAR;
$posts->content = ColumnType::TEXT;

$posts->id->autoIncrement = true;
$posts->title->length = 255;

// Link posts to users
$posts->userId->foreignKey = new ForeignKey($users, $users->id);
$posts->userId->foreignKey->onDelete = ForeignKeyAction::CASCADE;

// Generate SQL
echo $users->toSQL(); // Dynamically generates Sqlite or Mysql (depends on your .env) 
echo $posts->toSQL(DatabaseDialect::SQLITE); // Or explicit 
```

#### Template Engine (Views)

Render HTML templates with a Twig-like syntax:

```php
class PageController {
    public View $view;

    public function __construct(DependencyContainer $container) {
        $this->view = $container->get(View::class);
    }

    public function homepage(): Response {
        $this->view->setMultiple([
            'pageTitle' => 'Welcome',
            'userName' => 'John',
            'items' => ['News', 'Products', 'About'],
            'isLoggedIn' => true
        ]);

        $html = $this->view->render("homepage.tpl");
        return (new Response)->html($html);
    }
}
```

Template file (`backend/Templates/homepage.tpl`):

```html
<!DOCTYPE html>
<html>
<head>
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
</body>
</html>
```

#### Language Support

- Support multiple languages with automatic detection (doesn't affect the defined routepath!):

```
English: http://localhost:8000/en/products
German: http://localhost:8000/de/products
Default: http://localhost:8000/products
```

- Set default language in `.env`:
```
DEFAULT_LANGUAGE=en
```

- Or let the user decide what his language is:
```php
// Visit /de to set German as default for this user
$route->get('/:lang', LanguageController::class, 'setLanguage', ['lang' => 'langcode']);
```

then do `$this->language->setLanguage($lang, true);` in your controller. Done.

- Load the language string from a json like this:
    - As you cann see my language system supports nested definitions.
    - Also parameters are supported.

```json
{
    "user": {
        "profile": "My name %s, My age %d."
    }
}
```

and load it like this in php:
```php
$this->language->translate('user.profile', 'foobar', 8)
```



#### Configuration System

Manage super fast cached application settings in your database easily (Use sqlite in the `.env` if you don't have mysql ready):

```php
class SettingsController {
    private Configuration $config;

    public function __construct(DependencyContainer $container) {
        $this->config = $container->get(Configuration::class);
    }

    public function setup(): Response {
        // Register settings with defaults
        // be carefull if you change something later in this method, it won't work because the default are then written in the database, therefore use set() or reset().  
        $this->config->register("app.name", "My App", "App Name", "Application display name");
        $this->config->register("app.debug", false, "Debug Mode", "Enable debug logging");
        $this->config->register("mail.smtp.host", "localhost", "SMTP Host", "Email server hostname");

        // Change values
        // sets the config value also to "dirty", allows recaching
        $this->config->set("app.debug", true);
        $this->config->set("mail.smtp.host", "smtp.gmail.com");

        // Get values
        // As long as you use "get" the values will be cached to reduce database queries.
        $appName = $this->config->get("app.name");
        $debugMode = $this->config->get("app.debug");

        // Get entire sections
        $mailConfig = $this->config->getArray("mail.smtp");

        // saves automatically the config in the db 
        $this->config->save();

        return (new Response)->json([
            'app_name' => $appName,
            'debug' => $debugMode,
            'mail_config' => $mailConfig
        ]);
    }
}
```

### Response Handling

#### HTML Page Example

Return simple HTML pages:

```php
public function homepage(): Response {
    $html = "
        <h1>Welcome!</h1>
        <p>This is my homepage.</p>
        <a href='/about'>About Us</a>
    ";
    return (new Response)->html($html);
}
```

#### Redirection Example

Redirect users to other pages:

```php
public function oldPage(): Response {
    return (new Response)->redirect('/new-page');
}

public function loginRedirect(): Response {
    return (new Response)->redirect('https://accounts.google.com/login');
}
```

#### Markdown Rendering

Render Markdown content easily with built-in caching for performance.
If you want to use the cached performance load the MarkdownRenderer instance with the dependency container (recommended),
if you want to use the MarkdownRenderer for quick prototyping do `$mdr = new MarkdownRenderer();` but this instance has no caching,

```php
use SquareRouting\Core\MarkdownRenderer;
use SquareRouting\Core\Response;
use SquareRouting\Core\DependencyContainer;

class MarkdownExampleController
{
    public MarkdownRenderer $mdr;

    public function __construct(DependencyContainer $container)
    {
        $this->mdr = $container->get(MarkdownRenderer::class); // Use it like this for best performance
    }

    public function showMarkdownExample(): Response
    {
        $markdownContent = "# Simple Markdown Example";
        $htmlContent = $this->mdr->render($markdownContent);
        return (new Response)->html($htmlContent);
    }
}
```


## Run the Server

Start the built-in PHP server:

```bash
php -S localhost:8000 -t public
```

Then visit `http://localhost:8000` in your browser.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
