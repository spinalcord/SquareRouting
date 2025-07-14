<?php

namespace SquareRouting\Controllers;

use Exception;
use InvalidArgumentException;
use RuntimeException;
use SquareRouting\Core\Account;
use SquareRouting\Core\CLI\ArgumentInterpreter;
use SquareRouting\Core\CLI\CLIResponsePattern;
use SquareRouting\Core\CLI\CommandRegistry;
use SquareRouting\Core\CLI\CommandWizard;
use SquareRouting\Core\CLI\StepFeedback;
use SquareRouting\Core\CLI\Commands\VersionCommand;
use SquareRouting\Core\CLI\Commands\LogoutCommand;
use SquareRouting\Core\CLI\Commands\HelpCommand;
use SquareRouting\Core\CLI\Commands\InstallCommand;
use SquareRouting\Core\CLI\Commands\LoginCommand;

use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\DotEnv;
use SquareRouting\Core\RateLimiter;
use SquareRouting\Core\Request;
use SquareRouting\Core\Response;
use SquareRouting\Core\Schema\Permission;
use SquareRouting\Core\Schema\Role;
use SquareRouting\Core\Validation\Rules\AlphaNumeric;
use SquareRouting\Core\Validation\Rules\Email;
use SquareRouting\Core\Validation\Rules\IsArray;
use SquareRouting\Core\Validation\Rules\IsBoolean;
use SquareRouting\Core\Validation\Rules\IsString;
use SquareRouting\Core\Validation\Rules\Max;
use SquareRouting\Core\Validation\Rules\Min;
use SquareRouting\Core\Validation\Rules\Password;
use SquareRouting\Core\Validation\Rules\Required;
use SquareRouting\Core\Validation\Validator;
use SquareRouting\Core\View;


class CliController
{
    public View $view;
    public DotEnv $dotEnv;
    private Request $request;
    public RateLimiter $rateLimiter;
    public Account $account;
    public DependencyContainer $container;
    public string $routePath;
    private CommandRegistry $commandRegistry;

    public function __construct(DependencyContainer $container)
    {
        $this->request = $container->get(Request::class);
        $this->view = $container->get(View::class);
        $this->dotEnv = $container->get(DotEnv::class);
        $this->rateLimiter = $container->get(RateLimiter::class);
        $this->account = $container->get(Account::class);
        $this->container = $container;
        $this->routePath = $container->get('routePath');
        
        $this->setupCommandRegistry();
    }

    /**
     * Setup and register all available commands
     */
    private function setupCommandRegistry(): void
    {
        $this->commandRegistry = new CommandRegistry();
        
        // Register core commands
        $this->commandRegistry->register(new VersionCommand($this->container));
        $this->commandRegistry->register(new LogoutCommand($this->container));
        $this->commandRegistry->register(new InstallCommand($this->container));
        $this->commandRegistry->register(new LoginCommand($this->container));

        // Help command needs registry reference to list commands
        $this->commandRegistry->register(new HelpCommand($this->container, $this->commandRegistry));
        
        // Here you can easily add more commands:
        // $this->commandRegistry->register(new YourCustomCommand($this->container));
    }

    public function showTerminal(): Response
    {
        $output = $this->view->render('cli.tpl', [
            'routePath' => $this->routePath, 
            'username' => $this->account->isLoggedIn() ? $this->account->getCurrentUsername() : ''
        ]);

        return (new Response)->html($output);
    }

    public function processCommand(): Response
    {
        $commandStructure = $this->request->json();
        $commandName = $commandStructure['command'] ?? '';
        $args = new ArgumentInterpreter($commandStructure['arguments'] ?? [])->getAll() ?? [];
        $input = $commandStructure['input'] ?? ''; // is used if we are in a CommandWizard
        $commandId = $commandStructure['commandId'] ?? uniqid();
        $interrupt = $commandStructure['interrupt'] ?? false;

        $clientId = $this->request->getClientIp(); // Get client IP address
        $key = 'cli';

        // Interrupt: ^C oder page refresh -> clean everything
        if ($interrupt || $this->request->isRefresh() || $this->request->isGet()) {
            CommandWizard::cleanupAllSessions();

            return (new Response)->json([
                'output' => '',
                'commandComplete' => true,
                'expectInput' => false,
                'queue' => false,
            ]);
        }

        $this->rateLimiter->setLimit($key, 12, 30); // 12 attempts per 30 seconds

        if ($this->rateLimiter->isLimitExceeded($key, $clientId)) {
            $remainingTime = $this->rateLimiter->getRemainingTimeToReset($key, $clientId) + 2;

            return (new Response)->json([
                'rateLimitExceeded' => true,
                'remainingTime' => $remainingTime,
                'output' => 'Rate limit exceeded. Try again in '.$remainingTime.' seconds.',
            ]);
        } else {
            $this->rateLimiter->registerAttempt($key, $clientId);
        }

        // Validate input structure
        $rules = [
            'command' => [
                new Required,
                new IsString,
                new Min(2),
                new Max(50),
            ],
            'arguments' => [
                new IsArray,
            ],
            'input' => [
                new IsString,
            ],
            'commandId' => [
                new IsString,
                new Min(1),
                new Max(100),
            ],
            'interrupt' => [
                new IsBoolean,
            ],
            'arguments.*' => [
                new Max(10),
            ],
        ];

        $validator = new Validator($commandStructure, $rules);

        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors() as $fieldErrors) {
                $errors = array_merge($errors, $fieldErrors);
            }

            return (new Response)->json((new CLIResponsePattern)->Ordinary(implode("\n", $errors)));
        }

        // Check CLI access permission
        if (!$this->account->hasPermission(Permission::CLI_ACCESS ) && $this->dotEnv->get("SYSTEM_MARKED_AS_INSTALLED")) {
            return (new Response)->json((new CLIResponsePattern)->Ordinary('Web-Terminal access denied.'));
        }

        // Try to find and execute command via registry
        $command = $this->commandRegistry->get($commandName);
        
        if ($command === null) {
            return (new Response)->json([
                'output' => 'Unknown command: '.$commandName.'. Type "help" for available commands.',
                'commandComplete' => true,
                'expectInput' => false,
            ]);
        }

        try {
            // Execute the command
            $result = $command->execute($args, $input, $commandId);
            
            // Handle terminal label changes (for logout, login etc.)
            if (isset($result['setTerminalLabel'])) {
                $result = (new CLIResponsePattern)->SetTerminalLabel($result['setTerminalLabel'], $result['output']);
            }
            
            return (new Response)->json($result);
            
        } catch (Exception $e) {
            return (new Response)->json([
                'output' => 'Error executing command: ' . $e->getMessage(),
                'outputType' => 'error',
                'commandComplete' => true,
                'expectInput' => false,
            ]);
        }
    }
}