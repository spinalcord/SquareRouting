<?php

declare(strict_types=1);

namespace SquareRouting\Routes;

use SquareRouting\Controllers\AuthenticationController;
use SquareRouting\Controllers\CacheExampleController;
use SquareRouting\Controllers\ConfigExampleController;
use SquareRouting\Controllers\DashboardExampleController;
use SquareRouting\Controllers\DatabaseExampleController;
use SquareRouting\Controllers\EnvExampleController;
use SquareRouting\Controllers\FilterExampleController;
use SquareRouting\Controllers\HomeExampleController;
use SquareRouting\Controllers\HtmlPageExampleController;
use SquareRouting\Controllers\HttpRequestExampleController;
use SquareRouting\Controllers\LanguageExampleController;
use SquareRouting\Controllers\MarkdownExampleController;
use SquareRouting\Controllers\RateLimiterExampleController;
use SquareRouting\Controllers\TableExampleController;
use SquareRouting\Controllers\TemplateExampleController;
use SquareRouting\Controllers\TestExampleController;
use SquareRouting\Controllers\ValidatorExampleController;
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Interfaces\RoutableInterface;
use SquareRouting\Core\Route;
use SquareRouting\Filters\ExampleFilter;

class ApplicationRoutes implements RoutableInterface
{
    public function getRoute(DependencyContainer $container): Route
    {
        $route = new Route($container);

        // Example how to add a new pattern
        $route->addPattern('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

        $route->get('/', HomeExampleController::class, 'home');
        // Reroute Example
        $route->reroute('/reroute-test', '/google');
        // Config Example
        $route->get('/config-example', ConfigExampleController::class, 'configExample');

        $route->get('/table-example', TableExampleController::class, 'tableExample');
        // Get Examples
        $route->get('/html', HtmlPageExampleController::class, 'showHtmlPage');
        $route->get('/test/:myid', TestExampleController::class, 'someTest', ['myid' => 'num']);
        $route->get('/rate-limit-example', RateLimiterExampleController::class, 'rateLimiterExample');
        $route->get('/cache-example', CacheExampleController::class, 'cacheExample');
        $route->get('/validator-example', ValidatorExampleController::class, 'showValidatorExample');
        $route->get('/dashboard/:location', DashboardExampleController::class, 'dashboardExample', ['location' => 'path']);
        $route->get('/dotenv-example', EnvExampleController::class, 'envExample');
        $route->get('/database-example', DatabaseExampleController::class, 'databaseExamples');
        $route->get('/filtertest', FilterExampleController::class, 'filterTest')
            ->filter([ExampleFilter::class]);

        $route->get('/template-example', TemplateExampleController::class, 'templateExample');
        $route->get('/markdown-example', MarkdownExampleController::class, 'showMarkdownExample');
        $route->get('/account-example', AuthenticationController::class, 'accountExample');

        $route->get('/language-example', LanguageExampleController::class, 'languageExample');

        // Post Example
        $route->post('/post-example', HttpRequestExampleController::class, 'handlePostRequest');
        $route->post('/validate-example-post', ValidatorExampleController::class, 'validateExample');

        // Put Example
        $route->put('/put-example/:id', HttpRequestExampleController::class, 'handlePutRequest', ['id' => 'num']);

        // Delete Example
        $route->delete('/delete-example/:id', HttpRequestExampleController::class, 'handleDeleteRequest', ['id' => 'num']);

        // Patch Example
        $route->patch('/patch-example/:id', HttpRequestExampleController::class, 'handlePatchRequest', ['id' => 'num']);

        return $route;
    }
}
