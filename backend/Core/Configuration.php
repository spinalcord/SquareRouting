<?php

declare(strict_types=1);

namespace SquareRouting\Core;

use Exception;
use InvalidArgumentException;
use SquareRouting\Core\Schema\ColumnName;
use SquareRouting\Core\Schema\TableName;

class Configuration
{
    private Database $database;
    private bool $autoSave;
    private array $configurations = [];
    private array $registeredConfigs = [];
    private bool $isDirty = false;

    // Configuration cache
    private array $arrayCache = [];
    private bool $cacheEnabled = true;

    public function __construct(Database $database, bool $autoSave = false)
    {
        $this->database = $database;
        $this->autoSave = $autoSave;
        $this->initializeTable();
        $this->loadConfigurations();
    }

    /**
     * Register a new configuration key with default value
     */
    public function register(string $key, mixed $defaultValue, ?string $label = null, ?string $description = null): self
    {
        $this->validateKey($key);
        $this->validateNamespaceConflicts($key);

        $type = $this->getValueType($defaultValue);
        $serializedDefault = $this->serializeValue($defaultValue);
        $serializedValue = $this->serializeValue($defaultValue);

        // Check if configuration already exists
        if ($this->database->exists(TableName::CONFIGURATION, [ColumnName::NAME => $key])) {
            // Update registration info but keep current value
            $this->database->update(TableName::CONFIGURATION, [
                ColumnName::DEFAULT_VALUE => $serializedDefault,
                ColumnName::LABEL => $label,
                ColumnName::DESCRIPTION => $description,
                ColumnName::TYPE => $type,
                ColumnName::UPDATED_AT => date('Y-m-d H:i:s'),
            ], [ColumnName::NAME => $key]);
        } else {
            // Insert new configuration
            $this->database->insert(TableName::CONFIGURATION, [
                ColumnName::NAME => $key,
                ColumnName::VALUE => $serializedValue,
                ColumnName::DEFAULT_VALUE => $serializedDefault,
                ColumnName::LABEL => $label,
                ColumnName::DESCRIPTION => $description,
                ColumnName::TYPE => $type,
                ColumnName::CREATED_AT => date('Y-m-d H:i:s'),
            ]);
        }

        // Update in-memory cache
        if (! isset($this->configurations[$key])) {
            $this->configurations[$key] = $defaultValue;
        }

        $this->registeredConfigs[$key] = [
            'defaultValue' => $defaultValue,
            'label' => $label,
            'description' => $description,
            'type' => $type,
        ];

        $this->clearArrayCache();
        $this->markDirty();

        if ($this->autoSave) {
            $this->save();
        }

        return $this;
    }

    /**
     * Get configuration value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);

        if (array_key_exists($key, $this->configurations)) {
            return $this->configurations[$key];
        }

        // Return registered default if available
        if (isset($this->registeredConfigs[$key])) {
            return $this->registeredConfigs[$key]['defaultValue'];
        }

        return $default;
    }

    /**
     * Set configuration value
     */
    public function set(string $key, mixed $value): self
    {
        $this->validateKey($key);

        // Check if key is registered
        if (! isset($this->registeredConfigs[$key])) {
            throw new InvalidArgumentException("Configuration key '{$key}' is not registered. Register it first with register() method.");
        }

        $this->configurations[$key] = $value;
        $this->clearArrayCache();
        $this->markDirty();

        if ($this->autoSave) {
            $this->save();
        }

        return $this;
    }

    /**
     * Get all configurations matching a namespace as nested array
     */
    public function getArray(string $namespace = ''): array
    {
        if ($this->cacheEnabled && isset($this->arrayCache[$namespace])) {
            return $this->arrayCache[$namespace];
        }

        $result = [];

        foreach ($this->configurations as $key => $value) {
            if ($namespace === '' || str_starts_with($key, $namespace . '.')) {
                if ($namespace === '') {
                    $this->setNestedValue($result, $key, $value);
                } else {
                    // Remove namespace prefix
                    $relativePath = substr($key, strlen($namespace) + 1);
                    $this->setNestedValue($result, $relativePath, $value);
                }
            } elseif ($key === $namespace) {
                // Direct match - return the value directly if it's not an object/array context
                return [$this->getLastSegment($namespace) => $value];
            }
        }

        // If no nested keys found but we have a direct match, handle it
        if (empty($result) && isset($this->configurations[$namespace])) {
            $result = $this->configurations[$namespace];
            if (! is_array($result)) {
                $result = [$this->getLastSegment($namespace) => $result];
            }
        }

        if ($this->cacheEnabled) {
            $this->arrayCache[$namespace] = $result;
        }

        return $result;
    }

