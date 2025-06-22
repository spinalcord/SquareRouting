<?php

use SquareRouting\Core\DotEnv;
use SquareRouting\Core\RateLimiter;
use SquareRouting\Core\Cache;
use SquareRouting\Routes\ApplicationRoutes;
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Request;
use SquareRouting\Core\RouteCollector;
use SquareRouting\Core\DatabaseConnection;

require_once __DIR__ . '/../backend/vendor/autoload.php';
require __DIR__ . '/errorHandler.php';



if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

////////////////////////////////////
// SETUP DependencyContainer
////////////////////////////////////
$cacheLocation =__DIR__ . "/../backend/Cache";

$container = new DependencyContainer();

$container->register(DotEnv::class, parameters: ['path' => __DIR__ . '/../backend/Configs/.env' ]);
$dotEnv = $container->get(DotEnv::class); 

$container->register(Request::class);
$request = $container->get(Request::class); 

$container->register(Cache::class, parameters:
    ['cacheDir' => $cacheLocation, '$defaultTtl' => 3600]);
$cache = $container->get(Cache::class);

$container->register(RateLimiter::class,parameters: ['dataFile' => $cacheLocation . "/rate_limit.json"]);
$rateLimiter = $container->get(RateLimiter::class); 

// Database connection
$container->register(DatabaseConnection::class, parameters: ['dotEnv' => $dotEnv, 'sqlitePath' => "../backend/Database/"]);
$db = $container->get(DatabaseConnection::class); 


////////////////////////////////////
// SETUP Routing
////////////////////////////////////

$routeCollector = new RouteCollector($container);
$applicationRoute = new ApplicationRoutes();

$routeCollector->add($applicationRoute->getRoute($container));

if($routeCollector->dispatch() == false)
{
    if(file_exists("index.html"))
    {
        // We use index.html as fallback if you are using a JavaScript framework such as svelte.
        // Otherwise you could also use a 404.html
        header("Content-Type: text/html");
        readfile("index.html");
    } // else: route/api call
}
