<?php

namespace SquareRouting\Core;

use Exception;

class Cache
{
    public $cacheDir;

    private $defaultTtl; // Time-to-live in seconds

    public function __construct(string $cacheDir, int $defaultTtl = 3600)
    {
        $this->cacheDir = rtrim($cacheDir, '/\\') . '/';
        $this->defaultTtl = $defaultTtl;
        try {
            $this->ensureCacheDirExists();
        } catch (Exception $e) {
        }
    }

    public function get(string $prefix, string $key, callable $callback, int $ttl = 0, array $callbackArgs = []): mixed
    {
        $cacheFile = $this->getCacheFilePath($prefix, $key);
        $ttl = $ttl ?? $this->defaultTtl;

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
            // Cache is valid, load
            return unserialize(file_get_contents($cacheFile));
        } else {
            // Cache is not valid, exec callback function to get the data again
            $data = call_user_func_array($callback, $callbackArgs);

            // Save data in cache
            $this->put($prefix, $key, $data);

            return $data;
        }
    }

    public function put(string $prefix, string $key, mixed $data): void
    {
        $cacheFile = $this->getCacheFilePath($prefix, $key);
        file_put_contents($cacheFile, serialize($data));
    }

    public function delete(string $prefix, string $key): bool
    {
        $cacheFile = $this->getCacheFilePath($prefix, $key);
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }

        return false;
    }

    public function forget(string $prefix, string $key): bool
    {
        return $this->delete($prefix, $key);
    }

    public function clear(string $prefix = ''): void
    {
        $files = glob($this->cacheDir . "cache_{$prefix}*");
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function ensureCacheDirExists(): void
    {
        if (! is_dir($this->cacheDir)) {
            if (! mkdir($this->cacheDir, 0777, true)) {
                throw new Exception("Cache directory could not be created: {$this->cacheDir}");
            }
        }
    }

    private function getCacheFilePath(string $prefix, string $key): string
    {
        $key = md5($key); // Save key

        return $this->cacheDir . 'cache_' . $prefix . '_' . $key . '.cache';
    }
}
