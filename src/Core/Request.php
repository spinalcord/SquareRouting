<?php
declare(strict_types=1);

namespace SquareRouting\Core;

class Request
{
    function __construct()
    {
    }

    // Holt alle GET-Parameter
    public function get(?string $key = null): mixed
    {
        if ($key) {
            return $_GET[$key] ?? null; // Use null coalescing operator.
        }
        return $_GET;
    }

    // Holt alle POST-Parameter
    public function post(?string $key = null): mixed
    {
        if ($key) {
            return $_POST[$key] ?? null; // Use null coalescing operator.
        }
        return $_POST;
    }

    // Holt alle Request-Daten (GET, POST, etc.)
    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    // Holt den Wert eines Request-Headers
    public function header(string $key): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $_SERVER[$key] ?? null; // Use null coalescing, and simplify the logic.
    }

    // Überprüft, ob ein bestimmter GET- oder POST-Parameter gesetzt ist
    public function has(string $key): bool
    {
        return isset($_GET[$key]) || isset($_POST[$key]);
    }

    // Holt den Request-Methodentyp (GET, POST, etc.)
    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    // Überprüft, ob der Request ein POST-Request ist
    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    // Überprüft, ob der Request ein GET-Request ist
    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    // Holt den gesamten Body des Requests (nützlich für PUT, PATCH, etc.)
    public function rawBody(): string
    {
        return file_get_contents('php://input');
    }

    // Holt JSON-Daten aus dem Request-Body
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
