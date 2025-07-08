<?php

namespace SquareRouting\Core\CLI;

interface CommandInterface {
    public function execute(array $args): int;
    public function getDescription(): string;
}