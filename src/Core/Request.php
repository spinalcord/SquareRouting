<?php
declare(strict_types=1);

namespace SquareRouting\Core;

class Request
{
    function __construct()
    {
    }

    // Retrieves all GET parameters
    public function get(?string $key = null): mixed
    {
        if ($key) {
            return $_GET[$key] ?? null; // Use null coalescing operator.
        }
        return $_GET;
    }

    // Retrieves all POST parameters
    public function post(?string $key = null): mixed
    {
        if ($key) {
            return $_POST[$key] ?? null; // Use null coalescing operator.
        }
        return $_POST;
    }

    // Retrieves all request data (GET, POST, etc.)
    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    // Retrieves the value of a request header
    public function header(string $key): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $_SERVER[$key] ?? null; // Use null coalescing, and simplify the logic.
    }

    // Checks if a specific GET or POST parameter is set
    public function has(string $key): bool
    {
        return isset($_GET[$key]) || isset($_POST[$key]);
    }

    // Retrieves the request method type (GET, POST, etc.)
    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    // Checks if the request is a POST request
    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    // Checks if the request is a GET request
    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    // Retrieves the entire body of the request (useful for PUT, PATCH, etc.)
    public function rawBody(): string
    {
        return file_get_contents('php://input');
    }

    // Retrieves JSON data from the request body
    public function json(?string $key = null): mixed
    {
        $rawBody = $this->rawBody();
        if ($rawBody) {
            $data = json_decode($rawBody, true);
            // Improved error handling.  Return null (as before) if there's an error.
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            if ($key) {
                return $data[$key] ?? null; // Null coalescing.
            }
            return $data;
        }

        return null;
    }

    public function getClientIp(): string
    {
        return $clientId = $_SERVER['REMOTE_ADDR'];
    }
}
