<?php

namespace SquareRouting\Core\CLI\Commands;
use  SquareRouting\Core\CLI\CommandRegistry;

class HelpCommand extends BaseCommand
{
    private CommandRegistry $registry;

    public function __construct($container, CommandRegistry $registry)
    {
        parent::__construct($container);
        $this->registry = $registry;
    }

    public function getName(): string
    {
        return 'help';
    }

    public function getDescription(): string
    {
        return 'Show available commands';
    }

    public function execute(array $args, string $input, string $commandId): array
    {
        $output = "Available commands:\n\n";
        
        foreach ($this->registry->getAll() as $command) {
            $output .= sprintf("%-15s %s\n", $command->getName(), $command->getDescription());
        }

        return $this->createResponse($output);
    }
}
