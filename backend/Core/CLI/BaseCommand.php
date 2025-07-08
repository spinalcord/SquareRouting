<?php

namespace SquareRouting\Core\CLI;

use SquareRouting\Core\CLI\Colors;
use SquareRouting\Core\CLI\CommandInterface;

abstract class BaseCommand implements CommandInterface {
    protected function output(string $message, string $color = Colors::RESET): void {
        echo $color . $message . Colors::RESET . PHP_EOL;
    }
    
    protected function success(string $message): void {
        $this->output("✓ " . $message, Colors::GREEN);
    }
    
    protected function error(string $message): void {
        $this->output("✗ " . $message, Colors::RED);
    }
    
    protected function warning(string $message): void {
        $this->output("⚠ " . $message, Colors::YELLOW);
    }
    
    protected function info(string $message): void {
        $this->output("ℹ " . $message, Colors::BLUE);
    }
    
    protected function ask(string $question, bool $required = true): string {
        do {
            $this->output($question . ": ", Colors::CYAN);
            $input = trim(fgets(STDIN));
            
            if (!$required || !empty($input)) {
                return $input;
            }
            
            $this->error("This field is required!");
        } while (true);
    }
    
    protected function confirm(string $question): bool {
        $this->output($question . " (y/N): ", Colors::YELLOW);
        $input = trim(fgets(STDIN));
        return strtolower($input) === 'y' || strtolower($input) === 'yes';
    }
    
    protected function progress(int $current, int $total, string $message = ''): void {
        $percent = round(($current / $total) * 100);
        $bar = str_repeat('█', floor($percent / 2));
        $space = str_repeat('░', 50 - floor($percent / 2));
        
        echo "\r" . Colors::CYAN . "Progress: [" . $bar . $space . "] " . $percent . "% " . $message . Colors::RESET;
        
        if ($current === $total) {
            echo PHP_EOL;
        }
    }
}