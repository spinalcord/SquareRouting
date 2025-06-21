<?php

namespace SquareRouting;

use SquareRouting\Core\Response;
use SquareRouting\Routes\ApplicationRoutes;
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Request;
use SquareRouting\Core\RouteCollector;

require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/errorHandler.php';

$container = new DependencyContainer();

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$container->register(Request::class);
$request = $container->get(Request::class); 

$routeCollector = new RouteCollector($container);
$applicationRoute = new ApplicationRoutes();


$routeCollector->add($applicationRoute->getRoute($container));

$routeCollector->dispatch();
