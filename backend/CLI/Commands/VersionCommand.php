<?php

namespace SquareRouting\CLI\Commands;

use SquareRouting\Core\CLI\BaseCommand;
use SquareRouting\Core\CLI\Colors;
use SquareRouting\Core\CLI\CommandInterface;

class VersionCommand extends BaseCommand implements CommandInterface {
    public function execute(array $args): int {
        $this->output("CLI Application v1.0.0", Colors::GREEN);
        $this->output("PHP Version: " . PHP_VERSION, Colors::BLUE);
        return 0;
    }
    
    public function getDescription(): string {
        return "Show application version";
    }
}