    /**
     * Check if configuration key exists
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->configurations);
    }

    /**
     * Check if configuration key is registered
     */
    public function isRegistered(string $key): bool
    {
        return isset($this->registeredConfigs[$key]);
    }

    /**
     * Get all registered configuration keys
     */
    public function getRegisteredKeys(): array
    {
        return array_keys($this->registeredConfigs);
    }

    /**
     * Get registration info for a key
     */
    public function getRegistrationInfo(string $key): ?array
    {
        return $this->registeredConfigs[$key] ?? null;
    }

    /**
     * Save configurations to database
     */
    public function save(): self
    {
        if (! $this->isDirty) {
            return $this;
        }

        foreach ($this->configurations as $key => $value) {
            if (isset($this->registeredConfigs[$key])) {
                $serializedValue = $this->serializeValue($value);

                $this->database->update(TableName::CONFIGURATION, [
                    ColumnName::VALUE => $serializedValue,
                    ColumnName::UPDATED_AT => date('Y-m-d H:i:s'),
                ], [ColumnName::NAME => $key]);
            }
        }

        $this->markClean();

        return $this;
    }

    /**
     * Reset configuration to default value
     */
    public function reset(string $key): self
    {
        $this->validateKey($key);

        if (! isset($this->registeredConfigs[$key])) {
            throw new InvalidArgumentException("Configuration key '{$key}' is not registered.");
        }

        $this->configurations[$key] = $this->registeredConfigs[$key]['defaultValue'];
        $this->clearArrayCache();
        $this->markDirty();

        if ($this->autoSave) {
            $this->save();
        }

        return $this;
    }

    /**
     * Reset all configurations to default values
     */
    public function resetAll(): self
    {
        foreach ($this->registeredConfigs as $key => $config) {
            $this->configurations[$key] = $config['defaultValue'];
        }

        $this->clearArrayCache();
        $this->markDirty();

        if ($this->autoSave) {
            $this->save();
        }

        return $this;
    }

    /**
     * Remove configuration (unregister)
     */
    public function remove(string $key): self
    {
        $this->validateKey($key);

        // Remove from database
        $this->database->delete(TableName::CONFIGURATION, [ColumnName::NAME => $key]);

        // Remove from memory
        unset($this->configurations[$key]);
        unset($this->registeredConfigs[$key]);

        $this->clearArrayCache();

        return $this;
    }

    /**
     * Get all configurations as flat array
     */
    public function all(): array
    {
        return $this->configurations;
    }

    /**
     * Get all configurations with their registration info
     */
    public function getAllWithInfo(): array
    {
        $result = [];

        foreach ($this->registeredConfigs as $key => $info) {
            $result[$key] = [
                'value' => $this->configurations[$key] ?? $info['defaultValue'],
                'defaultValue' => $info['defaultValue'],
                'label' => $info['label'],
                'description' => $info['description'],
                'type' => $info['type'],
            ];
        }

        return $result;
    }

    /**
     * Enable/disable array caching
     */
    public function setCacheEnabled(bool $enabled): self
    {
        $this->cacheEnabled = $enabled;
        if (! $enabled) {
            $this->clearArrayCache();
        }

        return $this;
    }

    /**
     * Clear array cache
     */
    public function clearCache(): self
    {
        $this->clearArrayCache();

        return $this;
    }

    private function initializeTable(): void
    {
        $schema = new Schema;
        $table = $schema->configuration();
        $this->database->createTableIfNotExists($table);
    }

