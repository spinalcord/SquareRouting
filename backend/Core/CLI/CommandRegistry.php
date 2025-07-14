<?php

namespace SquareRouting\Core\CLI;

use SquareRouting\Core\CLI\CommandInterfaceM;

class CommandRegistry
{
    private array $commands = [];

    public function register(CommandInterface $command): void
    {
        $this->commands[$command->getName()] = $command;
    }

    public function get(string $name): ?CommandInterface
    {
        return $this->commands[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    public function getAll(): array
    {
        return $this->commands;
    }
}
