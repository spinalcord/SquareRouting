<?php

namespace SquareRouting\Core;

use RuntimeException;

class RateLimiter
{
    private $dataFile;
    private $limits = [];
    private $data = [];

    /**
     * Constructor
     *
     * @param  string  $dataFile  Path to the JSON file for data storage
     */
    public function __construct($dataFile = 'rate_limits.json')
    {
        $this->dataFile = $dataFile;

        // Automatische Verzeichniserstellung
        $this->ensureDirectoryExists();

        $this->loadData();
    }

    /**
     * Defines a limit for a specific key
     *
     * @param  string  $key  The key to monitor (e.g., 'captcha', 'login', etc.)
     * @param  int  $maxAttempts  Maximum number of attempts
     * @param  int  $timeWindow  Time window in seconds
     */
    public function setLimit($key, $maxAttempts, $timeWindow): void
    {
        $this->limits[$key] = [
            'maxAttempts' => $maxAttempts,
            'timeWindow' => $timeWindow,
        ];
    }

    /**
     * Checks if a client has exceeded the limit for a key
     *
     * @param  string  $key  The key to check
     * @param  string  $clientId  Unique client identifier (e.g., IP address, session ID)
     * @return bool True if the limit has been exceeded, otherwise False
     */
    public function isLimitExceeded($key, $clientId): bool
    {
        if (! isset($this->limits[$key])) {
            return false;
        }

        $limit = $this->limits[$key];
        $now = time();

        // Initialize the key if not already present
        if (! isset($this->data[$key])) {
            $this->data[$key] = [];
        }

        // Initialize the client if not already present
        if (! isset($this->data[$key][$clientId])) {
            $this->data[$key][$clientId] = [
                'attempts' => 0,
                'expires' => $now + $limit['timeWindow'],
            ];
        }

        // Check if the entry has expired and reset it
        if ($this->data[$key][$clientId]['expires'] < $now) {
            $this->data[$key][$clientId] = [
                'attempts' => 0,
                'expires' => $now + $limit['timeWindow'],
            ];
        }

        // Check if the limit has been exceeded
        return $this->data[$key][$clientId]['attempts'] >= $limit['maxAttempts'];
    }

    /**
     * Registers an attempt for a key and client
     *
     * @param  string  $key  The key (e.g., 'captcha', 'login', etc.)
     * @param  string  $clientId  Unique client identifier (e.g., IP address, session ID)
     * @return bool True if the limit has been exceeded, otherwise False
     */
    public function registerAttempt($key, $clientId): bool
    {
        if (! isset($this->limits[$key])) {
            return false;
        }

        $limit = $this->limits[$key];
        $now = time();

        // Initialize the key if not already present
        if (! isset($this->data[$key])) {
            $this->data[$key] = [];
        }

        // Initialize the client if not already present
        if (! isset($this->data[$key][$clientId])) {
            $this->data[$key][$clientId] = [
                'attempts' => 0,
                'expires' => $now + $limit['timeWindow'],
            ];
        }

        // Check if the entry has expired and reset it
        if ($this->data[$key][$clientId]['expires'] < $now) {
            $this->data[$key][$clientId] = [
                'attempts' => 1,
                'expires' => $now + $limit['timeWindow'],
            ];
        } else {
            // Increment the number of attempts
            $this->data[$key][$clientId]['attempts']++;
        }

        // Save the data
        $this->saveData();

        // Check if the limit has been exceeded
        return $this->data[$key][$clientId]['attempts'] >= $limit['maxAttempts'];
    }

    /**
     * Blocks a client for a specified time
     *
     * @param  string  $key  The key (e.g., 'captcha', 'login', etc.)
     * @param  string  $clientId  Unique client identifier (e.g., IP address, session ID)
     * @param  int  $blockTime  Block time in seconds
     */
    public function blockClient($key, $clientId, $blockTime): void
    {
        if (! isset($this->data[$key])) {
            $this->data[$key] = [];
        }

        $now = time();
        $this->data[$key][$clientId] = [
            'attempts' => PHP_INT_MAX, // High value to ensure isLimitExceeded returns true
            'expires' => $now + $blockTime,
        ];

        // Save the data
        $this->saveData();
    }

    /**
     * Unblocks a client
     *
     * @param  string  $key  The key (e.g., 'captcha', 'login', etc.)
     * @param  string  $clientId  Unique client identifier (e.g., IP address, session ID)
     */
    public function unblockClient($key, $clientId): void
    {
        if (isset($this->data[$key]) && isset($this->data[$key][$clientId])) {
            unset($this->data[$key][$clientId]);

            if (empty($this->data[$key])) {
                unset($this->data[$key]);
            }

            // Save the data
            $this->saveData();
        }
    }

    /**
     * Returns the remaining time until the limit resets
     *
     * @param  string  $key  The key (e.g., 'captcha', 'login', etc.)
     * @param  string  $clientId  Unique client identifier (e.g., IP address, session ID)
     * @return int Remaining time in seconds or 0 if no entry exists
     */
    public function getRemainingTimeToReset($key, $clientId): mixed
    {
        if (! isset($this->data[$key]) || ! isset($this->data[$key][$clientId])) {
            return 0;
        }

        $now = time();
        $expires = $this->data[$key][$clientId]['expires'];

        return max(0, $expires - $now);
    }

    /**
     * Returns the number of remaining attempts
     *
     * @param  string  $key  The key (e.g., 'captcha', 'login', etc.)
     * @param  string  $clientId  Unique client identifier (e.g., IP address, session ID)
     * @return int Number of remaining attempts or 0 if the limit has already been exceeded
     */
    public function getRemainingAttempts($key, $clientId): mixed
    {
        if (! isset($this->limits[$key])) {
            return PHP_INT_MAX;
        }

        if (! isset($this->data[$key]) || ! isset($this->data[$key][$clientId])) {
            return $this->limits[$key]['maxAttempts'];
        }

        $now = time();

        // Check if the entry has expired
        if ($this->data[$key][$clientId]['expires'] < $now) {
            return $this->limits[$key]['maxAttempts'];
        }

        return max(0, $this->limits[$key]['maxAttempts'] - $this->data[$key][$clientId]['attempts']);
    }

    /**
     * Stellt sicher, dass das Verzeichnis für die Datei existiert
     */
    private function ensureDirectoryExists(): void
    {
        $directory = dirname($this->dataFile);

        // Prüfen ob das Verzeichnis bereits existiert
        if (! is_dir($directory)) {
            // Verzeichnis rekursiv erstellen mit Berechtigung 0755
            if (! mkdir($directory, 0755, true)) {
                throw new RuntimeException('Konnte Verzeichnis nicht erstellen: ' . $directory);
            }
        }
    }

    /**
     * Loads the stored data from the JSON file
     */
    private function loadData(): void
    {
        if (file_exists($this->dataFile)) {
            $content = file_get_contents($this->dataFile);
            $this->data = json_decode($content, true) ?: [];

            // Clean up old entries
            $this->cleanupExpiredEntries();
        }
    }

    /**
     * Saves the current data to the JSON file
     */
    private function saveData(): void
    {
        file_put_contents($this->dataFile, json_encode($this->data));
    }

    /**
     * Cleans up expired entries
     */
    private function cleanupExpiredEntries(): void
    {
        $now = time();
        foreach ($this->data as $key => $entries) {
            foreach ($entries as $id => $entry) {
                if ($entry['expires'] < $now) {
                    unset($this->data[$key][$id]);
                }
            }

            if (empty($this->data[$key])) {
                unset($this->data[$key]);
            }
        }
    }
}
