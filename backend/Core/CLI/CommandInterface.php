<?php

declare(strict_types=1);

namespace SquareRouting\Core\CLI;

interface CommandInterface
{
    public function getName(): string;
    public function getDescription(): string;
    public function execute(array $args): void;
}