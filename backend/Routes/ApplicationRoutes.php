<?php
declare(strict_types=1);

namespace SquareRouting\Routes;
// Controllers
use SquareRouting\Controllers\{
    BlogController,
    AuthenticationController,
    CacheExampleController,
    ConfigExampleController,
    DashboardExampleController,
    DatabaseExampleController,
    EnvExampleController,
    FilterExampleController,
    HomeExampleController,
    HtmlPageExampleController,
    HttpRequestExampleController,
    LanguageExampleController,
    RateLimiterExampleController,
    TableExampleController,
    TemplateExampleController,
    TestExampleController,
    ValidatorExampleController
};

// Core & Utilities
use SquareRouting\Core\{
    DotEnv,
    DependencyContainer,
    Interfaces\RoutableInterface,
    Route
};

// Filters & Validation
use SquareRouting\Filters\ExampleFilter;
use SquareRouting\Core\Validation\Rules\{
    IsNumber,
    IsString,
    IsPath 
};
 
class ApplicationRoutes implements RoutableInterface
{
    public function getRoute(DependencyContainer $container): Route
    {
        $route = new Route($container);
        $dotEnv = $container->get(DotEnv::class);


        $route->get('/', HomeExampleController::class, 'home');
        // Reroute Example
        $route->reroute('/reroute-test', '/google');
        // Config Example
        $route->get('/config-example', ConfigExampleController::class, 'configExample');

        $route->get('/table-example', TableExampleController::class, 'tableExample');
        // Get Examples
        $route->get('/html', HtmlPageExampleController::class, 'showHtmlPage');
        $route->get('/test/:myid', TestExampleController::class, 'someTest', ['myid' => [new IsNumber()]]);
        $route->get('/rate-limit-example', RateLimiterExampleController::class, 'rateLimiterExample');
        $route->get('/cache-example', CacheExampleController::class, 'cacheExample');
        $route->get('/validator-example', ValidatorExampleController::class, 'showValidatorExample');
        $route->get('/dashboard/:location', DashboardExampleController::class, 'dashboardExample', ['location' => [new IsString()]]);
        $route->get('/dotenv-example', EnvExampleController::class, 'envExample');
        $route->get('/database-example', DatabaseExampleController::class, 'databaseExamples');
        $route->get('/filtertest', FilterExampleController::class, 'filterTest')
            ->filter([ExampleFilter::class]);

        $route->get('/template-example', TemplateExampleController::class, 'templateExample');
        $route->get('/account-example', AuthenticationController::class, 'accountExample');

        $route->get('/language-example', LanguageExampleController::class, 'languageExample');
        // blog example
        $route->get('/blog/:blogPath', BlogController::class, 'showBlog', ['blogPath' => [new IsPath()]]);
        $route->post('/blog/:blogPath', BlogController::class, 'getBlogContent', ['blogPath' => [new IsPath()]]);
        // cmd example
        $route->post('/cmd', HomeExampleController::class, 'cmdExample');

        // Post Example
        $route->post('/post-example', HttpRequestExampleController::class, 'handlePostRequest');
        $route->post('/validate-example-post', ValidatorExampleController::class, 'validateExample');

        // Put Example
        $route->put('/put-example/:id', HttpRequestExampleController::class, 'handlePutRequest', ['id' => [new IsNumber()]]);

        // Delete Example
        $route->delete('/delete-example/:id', HttpRequestExampleController::class, 'handleDeleteRequest', ['id' => [new IsNumber()]]);

        // Patch Example
        $route->patch('/patch-example/:id', HttpRequestExampleController::class, 'handlePatchRequest', ['id' => [new IsNumber()]]);
        
        return $route;
    }
}
