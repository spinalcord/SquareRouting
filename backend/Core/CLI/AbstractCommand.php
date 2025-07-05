<?php

declare(strict_types=1);

namespace SquareRouting\Core\CLI;

use SquareRouting\Core\DependencyContainer;

abstract class AbstractCommand implements CommandInterface
{
    protected array $args;
    protected DependencyContainer $container;

    public function __construct(array $args, DependencyContainer $container)
    {
        $this->args = $args;
        $this->container = $container;
    }

    abstract public function getName(): string;
    abstract public function getDescription(): string;
    abstract public function execute(array $args): void;
}