<?php

use SquareRouting\Core\Account;
use SquareRouting\Core\Cache;
use SquareRouting\Core\Configuration;
use SquareRouting\Core\CorsMiddleware;
use SquareRouting\Core\MarkdownRenderer;
use SquareRouting\Core\Database;
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\DotEnv;
use SquareRouting\Core\Language;
use SquareRouting\Core\RateLimiter;
use SquareRouting\Core\Request;
use SquareRouting\Core\RouteCollector;
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
$envFileLocation = __DIR__ . '/../backend/Configs/.env';
$cacheLocation = __DIR__ . '/../backend/Cache';
$templateLocation = __DIR__ . '/../backend/Templates/';
$sqliteFileLocation = __DIR__ . '/../backend/Database/';
$rateLimitTempFileLocation = $cacheLocation . '/rate_limit.cache';
$languagesLocation = __DIR__ . '/../backend/Languages/';
$container = new DependencyContainer;

$container->register(DotEnv::class, parameters: ['path' => $envFileLocation]);
$dotEnv = $container->get(DotEnv::class);

$container->register(Request::class);
$request = $container->get(Request::class);

$container->register(Cache::class, parameters: ['cacheDir' => $cacheLocation, '$defaultTtl' => 3600]);
$cache = $container->get(Cache::class);

$container->register(RateLimiter::class, parameters: ['dataFile' => $rateLimitTempFileLocation]);
$rateLimiter = $container->get(RateLimiter::class);

// Database connection
$container->register(Database::class, parameters: ['dotEnv' => $dotEnv, 'sqlitePath' => $sqliteFileLocation, 'cache' => $cache]);
$db = $container->get(Database::class);
$db->enableCaching($dotEnv->get('DB_CACHING'));
// View
$container->register(View::class, parameters: ['templateDir' => $templateLocation, 'cacheDir' => $cacheLocation]);
$view = $container->get(View::class);

// Auth
$container->register(Account::class, parameters: ['container' => $container]);
$account = $container->get(Account::class);

// Languages
$container->register(Language::class, parameters: ['languageDirectory' => $languagesLocation, 'defaultLanguage' => empty($_SESSION['language']) ? $dotEnv->get('DEFAULT_LANGUAGE') : $_SESSION['language']]);
$language = $container->get(Language::class);

// Configuration
$container->register(Configuration::class, parameters: ['database' => $db, 'autosave' => false]);
$config = $container->get(Configuration::class);

// Configuration
// You can also create a MarkdownRenderer instance everywhere, but we wan't use the same
// cache system instance everywhere for best performance.
$container->register(MarkdownRenderer::class, parameters: ['cache' => $cache]);
$config = $container->get(MarkdownRenderer::class);

// //////////////////////////////////
// Cors protection (add your domain to the array)
// //////////////////////////////////
$corsMiddleware = new CorsMiddleware;
$corsMiddleware->handle($dotEnv->get('ALLOWED_ORIGINS'));

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
