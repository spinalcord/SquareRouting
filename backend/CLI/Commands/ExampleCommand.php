<?php

namespace SquareRouting\CLI\Commands;

use SquareRouting\Core\CLI\Colors;
use SquareRouting\Core\CLI\BaseCommand;
use SquareRouting\Core\CLI\CommandInterface;

class ExampleCommand extends BaseCommand implements CommandInterface {
    public function execute(array $args): int {
        $this->info("Creating backup...");
        $this->info("Creating backup: TestBackup");
        for ($i = 1; $i <= 5; $i++) {
            sleep(1);
            $this->progress($i, 5, "Backing up data...");
        }
        $this->output("Check your Data", Colors::BOLD . Colors::GREEN);
        $this->success("Backup created successfully: TestBackup");
        $this->warning("Make sure everything is fine.");
        $to = $this->ask("Enter recipient email");
        $subject = $this->ask("Enter subject", false) ?: "Test Email";
        $this->confirm("Your Subject was: {$subject}?");

        $this->error("Test error message: {$subject}?");
        return 0;
    }
    
    public function getDescription(): string {
        return "Create a system backup";
    }
}
