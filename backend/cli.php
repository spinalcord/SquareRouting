<?php

declare(strict_types=1);

use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\DotEnv;
use SquareRouting\Core\CLI\SchemaGenerator;
use SquareRouting\Core\Scheme;

require_once __DIR__ . '/../vendor/autoload.php';

// //////////////////////////////////
// SETUP DependencyContainer
// //////////////////////////////////
$envFileLocation = __DIR__ . '/Configs/.env';
$cacheLocation = __DIR__ . '/Cache';
$sqliteFileLocation = __DIR__ . '/Database/';
$container = new DependencyContainer;

$container->register(DotEnv::class, parameters: ['path' => $envFileLocation]);
$dotEnv = $container->get(DotEnv::class);

// Register Scheme for SchemaGenerator
$container->register(Scheme::class);
$scheme = $container->get(Scheme::class);

// Register SchemaGenerator
$container->register(SchemaGenerator::class, parameters: ['scheme' => $scheme]);
$schemaGenerator = $container->get(SchemaGenerator::class);

// //////////////////////////////////
// CLI Command Handling
// //////////////////////////////////

$args = $_SERVER['argv'];
$command = $args[1] ?? 'help'; // Default to 'help' if no command is provided

switch ($command) {
    case 'generate:schema':
        echo "Generating schema constants...\n";
        $schemaGenerator->generate();
        echo "Schema generation complete.\n";
        break;
    case 'help':
    default:
        echo "Available CLI commands:\n";
        echo "  generate:schema  - Generates TableName.php and ColumnName.php from Scheme definitions.\n";
        echo "  help             - Displays this help message.\n";
        break;
}
