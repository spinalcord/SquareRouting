<?php

namespace SquareRouting\Controllers;

use SquareRouting\Core\Configuration;
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Response;

class ConfigExampleController
{
    private Configuration $config;

    public function __construct(DependencyContainer $container)
    {
        $this->config = $container->get(Configuration::class);
    }

    public function configExample(): Response
    {
       // Registriere verschiedene Konfigurationen
       $this->config->register("app.name", "SquareRouting", "Application Name", "The name of the application");
       $this->config->register("app.version", "1.0.0", "Application Version", "Current version of the application");
       $this->config->register("app.debug", true, "Debug Mode", "Enable or disable debug mode");
       $this->config->register("app.timezone", "Europe/Berlin", "Timezone", "Default timezone for the application");
       
       // Database Konfigurationen
       $this->config->register("database.max_connections", 100, "Max DB Connections", "Maximum number of database connections");
       $this->config->register("database.timeout", 30, "DB Timeout", "Database connection timeout in seconds");
       $this->config->register("database.retry_attempts", 3, "Retry Attempts", "Number of retry attempts for failed queries");
       
       // Cache Konfigurationen
       $this->config->register("cache.default_ttl", 3600, "Default Cache TTL", "Default cache time-to-live in seconds");
       $this->config->register("cache.enabled", true, "Cache Enabled", "Enable or disable caching");
       
       // Verschachtelte Feature-Konfigurationen
       $this->config->register("features.auth.enabled", true, "Authentication Enabled", "Enable authentication system");
       $this->config->register("features.auth.providers.ldap.enabled", false, "LDAP Provider", "Enable LDAP authentication");
       $this->config->register("features.auth.providers.oauth.enabled", true, "OAuth Provider", "Enable OAuth authentication");
       $this->config->register("features.auth.session.timeout", 1800, "Session Timeout", "Session timeout in seconds");
       $this->config->register("features.logging.level", "info", "Log Level", "Application logging level");
       
       // API Konfigurationen
       $this->config->register("api.rate_limit.requests", 200, "API Rate Limit", "Number of requests per minute");
       $this->config->register("api.rate_limit.enabled", true, "Rate Limiting", "Enable API rate limiting");
       
       // Error (rate_limit was a category before): $this->config->register("api.rate_limit", true, "Rate Limiting", "Enable API rate limiting");
       // Speichere alle Registrierungen
       $this->config->save();
       
       // Demonstriere verschiedene Operationen
       $operations = [];
       
       // 1. Einzelne Werte abrufen
       $operations['get_single_values'] = [
           'app_name' => $this->config->get("app.name"),
           'debug_mode' => $this->config->get("app.debug"),
           'db_timeout' => $this->config->get("database.timeout"),
           'nonexistent' => $this->config->get("nonexistent.key", "default_fallback")
       ];
       
       // 2. Werte ändern
       $this->config->set("app.debug", false);
       $this->config->set("database.timeout", 45);
       $this->config->set("features.auth.session.timeout", 3600);
       $this->config->set("api.rate_limit.requests", 230);
       
       // 3. Verschachtelte Arrays abrufen
       $operations['nested_arrays'] = [
           'all_app_config' => $this->config->getArray("app"),
           'database_config' => $this->config->getArray("database"),
           'auth_features' => $this->config->getArray("features.auth"),
           'auth_providers' => $this->config->getArray("features.auth.providers"),
           'rate_limit' => $this->config->getArray("api.rate_limit"),
           'api_config' => $this->config->getArray("api")
       ];
       
       // 5. Konfiguration zurücksetzen
       $this->config->reset("app.debug"); // Zurück auf true
       
       // Speichere finale Änderungen
       $this->config->save();
       
       $response = [
           'status' => 'success',
           'message' => 'Configuration system demonstration completed',
           'operations' => $operations,
           'examples' => [
               'register' => 'config->register("key", "default", "label", "description")',
               'set' => 'config->set("key", "new_value")',
               'get' => 'config->get("key", "fallback")',
               'get_array' => 'config->getArray("namespace")',
               'save' => 'config->save()',
               'reset' => 'config->reset("key")'
           ]
       ];
       
       return (new Response)->json($response, 200, true);
    }
}