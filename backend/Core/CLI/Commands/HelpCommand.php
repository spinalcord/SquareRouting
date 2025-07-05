<?php

declare(strict_types=1);

namespace SquareRouting\Core\CLI\Commands;

use SquareRouting\Core\CLI\AbstractCommand;
use SquareRouting\Core\CLI\CommandInterface;

class HelpCommand extends AbstractCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'help';
    }

    public function getDescription(): string
    {
        return 'Displays this help message.';
    }

    public function execute(array $args): void
    {
        // The main cli.php script now handles displaying the full list of commands.
        // This command can simply output a generic help message or be removed if not needed.
        // No action needed here as cli.php handles the display.
         echo "Schema generation complete.\n";

    }
}