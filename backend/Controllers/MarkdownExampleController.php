<?php
namespace SquareRouting\Controllers;
use SquareRouting\Core\Response;
use SquareRouting\Core\MarkdownRenderer;

class MarkdownExampleController
{
    public function showMarkdownExample(): Response
    {
        $inhalt = "# Welcome to SquareRouting Framework

## Getting Started

This is a **powerful** and *flexible* PHP routing framework that makes building web applications simple and enjoyable.

### Key Features

- **Fast Routing**: Lightning-fast route matching and dispatching
- **Flexible Controllers**: Clean, organized controller structure
- **Markdown Rendering**: Built-in support for rendering Markdown content

### Quick Example

Here's how easy it is to define a route:

```php
echo \"hello world\";
```

### Why Choose SquareRouting?

1. **Simple Setup**: Get started in minutes
2. **Modern PHP**: Built with PHP 8+ features
3. **Extensible**: Easy to customize and extend
4. **Well Documented**: Comprehensive documentation and examples

> \"Simplicity is the ultimate sophistication.\" - Leonardo da Vinci

### Code Example

```php
class UserController
{
    public function index(): Response
    {
        return (new Response)->json([
            'users' => User::all()
        ]);
    }
}
```


---

*Built with ❤️*";

        $renderer = new MarkdownRenderer();
        $htmlContent = $renderer->render($inhalt);
        return (new Response)->html($htmlContent);
    }
}
