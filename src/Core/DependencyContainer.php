<?php

namespace SquareRouting\Core;

/**
 * Ein moderner, leichtgewichtiger Dependency Injection Container
 */
class DependencyContainer
{
    /**
     * Speichert registrierte Definitionen
     */
    private array $definitions = [];

    /**
     * Speichert bereits instanziierte Services (Singleton-Pattern)
     */
    private array $instances = [];

    /**
     * Registriert einen Service mit einer Factory-Funktion
     *
     * @param string $id Identifier des Services
     * @param callable $factory Factory-Funktion, die den Service erstellt
     * @param bool $singleton Ob der Service als Singleton behandelt werden soll
     * @return self
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
     * Registriert eine Klasse mit automatischer Constructor-Injection
     *
     * @param string $id Identifier des Services (optional, wenn nicht angegeben wird der Klassenname verwendet)
     * @param string $className Name der Klasse
     * @param array $parameters Zusätzliche Parameter für den Constructor
     * @param bool $singleton Ob der Service als Singleton behandelt werden soll
     * @return self
     */
    public function register(?string $id = null, ?string $className = null, array $parameters = [], bool $singleton = true): self
    {
        // Wenn nur ein Parameter übergeben wurde, ist es der Klassenname
        if ($className === null) {
            $className = $id;
            $id = $className;
        }

        return $this->set($id, function () use ($className, $parameters) {
            $reflectionClass = new \ReflectionClass($className);
            $constructor = $reflectionClass->getConstructor();

            if (!$constructor) {
                return $reflectionClass->newInstance();
            }

            $dependencies = [];
            foreach ($constructor->getParameters() as $param) {
                $paramName = $param->getName();

                // Wenn Parameter explizit übergeben wurde
                if (isset($parameters[$paramName])) {
                    $dependencies[] = $parameters[$paramName];
                    continue;
                }

                // Type-Hint auslesen für Autowiring
                $type = $param->getType();
                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    $dependencies[] = $this->get($type->getName());
                } elseif ($param->isDefaultValueAvailable()) {
                    $dependencies[] = $param->getDefaultValue();
                } elseif ($param->allowsNull()) {
                    $dependencies[] = null;
                } else {
                    throw new \Exception("Parameter '$paramName' in $className kann nicht injiziert werden");
                }
            }

            return $reflectionClass->newInstanceArgs($dependencies);
        }, $singleton);
    }

    /**
     * Holt einen Service aus dem Container
     *
     * @param string $id Identifier des Services
     * @return mixed
     * @throws \Exception Wenn der Service nicht gefunden wurde
     */
    public function get(string $id)
    {
        // Wenn der Service nicht registriert ist, versuchen wir, ihn automatisch zu registrieren
        if (!isset($this->definitions[$id])) {
            // Prüfen, ob die ID eine existierende Klasse ist
            if (class_exists($id)) {
                $this->register($id);
            } else {
                throw new \Exception("Service '$id' nicht gefunden");
            }
        }

        $def = $this->definitions[$id];

        // Bei Singleton: Prüfen, ob bereits instanziiert
        if ($def['singleton'] && isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Service erzeugen
        $instance = $def['factory']($this);

        // Bei Singleton: Instance speichern
        if ($def['singleton']) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    /**
     * Prüft, ob ein Service registriert ist
     *
     * @param string $id Identifier des Services
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->definitions[$id]) || class_exists($id);
    }

    /**
     * Entfernt einen Service aus dem Container
     *
     * @param string $id Identifier des Services
     * @return self
     */
    public function remove(string $id): self
    {
        unset($this->definitions[$id]);
        unset($this->instances[$id]);

        return $this;
    }
}
