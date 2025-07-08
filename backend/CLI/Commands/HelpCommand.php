<?php

namespace SquareRouting\CLI\Commands;

use SquareRouting\Core\CLI\BaseCommand;
use SquareRouting\Core\CLI\Colors;
use SquareRouting\Core\CLI\CommandInterface;

class HelpCommand extends BaseCommand implements CommandInterface {
    private array $commands;
    
    public function __construct(array $commands) {
        $this->setCommands($commands);
    }

    public function setCommands(array $commands): void {
        $this->commands = $commands;
    }
    
    public function execute(array $args): int {
        $this->output("Available Commands:", Colors::BOLD . Colors::GREEN);
        $this->output("");
        
        foreach ($this->commands as $name => $command) {
            $this->output(sprintf("  %-20s %s", $name, $command->getDescription()));
        }
        
        $this->output("");
        $this->output("Usage: php cli.php [command] [options]", Colors::YELLOW);
        return 0;
    }
    
    public function getDescription(): string {
        return "Show available commands";
    }
}