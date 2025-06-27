<?php

declare(strict_types=1);

namespace SquareRouting\Core;

use InvalidArgumentException;
use RuntimeException;
 // Or your application's namespace
/**
 * Language management class with string formatting support
 * 
 * Supports loading language files from a directory and provides
 * translation with sprintf-style string formatting.
 * 
 * -- MODIFIED TO USE JSON FILES --
 */
class Language
{
    private array $translations = [];
    private string $currentLanguage = 'en';
    private string $languageDirectory;
    private string $fallbackLanguage = 'en';
    
    /**
     * Constructor
     * 
     * @param string $languageDirectory Path to the directory containing language files
     * @param string $defaultLanguage Default language code (e.g., 'en', 'de')
     * @param string $fallbackLanguage Fallback language if translation is missing
     */
    public function __construct(
        string $languageDirectory, 
        string $defaultLanguage = 'en',
        string $fallbackLanguage = 'en'
    ) {
        $this->languageDirectory = rtrim($languageDirectory, '/\\');
        $this->currentLanguage = $defaultLanguage;
        $this->fallbackLanguage = $fallbackLanguage;
        
        $this->validateLanguageDirectory();
        $this->loadLanguage($this->currentLanguage);
    }
    
    /**
     * Set the current language
     * 
     * @param string $languageCode Language code (e.g., 'en', 'de', 'fr')
     * @param bool $useSession Whether to store the language in session
     * @return self
     * @throws InvalidArgumentException If language file doesn't exist
     */
    public function setLanguage(string $languageCode, bool $useSession = false): self
    {
        if (!$this->languageFileExists($languageCode)) {
            throw new InvalidArgumentException("Language file for '{$languageCode}' not found");
        }
        
        $this->currentLanguage = $languageCode;
        
        // Store in session if requested
        if ($useSession) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['language'] = $languageCode;
        }
        
        $this->loadLanguage($languageCode);

