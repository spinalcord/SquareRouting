<?php
declare(strict_types=1);

namespace SquareRouting\Routes;

use SquareRouting\Controllers\ExampleController;
use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Interfaces\RoutableInterface;
use SquareRouting\Core\Route;


class ApplicationRoutes implements RoutableInterface
{
    public function getRoute(DependencyContainer $container) : Route
    {
        $route = new Route($container);

        // Example how to add a new pattern
        $route->addPattern('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

        //if($account->hasPermission($allPermissions, "can_manage_blogs"))
        /* $route->get('/category/list/:page', CategoryController::class, 'list', ['page' => 'num']); */
        /* $route->get('/category/list/:page/:search', CategoryController::class, 'listSearch', ['page' => 'num', 'search' => 'any']); */
        /* $route->post('/category/add', CategoryController::class, 'add'); */
        /* $route->get('/category/edit/:id', CategoryController::class, 'edit', ['id' => 'num']); */
        /* $route->post('/category/update', CategoryController::class, 'update'); */
        /* $route->post('/category/delete', CategoryController::class, 'delete'); */
        $route->reroute('/reroute-test', '/google');

        $route->get('/google', ExampleController::class, 'showHtmlPage');
        $route->get('/test/:num', ExampleController::class, 'someTest', ['page' => 'num']);

        return $route;
    }
}
