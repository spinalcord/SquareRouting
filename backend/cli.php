<?php


#!/usr/bin/env php

/**
 * Modern PHP CLI Application
 * 
 * Usage: php cli.php [command] [options]
 * 
 * 
 * Add new commands by creating classes in the Commands directory
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Composer Autoloader
require __DIR__ . '/../vendor/autoload.php';

use SquareRouting\Core\CLI\CLIApplication;
use SquareRouting\CLI\Commands\HelpCommand;
use SquareRouting\CLI\Commands\VersionCommand;
use SquareRouting\CLI\Commands\ExampleCommand;

// Run the application
if (php_sapi_name() === 'cli') {
    $app = new CLIApplication();

    // Register commands
    // First, instantiate all commands
    $helpCommand = new HelpCommand([]); // Initialize with empty array, will be updated later
    $versionCommand = new VersionCommand();
    $exampleCommand = new ExampleCommand();

    // Register them with the application
    $app->registerCommand('help', $helpCommand);
    $app->registerCommand('version', $versionCommand);
    $app->registerCommand('exampleCommand', $exampleCommand);

    // Now update the HelpCommand with the full list of registered commands
    $helpCommand->setCommands($app->getCommands());

    exit($app->run($argv));
} else {
    echo "This script can only be run from the command line.";
    exit(1);
}