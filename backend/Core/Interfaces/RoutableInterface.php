<?php

namespace SquareRouting\Core\Interfaces;

use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Route;

interface RoutableInterface
{
    public function getRoute(DependencyContainer $container): Route;
}
