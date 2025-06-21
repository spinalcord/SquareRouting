<?php

namespace SquareRouting;

use SquareRouting\Core\RateLimiter;
use SquareRouting\Core\Cache;
use SquareRouting\Routes\ApplicationRoutes;
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Request;
use SquareRouting\Core\RouteCollector;

require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/errorHandler.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

////////////////////////////////////
// SETUP DependencyContainer
////////////////////////////////////

$container = new DependencyContainer();


$container->register(Request::class);
$request = $container->get(Request::class); 

$container->register(Cache::class, parameters:
    ['cacheDir' => __DIR__ . "/Cache", '$defaultTtl' => 3600]);
$cache = $container->get(Cache::class);

$container->register(RateLimiter::class);
$rateLimiter = $container->get(RateLimiter::class); 

$routeCollector = new RouteCollector($container);
$applicationRoute = new ApplicationRoutes();

////////////////////////////////////
// SETUP Routing
////////////////////////////////////

$routeCollector->add($applicationRoute->getRoute($container));
$routeCollector->dispatch();
