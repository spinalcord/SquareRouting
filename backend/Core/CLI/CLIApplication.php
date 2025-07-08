<?php

namespace SquareRouting\Core\CLI;

use SquareRouting\Core\CLI\Colors;
use SquareRouting\Core\CLI\CommandInterface;
use Exception;

class CLIApplication {
    private array $commands = [];
    
    public function registerCommand(string $name, CommandInterface $command): void {
        $this->commands[$name] = $command;
    }

    public function getCommands(): array {
        return $this->commands;
    }
    
    public function run(array $argv): int {
        $this->showHeader();
        
        // Get command from arguments
        $command = $argv[1] ?? 'help';
        $args = array_slice($argv, 2);
        
        if (!isset($this->commands[$command])) {
            $this->error("Unknown command: $command");
            $this->output("Run 'php cli.php help' to see available commands.", Colors::YELLOW);
            return 1;
        }
        
        try {
            return $this->commands[$command]->execute($args);
        } catch (Exception $e) {
            $this->error("Error executing command: " . $e->getMessage());
            return 1;
        }
    }
    
    private function showHeader(): void {
        $this->output("", Colors::CYAN);
        $this->output("╔══════════════════════════════════════╗", Colors::CYAN);
        $this->output("║          PHP CLI Application         ║", Colors::CYAN);
        $this->output("║              v1.0.0                  ║", Colors::CYAN);
        $this->output("╚══════════════════════════════════════╝", Colors::CYAN);
        $this->output("");
    }
    
    private function output(string $message, string $color = Colors::RESET): void {
        echo $color . $message . Colors::RESET . PHP_EOL;
    }
    
    private function error(string $message): void {
        $this->output("✗ " . $message, Colors::RED);
    }
}