# SquareRouting

A simple yet powerful PHP routing engine designed for clarity and flexibility.

## Table of Contents

- [About](#about)
- [Features](#features)
- [Installation](#installation)
- [Usage](#usage)
  - [Defining Routes](#defining-routes)
  - [Route Parameters](#route-parameters)
  - [Custom Patterns](#custom-patterns)
  - [Rerouting](#rerouting)
  - [Request and Response Handling](#request-and-response-handling)
  - [Dependency Injection](#dependency-injection)
- [License](#license)
- [Contributing](#contributing)

## About

SquareRouting is a lightweight and efficient PHP routing solution that helps you define and manage application routes with ease. It provides a clear and intuitive API for handling HTTP requests, extracting parameters, and dispatching to the appropriate controllers and actions.

## Features

- **HTTP Method Support**: Define routes for `GET` and `POST` requests.
- **Route Parameters**: Easily capture dynamic segments from URLs.
- **Custom Patterns**: Extend route parameter matching with custom regular expressions (e.g., `uuid`, `slug`, `date`).
- **Rerouting**: Redirect requests from one path to another.
- **Dependency Injection Container**: Built-in lightweight DIC for managing dependencies.
- **Request & Response Objects**: Dedicated classes for handling incoming requests and crafting outgoing responses (JSON, HTML, Text, Redirects).
- **Route Collection**: Organize routes into separate classes and merge them for dispatching.
- **Error Handling**: Basic error handling for 404 Not Found and internal server errors.

## Installation

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/a7dev/SquareRouting.git
    cd SquareRouting
    ```

2.  **Install dependencies via Composer:**
    ```bash
    composer install
    ```

## Usage

### Defining Routes

Routes are defined in classes that implement the `RoutableInterface`, such as `src/Routes/ApplicationRoutes.php`.

```php
// src/Routes/ApplicationRoutes.php
<?php
declare(strict_types=1);

namespace SquareRouting\Routes;

use SquareRouting\Controllers\ExampleController;
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Interfaces\RoutableInterface;
use SquareRouting\Core\Route;

class ApplicationRoutes implements RoutableInterface
{
    public function getRoute(DependencyContainer $container) : Route
    {
        $route = new Route($container);

        // GET route
        $route->get('/google', ExampleController::class, 'showHtmlPage');

        // POST route
        // $route->post('/submit-form', MyController::class, 'processForm');

        return $route;
    }
}
```

### Route Parameters

Define dynamic segments in your routes using a colon (`:`) prefix. Built-in patterns like `num`, `alnum`, `alpha`, `any`, `slug`, `date`, `year`, `month`, `day`, `bool`, `hexcolor`, `all`, and `path` are available.

```php
// Example with a numeric parameter
$route->get('/test/:num', ExampleController::class, 'someTest', ['num' => 'num']);

// Example with a path parameter (can include slashes)
$route->get('/files/:path', FileController::class, 'serveFile', ['path' => 'path']);
```

### Custom Patterns

You can add your own custom regex patterns for route parameters:

```php
$route->addPattern('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
$route->get('/user/:id', UserController::class, 'profile', ['id' => 'uuid']);
```

### Rerouting

Redirect users from one URL to another:

```php
$route->reroute('/old-path', '/new-path');
$route->reroute('/reroute-test', '/google'); // Example from ApplicationRoutes.php
```

### Request and Response Handling

The `Request` and `Response` classes provide convenient methods for interacting with HTTP requests and responses.

**Request:**

```php
use SquareRouting\Core\Request;

class ExampleController
{
    public function someAction(Request $request)
    {
        $getParam = $request->get('paramName');
        $postParam = $request->post('paramName');
        $allParams = $request->all();
        $header = $request->header('User-Agent');
        $isPost = $request->isPost();
        $rawBody = $request->rawBody();
        $jsonData = $request->json('key');
        $clientIp = $request->getClientIp();
    }
}
```

**Response:**

```php
use SquareRouting\Core\Response;

class ExampleController
{
    public function showHtmlPage(): Response
    {
        return (new Response())->html('<h1>Hello from SquareRouting!</h1>');
    }

    public function sendJsonData(): Response
    {
        return (new Response())->json(['status' => 'success', 'data' => []], 200);
    }

    public function sendError(): Response
    {
        return (new Response())->error('Something went wrong', 500);
    }

    public function redirectToHome(): Response
    {
        return (new Response())->reroute('/home');
    }
}
```

### Dependency Injection

SquareRouting includes a simple Dependency Injection Container (`DependencyContainer`) for managing class dependencies.

```php
// Register a class (will be automatically instantiated with its dependencies)
$container->register(Request::class);
$container->register(Response::class);

// Retrieve an instance
$request = $container->get(Request::class);
$response = $container->get(Response::class);
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please feel free to open issues or submit pull requests.