<?php

namespace SquareRouting\Controllers;

use Exception;
use InvalidArgumentException;
use RuntimeException;
use SquareRouting\Core\Account;
use SquareRouting\Core\CLI\ArgumentInterpreter;
use SquareRouting\Core\CLI\CLIResponsePattern;
use SquareRouting\Core\CLI\CommandWizard;
use SquareRouting\Core\CLI\StepFeedback;
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

    public function __construct(DependencyContainer $container)
    {
        $this->request = $container->get(Request::class);
        $this->view = $container->get(View::class);
        $this->dotEnv = $container->get(DotEnv::class);
        $this->rateLimiter = $container->get(RateLimiter::class);
        $this->account = $container->get(Account::class);
        $this->container = $container;
        $this->routePath = $container->get('routePath');
    }

    public function showTerminal(): Response
    {

        $output = $this->view->render('cli.tpl', ['routePath' => $this->routePath, 'username' => $this->account->isLoggedIn() ? $this->account->getCurrentUsername() : '']);

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

        $this->rateLimiter->setLimit($key, 12, 30); // 5 attempts per 60 seconds

        if ($this->rateLimiter->isLimitExceeded($key, $clientId)) {

            $remainingTime = $this->rateLimiter->getRemainingTimeToReset($key, $clientId) + 2;

            return (new Response)->json(
                [
                    'rateLimitExceeded' => true,
                    'remainingTime' => $remainingTime,
                    'output' => 'Rate limit exceeded. Try again in '.$remainingTime.' seconds.',
                ]);

        } else {
            $this->rateLimiter->registerAttempt($key, $clientId);
        }

        $rules = [
            // Command ist erforderlich und muss bestimmte Kriterien erfüllen
            'command' => [
                new Required,
                new IsString,
                new Min(2),
                new Max(50), // Verhindere übermäßig lange Commands
            ],

            // Arguments muss ein Array sein (kann leer sein)
            'arguments' => [
                new IsArray,
            ],

            // Input ist optional aber muss String sein wenn vorhanden
            'input' => [
                new IsString,
            ],

            // CommandId muss vorhanden sein und bestimmte Kriterien erfüllen
            'commandId' => [
                new IsString,
                new Min(1),
                new Max(100), // Verhindere übermäßig lange IDs
            ],

            // Interrupt muss boolean sein wenn vorhanden
            'interrupt' => [
                new IsBoolean,
            ],

            // Zusätzliche Validierung für arguments Array-Elemente
            'arguments.*' => [
                new Max(10), // Verhindere übermäßig lange Argumente
            ],
        ];

        $validator = new Validator($commandStructure, $rules);

        if ($validator->fails()) {
            // Fehler extrahieren und in einen flachen Array umwandeln
            $errors = [];
            foreach ($validator->errors() as $fieldErrors) {
                $errors = array_merge($errors, $fieldErrors);
            }

            // Fehler in einen String umwandeln
            return (new Response)->json((new CLIResponsePattern)->Ordinary(implode("\n", $errors)));
        }

        if(!$this->account->hasPermission( Permission::CLI_ACCESS)) {
            return (new Response)->json((new CLIResponsePattern)->Ordinary('Web-Terminal access denied.'));
        }
        if ($commandName == 'version') {
            return (new Response)->json((new CLIResponsePattern)->Ordinary('
███████╗ ██████╗    ██████╗  ██████╗ ██╗   ██╗████████╗
██╔════╝██╔═══██╗   ██╔══██╗██╔═══██╗██║   ██║╚══██╔══╝
███████╗██║   ██║   ██████╔╝██║   ██║██║   ██║   ██║   
╚════██║██║▄▄ ██║   ██╔══██╗██║   ██║██║   ██║   ██║   
███████║╚██████╔╝██╗██║  ██║╚██████╔╝╚██████╔╝   ██║██╗
╚══════╝ ╚══▀▀═╝ ╚═╝╚═╝  ╚═╝ ╚═════╝  ╚═════╝    ╚═╝╚═╝
Web-Terminal Version 1.0'));
        } elseif ($commandName == 'install') {
            $isInstallationPossible = ! $this->dotEnv->get('SYSTEM_MARKED_AS_INSTALLED');
            if ($isInstallationPossible) {
                $wizard = new CommandWizard('create_admin_account');
                $wizard
                    ->addStep(
                        'Enter your emailaddress:',
                        // Validator mit verschiedenen outputTypes
                        function ($input, $data) {
                            $stepValidation = new Validator(['email' => $input], ['email' => [new Email]]);
                            if ($stepValidation->fails()) {
                                $errors = [];
                                foreach ($stepValidation->errors() as $fieldErrors) {
                                    $errors = array_merge($errors, $fieldErrors);
                                }
                                sleep(3);

                                return (new StepFeedback)->warning(implode("\n", $errors));
                            } else {
                                return true;
                            }
                        },
                        // Processor
                        function ($input, $data) {
                            return ['email' => trim($input)];
                        }
                    )
                    ->addStep(
                        // Dynamische Frage basierend auf vorherigen Daten
                        'Enter your username:',
                        function ($input, $data) {
                            $stepValidation = new Validator(['username' => $input], ['username' => [new AlphaNumeric, new Min(2), new Max(30)]]);
                            if ($stepValidation->fails()) {

                                $errors = [];
                                foreach ($stepValidation->errors() as $fieldErrors) {
                                    $errors = array_merge($errors, $fieldErrors);
                                }

                                return (new StepFeedback)->warning(implode("\n", $errors));
                            } else {
                                return true;
                            }
                        },
                        // Email Processor - terminiert den Wizard
                        function ($input, $data) {
                            return ['username' => trim($input)];
                        }
                    )
                    ->addStep(
                        // Dynamische Frage basierend auf vorherigen Daten
                        'Enter your password:',
                        function ($input, $data) {
                            $stepValidation = new Validator(['password' => $input], ['password' => [new Password]]);
                            if ($stepValidation->fails()) {

                                $errors = [];
                                foreach ($stepValidation->errors() as $fieldErrors) {
                                    $errors = array_merge($errors, $fieldErrors);
                                }

                                return (new StepFeedback)->warning(implode("\n", $errors));
                            } else {
                                return true;
                            }
                        },
                        // Email Processor - terminiert den Wizard
                        function ($input, $data) {
                            return ['password' => trim($input)];
                        }
                    )
                    ->addQueueStep(
                        function ($data) {
                            return 'Erstelle Administrator...';
                        },
                        function ($input, $data) {
                            $this->account->register($data['email'], $data['password'], ['username' => $data['username']]);
                            $this->account->assignRole($this->account->getCurrentUserId(), Role::ADMIN->value);

                            return ['config' => 'loaded'];
                        },

                        function ($input, $data) {
                            return (new StepFeedback)->success('Registrierung erfolgreich abgeschlossen! Logge dich ein mit login');
                        }
                    );
                $result = $wizard->process($input, $commandId);

                return (new Response)->json($result);
            }
        } elseif ($commandName == 'logout') {
 $this->account->logout();       
            return (new Response)->json((new CLIResponsePattern)->SetTerminalLabel('','logged out'));
        } elseif ($commandName == 'login') {
            if ($this->account->isLoggedIn()) {
                return (new Response)->json((new CLIResponsePattern)->Ordinary('you are already logged in.'));
            }

            $wizard = new CommandWizard('user_login');
            $wizard
                ->addStep(
                    'Enter your email:',
                    // Validator
                    function ($input, $data) {
                        $stepValidation = new Validator(['email' => $input], ['email' => [new Email]]);
                        if ($stepValidation->fails()) {
                            $errors = [];
                            foreach ($stepValidation->errors() as $fieldErrors) {
                                $errors = array_merge($errors, $fieldErrors);
                            }

                            return (new StepFeedback)->warning(implode("\n", $errors));
                        } else {
                            return true;
                        }
                    },
                    // Processor
                    function ($input, $data) {
                        return ['email' => trim($input)];
                    }
                )
                ->addStep(
                    'Enter your password:',
                    // Validator
                    function ($input, $data) {
                        $stepValidation = new Validator(['password' => $input], ['password' => [new Required, new IsString, new Min(1)]]);
                        if ($stepValidation->fails()) {
                            $errors = [];
                            foreach ($stepValidation->errors() as $fieldErrors) {
                                $errors = array_merge($errors, $fieldErrors);
                            }

                            return (new StepFeedback)->warning(implode("\n", $errors));
                        } else {
                            return true;
                        }
                    },
                    // Processor
                    function ($input, $data) {
                        return ['password' => trim($input)];
                    }
                )
                ->addQueueStep(
                    function ($data) {
                        return 'Logging in...';
                    },
                    function ($input, $data) {
                        try {
                            $this->account->login($data['email'], $data['password']);

                            return ['terminate' => true, 'label' => $this->account->getCurrentUsername(), 'message' => 'Login successful! Welcome back.', 'type' => 'success'];
                        } catch (InvalidArgumentException $e) {
                            return ['terminate' => true, 'message' => $e->getMessage(), 'type' => 'error'];
                        } catch (RuntimeException $e) {
                            return ['terminate' => true, 'message' => $e->getMessage(), 'type' => 'error'];
                        } catch (Exception $e) {
                            return ['terminate' => true, 'message' => 'An unexpected error occurred during login.', 'type' => 'error'];
                        }
                    }
                );

            $result = $wizard->process($input, $commandId);

            return (new Response)->json($result);
        }
        // $this->account->login(email, password);

        return (new Response)->json([
            'output' => 'Unknown command: '.$commandName,
            'commandComplete' => true,
            'expectInput' => false,
        ]);

    }
}
