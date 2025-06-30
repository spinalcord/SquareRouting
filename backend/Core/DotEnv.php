<?php

declare(strict_types=1);

namespace SquareRouting\Core; // Or your application's namespace

use InvalidArgumentException;
use RuntimeException;

/**
 * A modern, fast, and robust .env file reader and writer for PHP 8.3+.
 *
 * Lazily loads environment variables from a .env file and caches them.
 * Supports reading, setting, and saving variables back to the file
 * while preserving comments and formatting.
 */
final class DotEnv
{
    /**
     * In-memory cache for the parsed .env data.
     * Null until the file is loaded for the first time.
     *
     * @var array<string, string>|null
     */
    private ?array $data = null;

    /**
     * @param  string  $path  The absolute path to the .env file.
     */
    public function __construct(
        private readonly string $path
    ) {}

    /*
    //Static factory method for a more fluent interface.
    public static function create(string $path): self
    {
        return new self($path);
    }
    */

    /**
     * Retrieves an environment variable by its key.
     *
     * @param  string  $key  The variable key.
     * @param  mixed  $default  A default value to return if the key is not found.
     * @return string|mixed The variable value or the default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Lazily load the .env file on the first call.
        if ($this->data === null) {
            $this->load();
        }

        $value = $this->data[$key] ?? $default;
        
        // Convert string values to appropriate types
        return $this->convertType($value);
    }

    /**
     * Retrieves an environment variable as raw string (no type conversion).
     *
     * @param  string  $key  The variable key.
     * @param  mixed  $default  A default value to return if the key is not found.
     * @return string|mixed The variable value or the default.
     */
    public function getRaw(string $key, mixed $default = null): mixed
    {
        // Lazily load the .env file on the first call.
        if ($this->data === null) {
            $this->load();
        }

        return $this->data[$key] ?? $default;
    }

    /**
     * Sets an environment variable in the in-memory cache.
     * Note: This does not save to the file until save() is called.
     *
     * @param  string  $key  The variable key (must be alphanumeric with underscores).
     * @param  string|int|bool|null  $value  The value to set.
     */
    public function set(string $key, string|int|bool|null $value): void
    {
        if (! preg_match('/^[A-Z0-9_]+$/i', $key)) {
            throw new InvalidArgumentException(sprintf('The key "%s" is invalid. Only alphanumeric characters and underscores are allowed.', $key));
        }

        // Ensure data is loaded before setting to merge with existing values.
        if ($this->data === null) {
            $this->load();
        }

        $this->data[$key] = $this->formatValue($value);
    }

    /**
     * Writes the current set of environment variables back to the .env file.
     * This method intelligently updates existing keys, adds new ones,
     * and preserves all comments and blank lines.
     *
     * @throws RuntimeException If the file cannot be written to.
     */
    public function save(): void
    {
        if ($this->data === null) {
            // Nothing to save if no data has been loaded or set.
            return;
        }

        $currentLines = file_exists($this->path) ? file($this->path) : [];
        if ($currentLines === false) {
            throw new RuntimeException(sprintf('Could not read file for saving: %s', $this->path));
        }

        $keysToUpdate = $this->data;
        $newLines = [];

        // Iterate through existing lines to update them
        foreach ($currentLines as $line) {
            $line = rtrim($line, "\r\n");
            $trimmedLine = trim($line);

            if (empty($trimmedLine) || str_starts_with($trimmedLine, '#')) {
                $newLines[] = $line;

                continue;
            }

            // Extract key from 'KEY=VALUE'
            $parts = explode('=', $trimmedLine, 2);
            $key = $parts[0] ?? '';
            $key = trim(str_replace('export ', '', $key));

            if (array_key_exists($key, $keysToUpdate)) {
                // Key exists, update its line and remove it from the update list
                $newLines[] = "{$key}={$keysToUpdate[$key]}";
                unset($keysToUpdate[$key]);
            } else {
                // This key was not changed, keep the original line
                $newLines[] = $line;
            }
        }

        // Add any new keys that weren't in the original file
        if (! empty($keysToUpdate)) {
            // Add a blank line for separation if the file is not empty
            if (! empty($newLines) && trim(end($newLines)) !== '') {
                $newLines[] = '';
            }
            foreach ($keysToUpdate as $key => $value) {
                $newLines[] = "{$key}={$value}";
            }
        }

        $content = implode(PHP_EOL, $newLines);

        // Atomically write the file with an exclusive lock
        if (file_put_contents($this->path, $content, LOCK_EX) === false) {
            throw new RuntimeException(sprintf('Unable to save the .env file to %s.', $this->path));
        }
    }

    /**
     * Loads and parses the .env file into the data cache.
     *
     * @throws RuntimeException if the file is not found or not readable.
     */
    private function load(): void
    {
        $this->data = [];

        if (! is_file($this->path) || ! is_readable($this->path)) {
            // It's not an error if the file doesn't exist, it might be created later.
            // We just start with an empty data set.
            return;
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new RuntimeException(sprintf('Could not read .env file at: %s', $this->path));
        }

        foreach ($lines as $line) {
            // Ignore comments
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            // Split into key and value
            if (! str_contains($line, '=')) {
                continue; // Not a valid key-value pair
            }

            [$key, $value] = explode('=', $line, 2);

            // Clean up key (remove 'export' prefix and trim)
            $key = trim(str_replace('export ', '', $key));
            $value = trim($value);

            // Remove surrounding quotes (single or double) from the value
            if (
                strlen($value) > 1 &&
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $this->data[$key] = $value;
        }
    }

    /**
     * Converts string values to appropriate PHP types.
     */
    private function convertType(mixed $value): mixed
    {
        // If it's not a string, return as-is
        if (!is_string($value)) {
            return $value;
        }

        // Convert common string representations to proper types
        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $this->tryNumericConversion($value)
        };
    }

    /**
     * Attempts to convert numeric strings to integers or floats.
     */
    private function tryNumericConversion(string $value): string|int|float
    {
        // Check if it's a valid numeric string
        if (!is_numeric($value)) {
            return $value;
        }

        // Check if it's an integer
        if (ctype_digit($value) || (str_starts_with($value, '-') && ctype_digit(substr($value, 1)))) {
            $intValue = (int) $value;
            // Make sure we didn't lose precision
            if ((string) $intValue === $value) {
                return $intValue;
            }
        }

        // Try float conversion
        $floatValue = (float) $value;
        if (is_finite($floatValue)) {
            return $floatValue;
        }

        // If all else fails, return as string
        return $value;
    }

    /**
     * Formats a value for storage in the .env file.
     * Wraps strings with spaces in double quotes.
     */
    private function formatValue(string|int|bool|null $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        $stringValue = (string) $value;

        // Quote if it contains spaces, #, or =
        if (preg_match('/[\s#=]/', $stringValue)) {
            // Escape existing double quotes and wrap the whole thing
            return '"' . str_replace('"', '\"', $stringValue) . '"';
        }

        return $stringValue;
    }
}
