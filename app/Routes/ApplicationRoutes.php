<?php
declare(strict_types=1);

namespace SquareRouting\Routes;

use SquareRouting\Controllers\ExampleController;
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Interfaces\RoutableInterface;
use SquareRouting\Core\Route;
use SquareRouting\Filters\ExampleFilter;

class ApplicationRoutes implements RoutableInterface
{
    public function getRoute(DependencyContainer $container) : Route
    {
        $route = new Route($container);

        // Example how to add a new pattern
        $route->addPattern('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

        // Reroute Example
        $route->reroute('/reroute-test', '/google');
        
        // Get Examples
        $route->get('/html', ExampleController::class, 'showHtmlPage');
        $route->get('/test/:myid', ExampleController::class, 'someTest', ['myid' => 'num']);
        $route->get('/redirect-to-google', ExampleController::class, 'redirectToGoogle');
        $route->get('/rate-limit-example', ExampleController::class, 'rateLimiterExample');
        $route->get('/cache-example', ExampleController::class, 'cacheExample');
        $route->get('/dashboard/:location', ExampleController::class, 'dashboardExample', ['location' => 'path']);
        $route->get('/dotenv-example', ExampleController::class, 'envExample');
        $route->get('/filtertest', ExampleController::class, 'filterTest')
              ->filter([ExampleFilter::class]);

        // Post Example
        $route->post('/post-example', ExampleController::class, 'handlePostRequest');

        return $route;
    }
}
