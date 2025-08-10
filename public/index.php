<?php

use SquareRouting\Core\Account;
use SquareRouting\Core\Cache;
use SquareRouting\Core\Configuration;
use SquareRouting\Core\CorsMiddleware;
use SquareRouting\Core\Database;
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\DotEnv;
use SquareRouting\Core\JsonSession;
use SquareRouting\Core\Language;
use SquareRouting\Core\RateLimiter;
use SquareRouting\Core\Request;
use SquareRouting\Core\RouteCollector;
use SquareRouting\Core\Schema;
use SquareRouting\Core\View;
use SquareRouting\Routes\ApplicationRoutes;

require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/errorHandler.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// //////////////////////////////////
// SETUP DependencyContainer
// //////////////////////////////////
$container = new DependencyContainer;

// Pfade als Strings im Container speichern
$container->set('env_file_location', fn() => __DIR__ . '/../backend/Configs/.env');
$container->set('cache_location', fn() => __DIR__ . '/../backend/Cache');
$container->set('template_location', fn() => __DIR__ . '/../backend/Templates/');
$container->set('sqlite_file_location', fn() => __DIR__ . '/../backend/Database/');
$container->set('rate_limit_temp_file_location', fn() => $container->get('cache_location') . '/rate_limit.cache');
$container->set('languages_location', fn() => __DIR__ . '/../backend/Languages/');
$container->set('schema_enums_location', fn() => __DIR__ . '/../backend/Core/Schema/');

$container->register(DotEnv::class, parameters: ['path' => $container->get('env_file_location')]);
$dotEnv = $container->get(DotEnv::class);

$container->register(Request::class);
$request = $container->get(Request::class);

$container->register(Cache::class, parameters: ['cacheDir' => $container->get('cache_location'), 'defaultTtl' => 3600, 'enabled' => !$dotEnv->get('DEVELOPER', true)]);
$cache = $container->get(Cache::class);

$container->register(RateLimiter::class, parameters: ['dataFile' => $container->get('rate_limit_temp_file_location')]);
$rateLimiter = $container->get(RateLimiter::class);

// Database connection
$container->register(Database::class, parameters: ['dotEnv' => $dotEnv, 'sqlitePath' => $container->get('sqlite_file_location'), 'cache' => $cache]);
$db = $container->get(Database::class);
$db->connect();
$db->enableCaching(!$dotEnv->get('DEVELOPER', true));

// View
$container->register(View::class, parameters: ['templateDir' => $container->get('template_location'), 'cacheDir' => $container->get('cache_location')]);
$view = $container->get(View::class);

// Auth
$container->register(Account::class, parameters: ['container' => $container]);
$account = $container->get(Account::class);

// Languages
$container->register(Language::class, parameters: ['languageDirectory' => $container->get('languages_location'), 'defaultLanguage' => empty($_SESSION['language']) ? $dotEnv->get('DEFAULT_LANGUAGE') : $_SESSION['language']]);
$language = $container->get(Language::class);


$config = null;

    $container->register(Configuration::class, parameters: ['database' => $db, 'autosave' => false]);
    $config = $container->get(Configuration::class);

// Configuration


// //////////////////////////////////
// Cors protection (add your domain to the array)
// //////////////////////////////////
$corsMiddleware = new CorsMiddleware;
$corsMiddleware->handle($dotEnv->get('ALLOWED_ORIGINS'));

// //////////////////////////////////
// Create tables
// //////////////////////////////////

  $schema = new Schema;

  $db->createTableIfNotExists($schema->role());
  $db->createTableIfNotExists($schema->account());
  $db->createTableIfNotExists($schema->permission());
  $db->createTableIfNotExists($schema->role_permissions());
  $db->createTableIfNotExists($schema->configuration());
  $account->initializeDefaultRoles();


// //////////////////////////////////
// SETUP Routing
// //////////////////////////////////

$routeCollector = new RouteCollector($container);
$applicationRoute = new ApplicationRoutes;

$routeCollector->add($applicationRoute->getRoute($container));

if ($routeCollector->dispatch() == false) {
    if (file_exists('index.html')) {
        // We use index.html as fallback if you are using a JavaScript framework such as svelte.
        // Otherwise you could also use a 404.html
        header('Content-Type: text/html');
        readfile('index.html');
    } // else: route/api call
}


