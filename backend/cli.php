<?php

declare(strict_types=1);

use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\DotEnv;
use SquareRouting\Core\CLI\CommandInterface;
use SquareRouting\Core\CLI\Commands\GenerateSchemaCommand;
use SquareRouting\Core\CLI\Commands\HelpCommand;

require_once __DIR__ . '/../vendor/autoload.php';

////////////////////////////////////
// SETUP DependencyContainer
////////////////////////////////////
$envFileLocation = __DIR__ . '/Configs/.env';
$cacheLocation = __DIR__ . '/Cache';
$sqliteFileLocation = __DIR__ . '/Database/';
$container = new DependencyContainer;

$container->register(DotEnv::class, parameters: ['path' => $envFileLocation]);
$dotEnv = $container->get(DotEnv::class);

$container->register(SquareRouting\Core\Scheme::class);
$scheme = $container->get(SquareRouting\Core\Scheme::class);
$container->register(SquareRouting\Core\CLI\SchemaGenerator::class, parameters: ['scheme' => $scheme]);

$args = $_SERVER['argv'];
$commandName = $args[1] ?? ''; // Default to 'help' if no command is provided

////////////////////////////////////
// CLI Command Handling
////////////////////////////////////

// Add your commands Like this

$generateSchemaCommand = new GenerateSchemaCommand($args, $container);
$helpCommand = new HelpCommand($args, $container);
//other cmd

$commandInstances = [
    $generateSchemaCommand,
    $helpCommand,
    //other cmd
];


////////////////////////////////////
////////////////////////////////////

$commands = [];
foreach ($commandInstances as $command) {
    $commands[$command->getName()] = $command;
}

if (isset($commands[$commandName])) {
    $commands[$commandName]->execute($args);
} else {
    // echo "Unknown command: {$commandName}\n";
    echo "Available CLI commands:\n";
    foreach ($commands as $name => $command) {
        echo sprintf("  %-20s - %s\n", $command->getName(), $command->getDescription());
    }
}
