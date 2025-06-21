<?php
declare(strict_types=1);

namespace SquareRouting\Core;

class RouteCollector
{
    private array $routeInstances = [];
    public static $avaibleRoutes = [];

    function __construct(public DependencyContainer $container)
    {
    }

    // Eine Route-Instanz hinzufügen
    public function add(Route $route): void
    {
        $this->routeInstances[] = $route;
    }

    // Führt alle Routen der gesammelten Route-Instanzen zusammen
    public function getMergedRoute(): Route
    {
        $merged = new Route($this->container);

        foreach ($this->routeInstances as $routeInstance) {
            foreach ($routeInstance->getRoutes() as $routeDefinition) {
                $merged->addRouteDefinition($routeDefinition);
            }
            self::$avaibleRoutes = array_merge(self::$avaibleRoutes, $routeInstance->getRoutesStrArray());
        }
        return $merged;
    }

    // Dispatch auf der zusammengeführten Route ausführen
    public function dispatch(): void
    {
        $mergedRoute = $this->getMergedRoute();
        $mergedRoute->dispatch();
    }
}
