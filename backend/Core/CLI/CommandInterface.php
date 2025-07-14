<?php

namespace SquareRouting\Core\CLI;

interface CommandInterface
{
    public function getName(): string;
    public function getDescription(): string;
    public function execute(array $args, string $input, string $commandId): array;
    public function requiresPermission(): ?string;
}
