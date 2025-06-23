<?php
namespace SquareRouting\Core;

class CorsMiddleware
{
    public static function handle(string $allowedOrigins)
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Den kommagetrennten String in ein Array umwandeln
        $allowedOriginsArray = array_map('trim', explode(',', $allowedOrigins));

        if (empty($allowedOriginsArray) || in_array($origin, $allowedOriginsArray)) {
            header("Access-Control-Allow-Origin: " . (empty($allowedOriginsArray) ? '*' : $origin));
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
