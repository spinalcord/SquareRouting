<?php
namespace SquareRouting\Core\CLI;

class ArgumentInterpreter
{
    private array $arguments = [];
    private array $rawArgs = [];

    public function __construct(?array $args = null)
    {
        // Wenn keine Argumente übergeben werden, nimm die globalen $argv
        $this->rawArgs = $args ?? array_slice($GLOBALS['argv'] ?? [], 1);
        $this->parseArguments();
    }

    /**
     * Parst die Argumente aus dem Array
     */
    private function parseArguments(): void
    {
        $this->arguments = [];
        $i = 0;
        
        while ($i < count($this->rawArgs)) {
            $current = $this->rawArgs[$i];
            
            // Prüfen ob es ein Argument ist (beginnt mit --)
            if (strpos($current, '--') === 0) {
                $argName = substr($current, 2); // Entferne die ersten zwei Zeichen (--)
                
                // Edge Case: Leerer Argumentname (nur "--")
                if (empty($argName)) {
                    $i++;
                    continue;
                }
                
                // Schauen ob es einen Wert gibt
                if ($i + 1 < count($this->rawArgs) && strpos($this->rawArgs[$i + 1], '--') !== 0) {
                    $value = $this->rawArgs[$i + 1];
                    $this->arguments[$argName] = $this->convertValue($value);
                    $i += 2; // Überspringe den nächsten Wert
                } else {
                    // Flag ohne Wert (boolean true)
                    $this->arguments[$argName] = true;
                    $i++;
                }
            } else {
                // Ignoriere Werte ohne vorhergehende Flags
                $i++;
            }
        }
    }

    /**
     * Konvertiert String-Werte in passende Datentypen
     */
    private function convertValue(string $value): mixed
    {
        // Prüfe auf boolean
        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }
        
        // Prüfe auf null
        if (strtolower($value) === 'null') {
            return null;
        }
        
        // Prüfe auf Zahlen
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }
        
        // Standardmäßig als String zurückgeben
        return $value;
    }

    /**
     * Gibt einen spezifischen Argument-Wert zurück
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->arguments[$key] ?? $default;
    }

    /**
     * Prüft ob ein Argument existiert
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->arguments);
    }

    /**
     * Gibt alle Argumente als Array zurück
     */
    public function getAll(): array
    {
        return $this->arguments;
    }

    /**
     * Gibt alle Argumente als JSON zurück
     */
    public function toJson(): string
    {
        return json_encode($this->arguments, JSON_PRETTY_PRINT);
    }

    /**
     * Statische Methode zum direkten Parsen eines Arrays
     */
    public static function parse(array $args): self
    {
        return new self($args);
    }
}