    /**
     * Load all configurations from database
     */
    private function loadConfigurations(): void
    {
        try {
            $configs = $this->database->select(TableName::CONFIGURATION);

            foreach ($configs as $config) {
                $value = $this->deserializeValue($config[ColumnName::VALUE], $config[ColumnName::TYPE]);
                $defaultValue = $this->deserializeValue($config[ColumnName::DEFAULT_VALUE], $config[ColumnName::TYPE]);

                $this->configurations[$config[ColumnName::NAME]] = $value;
                $this->registeredConfigs[$config[ColumnName::NAME]] = [
                    'defaultValue' => $defaultValue,
                    'label' => $config[ColumnName::LABEL],
                    'description' => $config[ColumnName::DESCRIPTION],
                    'type' => $config[ColumnName::TYPE],
                ];
            }
        } catch (Exception $e) {
            // If table doesn't exist or other error, start with empty configuration
            $this->configurations = [];
            $this->registeredConfigs = [];
        }
    }

    // Private helper methods

    private function validateNamespaceConflicts(string $key): void
    {
        // Check if the key would create a namespace conflict

        // 1. Check if any existing key uses this key as a namespace
        foreach (array_keys($this->configurations) as $existingKey) {
            if (str_starts_with($existingKey, $key . '.')) {
                throw new InvalidArgumentException(
                    "Cannot register configuration key '{$key}' as a direct value because it's already used as a namespace. " .
                    "Existing key '{$existingKey}' uses '{$key}' as a namespace."
                );
            }
        }

        // 2. Check if this key would use an existing direct value as a namespace
        $segments = explode('.', $key);
        $currentPath = '';

        foreach ($segments as $i => $segment) {
            if ($i > 0) {
                $currentPath .= '.';
            }
            $currentPath .= $segment;

            // Don't check the full path against itself
            if ($currentPath !== $key && isset($this->configurations[$currentPath])) {
                throw new InvalidArgumentException(
                    "Cannot register configuration key '{$key}' because it would use '{$currentPath}' as a namespace, " .
                    "but '{$currentPath}' is already registered as a direct value."
                );
            }
        }
    }

    private function validateKey(string $key): void
    {
        if (empty(trim($key))) {
            throw new InvalidArgumentException('Configuration key cannot be empty or whitespace only.');
        }

        // Check for invalid characters
        if (preg_match('/[^a-zA-Z0-9._-]/', $key)) {
            throw new InvalidArgumentException("Configuration key '{$key}' contains invalid characters. Only alphanumeric, dots, underscores and hyphens are allowed.");
        }

        // Check for consecutive dots
        if (str_contains($key, '..')) {
            throw new InvalidArgumentException("Configuration key '{$key}' cannot contain consecutive dots.");
        }

        // Check if starts or ends with dot
        if (str_starts_with($key, '.') || str_ends_with($key, '.')) {
            throw new InvalidArgumentException("Configuration key '{$key}' cannot start or end with a dot.");
        }
    }

    private function setNestedValue(array &$array, string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $current = &$array;

        foreach ($keys as $key) {
            if (! isset($current[$key]) || ! is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }

        $current = $value;
    }

    private function getLastSegment(string $path): string
    {
        $segments = explode('.', $path);

        return end($segments);
    }

    private function getValueType(mixed $value): string
    {
        return match (true) {
            is_null($value) => 'null',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_string($value) => 'string',
            is_array($value) => 'array',
            is_object($value) => 'object',
            default => 'unknown'
        };
    }

    private function serializeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return serialize($value);
    }

    private function deserializeValue(?string $serialized, string $type): mixed
    {
        if ($serialized === null) {
            return null;
        }

        return match ($type) {
            'null' => null,
            'boolean' => filter_var($serialized, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $serialized,
            'float' => (float) $serialized,
            'string' => $serialized,
            'array', 'object' => unserialize($serialized),
            default => $serialized
        };
    }

    private function clearArrayCache(): void
    {
        $this->arrayCache = [];
    }

    private function markDirty(): void
    {
        $this->isDirty = true;
    }

    private function markClean(): void
    {
        $this->isDirty = false;
    }
}
