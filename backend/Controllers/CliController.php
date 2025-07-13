<?php

namespace SquareRouting\Controllers;

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
use SquareRouting\Core\Schema;
use SquareRouting\Core\Schema\Permission;
use SquareRouting\Core\SchemaGenerator;
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
        $output = $this->view->render('cli.tpl', ['routePath' => $this->routePath]);

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

        if ($commandName == 'install') {
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
                            $this->account->register($data['email'], $data['password'], ['username' => $data['username']] );
                            $this->account->login($data['email'], $data['password']);

                            return ['config' => 'loaded'];
                        },

                        function ($input, $data) {
                            return (new StepFeedback)->success("✅ Registrierung erfolgreich abgeschlossen! ");
                        }
                    );
                $result = $wizard->process($input, $commandId);

                return (new Response)->json($result);
            }
        }

        if ($commandName == 'generate') {

            if ($args['enums'] == 'database') {
                $schema = new Schema;
                $schemeaGenerator = new SchemaGenerator($schema, outputDir: $this->container->get('schema_enums_location'));
                $schemeaGenerator->generateTableNames();
                $schemeaGenerator->generateColumnNames();

                return (new Response)->json((new CLIResponsePattern)->Ordinary('Database enums created successfully'));
            }

            $this->account->initializeDefaultRoles();

            $this->account->register($args['name'], $args['password'], ['username' => 'test'.uniqid()]);
            $this->account->login($args['name'], $args['password']);

            return (new Response)->json((new CLIResponsePattern)->Ordinary(''));
        }

        /* if ($commandName == 'permtest') { */
        /**/
        /*     if ($this->account->hasPermission(Permission::CONTENT_CREATE)) { */
        /*         return (new Response)->json((new CLIResponsePattern)->Ordinary('you are allowed to read')); */
        /*     } else { */
        /*         return (new Response)->json((new CLIResponsePattern)->Ordinary('you are not allowed to read')); */
        /*     } */
        /* } */

        if ($commandName == 'version') {
            return (new Response)->json((new CLIResponsePattern)->Ordinary('
███████╗ ██████╗    ██████╗  ██████╗ ██╗   ██╗████████╗
██╔════╝██╔═══██╗   ██╔══██╗██╔═══██╗██║   ██║╚══██╔══╝
███████╗██║   ██║   ██████╔╝██║   ██║██║   ██║   ██║   
╚════██║██║▄▄ ██║   ██╔══██╗██║   ██║██║   ██║   ██║   
███████║╚██████╔╝██╗██║  ██║╚██████╔╝╚██████╔╝   ██║██╗
╚══════╝ ╚══▀▀═╝ ╚═╝╚═╝  ╚═╝ ╚═════╝  ╚═════╝    ╚═╝╚═╝
Web-Terminal Version 1.0'));
        }

        if ($commandName == 'register') {
            $wizard = new CommandWizard('register_wizard');

            $wizard
                ->addStep(
                    'Wie ist dein Name?',
                    // Validator mit verschiedenen outputTypes
                    function ($input, $data) {
                        $name = strtolower(trim($input));
                        if ($name === 'admin') {
                            return (new StepFeedback)->error('Admin-Namen sind nicht erlaubt. Registrierung abgebrochen.');
                        }
                        if ($name === 'foobar') {
                            return (new StepFeedback)->warning("Der Name 'foobar' ist nicht erlaubt. Bitte gib einen anderen Namen ein:");
                        }
                        if (strlen($name) < 2) {
                            return (new StepFeedback)->warning('Name muss mindestens 2 Zeichen lang sein:');
                        }

                        return true;
                    },
                    // Processor
                    function ($input, $data) {
                        return ['name' => trim($input)];
                    }
                )
                ->addStep(
                    // Dynamische Frage basierend auf vorherigen Daten
                    function ($data) {
                        $name = $data['name'] ?? '';

                        return "Hallo {$name}! Wie ist deine E-Mail-Adresse?";
                    },
                    // Email Validator
                    function ($input, $data) {
                        if (! filter_var($input, FILTER_VALIDATE_EMAIL)) {
                            return (new StepFeedback)->warning('Bitte gib eine gültige E-Mail-Adresse ein:');
                        }

                        return true;
                    },
                    // Email Processor - terminiert den Wizard
                    function ($input, $data) {
                        $name = $data['name'];
                        $email = $input;

                        // Hier kannst du die Registrierung durchführen
                        // z.B. in Datenbank speichern
                        return (new StepFeedback)->success("✅ Registrierung erfolgreich abgeschlossen! Name: {$name}, E-Mail: {$email}");
                    }
                )
                ->addStep(
                    // Dynamische Frage basierend auf vorherigen Daten
                    function ($data) {
                        $name = $data['name'] ?? 'dort';

                        return "Hallo {$name}! Wie ist deine E-Mail-Adresse?";
                    },
                    // Email Validator
                    function ($input, $data) {
                        if (! filter_var($input, FILTER_VALIDATE_EMAIL)) {
                            return (new StepFeedback)->warning('Bitte gib eine gültige E-Mail-Adresse ein:');
                        }

                        return true;
                    },
                    // Email Processor - terminiert den Wizard
                    function ($input, $data) {
                        $name = $data['name'];
                        $email = $input;

                        // Hier kannst du die Registrierung durchführen
                        // z.B. in Datenbank speichern
                        return (new StepFeedback)->success("✅ Registrierung erfolgreich abgeschlossen! Name: {$name}, E-Mail: {$email}");
                    }
                );

            $result = $wizard->process($input, $commandId);

            return (new Response)->json($result);
        }

        // Weiteres Beispiel: Einfacher Login-Wizard
        if ($commandName == 'login') {
            $wizard = new CommandWizard('login_wizard');

            $wizard
                ->addStep('Username:', null, fn ($input) => ['username' => $input])
                ->addStep('Password:', null, function ($input, $data) {
                    // Hier würdest du normalerweise das Login prüfen
                    if ($data['username'] === 'admin' && $input === 'secret') {
                        return (new StepFeedback)->success('🎉 Login erfolgreich! Willkommen zurück!');
                    } else {
                        return (new StepFeedback)->error('❌ Login fehlgeschlagen! Benutzername oder Passwort falsch.');
                    }
                });

            $result = $wizard->process($input, $commandId);

            return (new Response)->json($result);
        }

        // Beispiel: Wizard mit Queue Steps (automatische Schritte)
        if ($commandName == 'deploy') {
            $wizard = new CommandWizard('deploy_wizard');

            $wizard
                // Normal Step - wartet auf Input
                ->addStep('Projekt-Name für Deployment:', null, fn ($input) => ['project' => $input])

                // Queue Step - automatisch
                ->addQueueStep(
                    function ($data) {
                        return "🔄 Lade Konfiguration für {$data['project']}...";
                    },
                    function ($input, $data) {
                        // Hier würde man die Konfiguration laden
                        sleep(4); // Simuliere Ladezeit

                        return ['config' => 'loaded'];
                    }
                )

                // Normal Step - wartet auf Input
                ->addStep(
                    '✅ Konfiguration geladen! Möchtest du fortfahren? (ja/nein)',
                    function ($input) {
                        if (! in_array(strtolower($input), ['ja', 'nein', 'yes', 'no'])) {
                            return (new StepFeedback)->warning("Bitte 'ja' oder 'nein' eingeben:");
                        }
                        if (in_array(strtolower($input), ['nein', 'no'])) {
                            return (new StepFeedback)->error('Deployment abgebrochen.');
                        }

                        return true;
                    },
                    fn ($input) => ['confirmed' => true]
                )

                // Queue Step - automatisch
                ->addQueueStep(
                    '🚀 Starte Deployment...',
                    function ($input, $data) {
                        sleep(4); // Simuliere Ladezeit

                        // Hier würde das eigentliche Deployment stattfinden
                        return (new StepFeedback)->success("🎉 Deployment erfolgreich! Projekt: {$data['project']}");
                    }
                );

            $result = $wizard->process($input, $commandId);

            return (new Response)->json($result);
        }

        // Einfacheres Beispiel - auch mit addQueueStep
        if ($commandName == 'test') {
            $wizard = new CommandWizard('test_wizard');

            $wizard
                ->addStep('Name eingeben:', null, fn ($input) => ['name' => $input])           // Normal
                ->addQueueStep('🔄 Verarbeite...', fn () => ['processed' => true])            // Queue
                ->addQueueStep(                                                               // Queue + Ende
                    function ($data) {
                        return "✅ Hallo {$data['name']}! Verarbeitung abgeschlossen.";
                    },
                    fn () => (new StepFeedback)->success('Alles erledigt!')
                );

            $result = $wizard->process($input, $commandId);

            return (new Response)->json($result);
        }

        return (new Response)->json([
            'output' => 'Unknown command: '.$commandName,
            'commandComplete' => true,
            'expectInput' => false,
        ]);
    }
}