        return $this;
    }
    
    /**
     * Get the current language code
     * 
     * @return string
     */
    public function getCurrentLanguage(): string
    {
        return $this->currentLanguage;
    }
    
    /**
     * Get available languages based on existing language files
     * 
     * @return array Array of language codes
     */
    public function getAvailableLanguages(): array
    {
        $languages = [];
        // GEÄNDERT: Sucht nach .json Dateien
        $files = glob($this->languageDirectory . '/*.json');
        
        foreach ($files as $file) {
            // GEÄNDERT: Entfernt .json von Dateinamen
            $filename = basename($file, '.json');
            if (preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $filename)) {
                $languages[] = $filename;
            }
        }
        
        return $languages;
    }
    
    /**
     * Translate a key with optional string formatting
     * 
     * @param string $key Translation key
     * @param mixed ...$args Arguments for sprintf formatting
     * @return string Translated and formatted string
     */
    public function translate(string $key, mixed ...$args): string
    {
        $translation = $this->getTranslation($key);
        
        if (empty($args)) {
            return $translation;
        }
        
        return sprintf($translation, ...$args);
    }
    
    /**
     * Alias for translate method (shorter syntax)
     * 
     * @param string $key Translation key
     * @param mixed ...$args Arguments for sprintf formatting
     * @return string Translated and formatted string
     */
    public function t(string $key, mixed ...$args): string
    {
        return $this->translate($key, ...$args);
    }
    
    /**
     * Check if a translation key exists
     * 
     * @param string $key Translation key
     * @return bool
     */
    public function hasTranslation(string $key): bool
    {
        return $this->getNestedValue($this->translations, $key) !== null;
    }
    
    /**
     * Get all translations for the current language
     * 
     * @return array
     */
    public function getAllTranslations(): array
    {
        return $this->translations;
    }
    
    /**
     * Set fallback language
     * 
     * @param string $languageCode Fallback language code
     * @return self
     */
    public function setFallbackLanguage(string $languageCode): self
    {
        $this->fallbackLanguage = $languageCode;
        return $this;
    }
    
    /**
     * Get the fallback language code
     * 
     * @return string
     */
    public function getFallbackLanguage(): string
    {
        return $this->fallbackLanguage;
    }
    
    /**
     * Load language from session if available
     * 
     * @return self
     */
    public function loadFromSession(): self
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['language']) && $this->languageFileExists($_SESSION['language'])) {
            $this->setLanguage($_SESSION['language']);
        }
        
        return $this;
    }
    
    /**
     * Clear language from session
     * 
     * @return self
     */
    public function clearSession(): self
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        unset($_SESSION['language']);
        
        return $this;
    }
    
    /**
     * Validate that the language directory exists and is readable
     * 
     * @throws InvalidArgumentException If directory doesn't exist or isn't readable
     */
    private function validateLanguageDirectory(): void
    {
        if (!is_dir($this->languageDirectory)) {
            throw new InvalidArgumentException("Language directory '{$this->languageDirectory}' does not exist");
        }
        
        if (!is_readable($this->languageDirectory)) {
            throw new InvalidArgumentException("Language directory '{$this->languageDirectory}' is not readable");
        }
    }
    
    /**
     * Check if a language file exists
     * 
     * @param string $languageCode Language code
     * @return bool
     */
    private function languageFileExists(string $languageCode): bool
    {
        $filePath = $this->getLanguageFilePath($languageCode);
        return file_exists($filePath) && is_readable($filePath);
    }
    
    /**
     * Get the full path to a language file
     * 
     * @param string $languageCode Language code
     * @return string
     */
    private function getLanguageFilePath(string $languageCode): string
    {
        // GEÄNDERT: Verwendet .json als Dateiendung
        return $this->languageDirectory . DIRECTORY_SEPARATOR . $languageCode . '.json';
    }
    
    /**
     * Load translations from a language file
     * 
     * @param string $languageCode Language code
     * @throws RuntimeException If language file cannot be loaded or is invalid
     */
    private function loadLanguage(string $languageCode): void
    {
        $filePath = $this->getLanguageFilePath($languageCode);
        
        if (!$this->languageFileExists($languageCode)) {
            throw new RuntimeException("Cannot load language file: {$filePath}");
        }
        
        // GEÄNDERT: Lädt und dekodiert die JSON-Datei
        $this->translations = $this->loadTranslationsFromFile($filePath);
    }
    
    /**
     * Get translation for a key with fallback support
     * 
     * @param string $key Translation key (supports dot notation like 'user.name')
     * @return string
     */
    private function getTranslation(string $key): string
    {
        // Try to get translation from current language
        $translation = $this->getNestedValue($this->translations, $key);
        
        if ($translation !== null) {
            return $translation;
        }
        
        // Try fallback language if different from current
        if ($this->fallbackLanguage !== $this->currentLanguage && 
            $this->languageFileExists($this->fallbackLanguage)) {
            
            // GEÄNDERT: Lädt die Fallback-Sprachdatei als JSON
            try {
                $fallbackFilePath = $this->getLanguageFilePath($this->fallbackLanguage);
                $fallbackTranslations = $this->loadTranslationsFromFile($fallbackFilePath);
                $fallbackTranslation = $this->getNestedValue($fallbackTranslations, $key);
                
                if ($fallbackTranslation !== null) {
                    return $fallbackTranslation;
                }
            } catch (RuntimeException $e) {
                // Optional: Log the error that the fallback file is corrupt
                // For now, we just ignore it and return the key
            }
        }
        
        // Return key if no translation found
        return $key;
    }
    
    /**
     * Get nested array value using dot notation
     * 
     * @param array $array The array to search in
     * @param string $key Dot-separated key (e.g., 'user.profile.name')
     * @return string|null
     */
    private function getNestedValue(array $array, string $key): ?string
    {
        $keys = explode('.', $key);
        $value = $array;
        
        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }
        
        return is_string($value) ? $value : null;
    }

    /**
     * NEU: Helper-Methode zum Laden und Parsen einer JSON-Datei
     *
     * @param string $filePath The full path to the JSON file
     * @return array The decoded translations
     * @throws RuntimeException if the file is not readable or contains invalid JSON
     */
    private function loadTranslationsFromFile(string $filePath): array
    {
        $jsonContent = @file_get_contents($filePath);
        if ($jsonContent === false) {
            throw new RuntimeException("Could not read language file: {$filePath}");
        }

        $translations = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON in '{$filePath}': " . json_last_error_msg());
        }

        if (!is_array($translations)) {
            throw new RuntimeException("Language file '{$filePath}' must contain a JSON object.");
        }

        return $translations;
    }
}