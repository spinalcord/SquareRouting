<?php
namespace SquareRouting\Core;

class CorsMiddleware
{
    public static function handle(array $allowedOrigins)
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (empty($allowedOrigins) || in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: " . (empty($allowedOrigins) ? '*' : $origin));
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400'); // 24 H

        // Catch Preflight-Request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
}
