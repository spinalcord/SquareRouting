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
        $route->get('/database-example', ExampleController::class, 'databaseExamples');
        $route->get('/filtertest', ExampleController::class, 'filterTest')
              ->filter([ExampleFilter::class]);

        $route->get('/template-example', ExampleController::class, 'templateExample');
        
        // Account Routes
        $route->get('/account/register', ExampleController::class, 'registerAccount');
        $route->get('/account/login', ExampleController::class, 'loginAccount');
        $route->post('/account/register', ExampleController::class, 'handleRegisterAccount');
        $route->post('/account/login', ExampleController::class, 'handleLoginAccount');
        $route->get('/account/current', ExampleController::class, 'getCurrentAccount');
        $route->post('/account/logout', ExampleController::class, 'logoutAccount');
        $route->post('/account/change-password', ExampleController::class, 'changePassword');
        $route->post('/account/generate-reset-token', ExampleController::class, 'generateResetToken');
        $route->post('/account/reset-password', ExampleController::class, 'resetPassword');
        $route->post('/account/profile', ExampleController::class, 'updateAccountProfile');
        $route->post('/account/delete', ExampleController::class, 'deleteAccount');
        $route->post('/account/generate-verification-token', ExampleController::class, 'generateVerificationToken');
        $route->get('/account/verify-email', ExampleController::class, 'verifyEmail');

        // Post Example
        $route->post('/post-example', ExampleController::class, 'handlePostRequest');

        return $route;
    }
}
