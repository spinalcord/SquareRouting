<?php
declare(strict_types=1);

namespace SquareRouting\Routes;

use SquareRouting\Controllers\AuthenticationController;
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
        $route->get('/', ExampleController::class, 'home');
        
        $route->get('/table-example', ExampleController::class, 'tableExample');
        $route->get('/register', ExampleController::class, 'registerExample');
        // Get Examples
        $route->get('/html', ExampleController::class, 'showHtmlPage');
        $route->get('/test/:myid', ExampleController::class, 'someTest', ['myid' => 'num']);
        $route->get('/redirect-to-google', ExampleController::class, 'redirectToGoogle');
        $route->get('/rate-limit-example', ExampleController::class, 'rateLimiterExample');
        $route->get('/cache-example', ExampleController::class, 'cacheExample');
        $route->get('/validator-example', ExampleController::class, 'showValidatorExample');
        $route->get('/dashboard/:location', ExampleController::class, 'dashboardExample', ['location' => 'path']);
        $route->get('/dotenv-example', ExampleController::class, 'envExample');
        $route->get('/database-example', ExampleController::class, 'databaseExamples');
        $route->get('/filtertest', ExampleController::class, 'filterTest')
              ->filter([ExampleFilter::class]);

        $route->get('/template-example', ExampleController::class, 'templateExample');
        $route->get('/account-example', AuthenticationController::class, 'accountExample');
        
        $route->get('/language-example', ExampleController::class, 'languageExample');

        // Post Example
        $route->post('/post-example', ExampleController::class, 'handlePostRequest');
        $route->post('/validate-example-post', ExampleController::class, 'validateExample');

        // Put Example
        $route->put('/put-example/:id', ExampleController::class, 'handlePutRequest', ['id' => 'num']);

        // Delete Example
        $route->delete('/delete-example/:id', ExampleController::class, 'handleDeleteRequest', ['id' => 'num']);

        // Patch Example
        $route->patch('/patch-example/:id', ExampleController::class, 'handlePatchRequest', ['id' => 'num']);

        return $route;
    }
}
