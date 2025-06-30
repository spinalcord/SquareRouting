<?php

namespace SquareRouting\Core;

use Exception;
use ReflectionClass;
use ReflectionNamedType;

/**
 * A modern, lightweight Dependency Injection Container
 */
class DependencyContainer
{
    /**
     * Stores registered definitions
     */
    private array $definitions = [];

    /**
     * Stores already instantiated services (Singleton pattern)
     */
    private array $instances = [];

    /**
     * Registers a service with a factory function
     *
     * @param  string  $id  Service identifier
     * @param  callable  $factory  Factory function that creates the service
     * @param  bool  $singleton  Whether the service should be treated as a singleton
     */
    public function set(string $id, callable $factory, bool $singleton = true): self
    {
        $this->definitions[$id] = [
            'factory' => $factory,
            'singleton' => $singleton,
        ];

        return $this;
    }

    /**
     * Registers a class with automatic Constructor Injection
     *
     * @param  string  $id  Service identifier (optional, if not provided, the class name is used)
     * @param  string  $className  Class name
     * @param  array  $parameters  Additional parameters for the constructor
     * @param  bool  $singleton  Whether the service should be treated as a singleton
     */
    public function register(?string $id = null, ?string $className = null, array $parameters = [], bool $singleton = true): self
    {
        // If only one parameter was passed, it is the class name
        if ($className === null) {
            $className = $id;
            $id = $className;
        }

        return $this->set($id, function () use ($className, $parameters) {
            $reflectionClass = new ReflectionClass($className);
            $constructor = $reflectionClass->getConstructor();

            if (! $constructor) {
                return $reflectionClass->newInstance();
            }

            $dependencies = [];
            foreach ($constructor->getParameters() as $param) {
                $paramName = $param->getName();

                // If parameter was explicitly passed
                if (isset($parameters[$paramName])) {
                    $dependencies[] = $parameters[$paramName];

                    continue;
                }

                // Read type hint for autowiring
                $type = $param->getType();
                if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                    $dependencies[] = $this->get($type->getName());
                } elseif ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                } elseif ($param->allowsNull()) {
                    $dependencies[] = null;
                } else {
                    throw new Exception("Missing parameter {$paramName}. Make sure your register {$className} with {$paramName}.");
                }
            }

            return $reflectionClass->newInstanceArgs($dependencies);
        }, $singleton);
    }

    /**
     * Retrieves a service from the container
     *
     * @param  string  $id  Identifier des Services
     * @return mixed
     *
     * @throws Exception If the service is not found
     */
    public function get(string $id)
    {
        // If the service is not registered, try to register it automatically
        if (! isset($this->definitions[$id])) {
            // Check if the ID is an existing class
            if (class_exists($id)) {
                $this->register($id);
            } else {
                throw new Exception("Service '{$id}' not found");
            }
        }

        $def = $this->definitions[$id];

        // For singleton: Check if already instantiated
        if ($def['singleton'] && isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Create service
        $instance = $def['factory']($this);

        // For singleton: Store instance
        if ($def['singleton']) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    /**
     * Checks if a service is registered
     *
     * @param  string  $id  Identifier des Services
     */
    public function has(string $id): bool
    {
        return isset($this->definitions[$id]) || class_exists($id);
    }

    /**
     * Removes a service from the container
     *
     * @param  string  $id  Identifier des Services
     */
    public function remove(string $id): self
    {
        unset($this->definitions[$id]);
        unset($this->instances[$id]);

        return $this;
    }
}
