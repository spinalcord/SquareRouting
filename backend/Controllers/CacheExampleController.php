<?php

namespace SquareRouting\Controllers;

use SquareRouting\Core\Cache;
use SquareRouting\Core\Response;
use SquareRouting\Core\DependencyContainer;

class CacheExampleController
{
    public Cache $cache;

    public function __construct(DependencyContainer $container)
    {
        $this->cache = $container->get(Cache::class);
    }

    public function cacheExample(): Response
    {
        $cacheKey = 'my_cached_data';
        $prefix = 'example_prefix';
        $ttl = 15; // Cache for 15 seconds

        $data = $this->cache->get($prefix, $cacheKey, function () {
            sleep(2); // Simulate a delay

            return [
                'message' => 'Data fetched from source at ' . date('Y-m-d H:i:s'),
                'generated_at' => time(), // Store Unix timestamp
            ];
        }, $ttl);

        // Helper logic to display the remaining time
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
            'remaining_seconds_until_expiry' => $remainingSeconds,
        ], 200);
    }
}