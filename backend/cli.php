<?php

declare(strict_types=1);

use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\DotEnv;
use SquareRouting\Core\CLI\CommandInterface;

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

////////////////////////////////////
// CLI Command Handling
////////////////////////////////////

$args = $_SERVER['argv'];
$commandName = $args[1] ?? ''; // Default to 'help' if no command is provided

$commands = [];
$commandClasses = [];

$commandDirectory = __DIR__ . '/Core/CLI/Commands/';
$commandFiles = scandir($commandDirectory);

foreach ($commandFiles as $file) {
    if (str_ends_with($file, 'Command.php')) {
        $className = str_replace('.php', '', $file);
        $fullClassName = 'SquareRouting\\Core\\CLI\\Commands\\' . $className;
        $commandClasses[] = $fullClassName;
    }
}

foreach ($commandClasses as $commandClass) {
    if (is_subclass_of($commandClass, CommandInterface::class)) {
        $commandInstance = new $commandClass($args, $container);
        $commands[$commandInstance->getName()] = $commandInstance;
    }
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
