<?php

namespace SquareRouting\Core;

class CorsMiddleware
{
    public function handle(string $allowedOrigins)
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Prüfen ob allowedOrigins leer oder nur Whitespace ist
        if (empty(trim($allowedOrigins))) {
            // Wenn keine spezifischen Origins angegeben sind, erlaube alle
            header('Access-Control-Allow-Origin: *');
        } else {
            // Den kommagetrennten String in ein Array umwandeln und leere Einträge entfernen
            $allowedOriginsArray = array_filter(
                array_map('trim', explode(',', $allowedOrigins)),
                function ($item) {
                    return $item !== '';
                }
            );

            // URLs normalisieren (trailing slash entfernen)
            $allowedOriginsArray = array_map([$this, 'normalizeUrl'], $allowedOriginsArray);
            $normalizedOrigin = $this->normalizeUrl($origin);

            if (in_array($normalizedOrigin, $allowedOriginsArray)) {
                header('Access-Control-Allow-Origin: ' . $origin);
            }
            // Wenn Origin nicht erlaubt ist, wird kein Access-Control-Allow-Origin Header gesetzt
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

    /**
     * Normalisiert eine URL für CORS-Vergleiche
     * - Entfernt trailing slashes
     * - Konvertiert zu lowercase (für Domain-Teil)
     * - Behält den ursprünglichen Pfad bei
     */
    private function normalizeUrl(string $url): string
    {
        if (empty($url)) {
            return $url;
        }

        // Parse URL
        $parsed = parse_url($url);
        if (! $parsed) {
            return $url;
        }

        // Schema und Host zu lowercase
        $schema = isset($parsed['scheme']) ? strtolower($parsed['scheme']) : '';
        $host = isset($parsed['host']) ? strtolower($parsed['host']) : '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

        // Pfad normalisieren (trailing slash entfernen, außer bei Root)
        $path = $parsed['path'] ?? '';
        if ($path !== '/' && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }

        // URL wieder zusammenbauen (ohne Query und Fragment für Origins)
        $normalizedUrl = $schema . '://' . $host . $port . $path;

        return $normalizedUrl;
    }
}
