<?php

declare(strict_types=1);

namespace SquareRouting\Core;

class RouteCollector
{
    private array $routeInstances = [];

    public function __construct(public DependencyContainer $container) {}

    // Add a Route instance
    public function add(Route $route): void
    {
        $this->routeInstances[] = $route;
    }

    // Merges all routes from the collected Route instances
    public function getMergedRoute(): Route
    {
        $merged = new Route($this->container);

        foreach ($this->routeInstances as $routeInstance) {
            foreach ($routeInstance->getRoutes() as $routeDefinition) {
                $merged->addRouteDefinition($routeDefinition);
            }
        }

        return $merged;
    }

    // Execute dispatch on the merged route
    public function dispatch(): bool
    {
        $mergedRoute = $this->getMergedRoute();

        return $mergedRoute->dispatch();
    }
}
