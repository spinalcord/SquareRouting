<?php

namespace SquareRouting\Core\CLI;

class CommandWizard
{
    private array $steps = [];

    private string $sessionKey;

    private static string $sessionPrefix = 'cli_wizard_';

    public function __construct(string $sessionKey)
    {
        $this->sessionKey = self::$sessionPrefix.$sessionKey;
    }

    // Statische Methode zum Bereinigen aller CLI-Sessions
    public static function cleanupAllSessions(): void
    {
        // Session prüfen und ggf. starten
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Sicherstellen, dass $_SESSION verfügbar ist
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            return;
        }

        $keysToRemove = [];

        // Finde alle Session-Keys die mit unserem Präfix beginnen
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, self::$sessionPrefix) === 0) {
                $keysToRemove[] = $key;
            }
        }

        // Lösche alle gefundenen CLI-Session-Keys
        foreach ($keysToRemove as $key) {
            unset($_SESSION[$key]);
        }

        // Optional: Session explizit schreiben
        if (!empty($keysToRemove)) {
            session_write_close();
            session_start(); // Neu starten für weitere Verwendung
        }
    }

    // Convenience-Methode für einzelne Session
    public function cleanup(): void
    {
        unset($_SESSION[$this->sessionKey]);
    }

    public function addStep($question, ?callable $validator = null, ?callable $processor = null, bool $isQueue = false): self
    {
        $this->steps[] = [
            'question' => $question, // kann string oder callable sein
            'validator' => $validator,
            'processor' => $processor,
            'isQueue' => $isQueue,  // true = automatischer Step ohne User-Input, false = wartet auf Input
        ];

        return $this;
    }

    // Convenience-Methode für Queue Steps
    public function addQueueStep($message, ?callable $processor = null): self
    {
        return $this->addStep($message, null, $processor, true);
    }

    public function process(string $input, string $commandId): array
    {
        // Prüfe ob sich die commandId geändert hat
        $sessionData = $_SESSION[$this->sessionKey] ?? null;
        $existingCommandId = $sessionData['commandId'] ?? null;
        
        // Wenn neue commandId, bereinige alte Session
        if ($existingCommandId && $existingCommandId !== $commandId) {
            $this->cleanup();
            $sessionData = null;
        }

        $currentStep = $sessionData['step'] ?? 0;
        $data = $sessionData['data'] ?? [];

        // Erster Aufruf - erste Frage stellen
        if ($currentStep === 0 && empty($input)) {
            $_SESSION[$this->sessionKey] = [
                'step' => 0, 
                'data' => [], 
                'commandId' => $commandId
            ];

            return $this->askQuestion(0, $commandId);
        }

        $step = $this->steps[$currentStep];

        // Queue Step: Automatische Verarbeitung ohne Input-Validation
        if ($step['isQueue'] && empty($input)) {
            // Queue Steps haben normalerweise keinen Validator, aber falls doch...
            if ($step['validator']) {
                $validationResult = ($step['validator'])('', $data);
                if ($validationResult !== true) {
                    return $this->handleValidationError($validationResult, $commandId);
                }
            }

            // Queue Step verarbeiten
            if ($step['processor']) {
                $processorResult = ($step['processor'])('', $data);
                if (is_array($processorResult) && isset($processorResult['terminate'])) {
                    $this->cleanup();

                    return [
                        'commandId' => $commandId,
                        'output' => $processorResult['message'],
                        'outputType' => $processorResult['type'] ?? 'success',
                        'commandComplete' => true,
                        'expectInput' => false,
                        'queue' => false,
                    ];
                }
                $data = array_merge($data, $processorResult ?? []);
            }

            // Zum nächsten Schritt
            $currentStep++;
            $_SESSION[$this->sessionKey]['step'] = $currentStep;
            $_SESSION[$this->sessionKey]['data'] = $data;

            // Prüfen ob weitere Schritte vorhanden
            if ($currentStep >= count($this->steps)) {
                $this->cleanup();

                return [
                    'commandId' => $commandId,
                    'output' => 'Prozess abgeschlossen!',
                    'outputType' => 'success',
                    'commandComplete' => true,
                    'expectInput' => false,
                    'queue' => false,
                ];
            }

            return $this->askQuestion($currentStep, $commandId);
        }

        // Normale Input-Validation für Input-Steps
        if (! $step['isQueue'] && $step['validator']) {
            $validationResult = ($step['validator'])($input, $data);

            // Validator kann den Wizard terminieren
            if (is_array($validationResult) && ($validationResult['terminate'] ?? false)) {
                $this->cleanup();

                return [
                    'commandId' => $commandId,
                    'output' => $validationResult['message'],
                    'outputType' => $validationResult['type'] ?? 'error',
                    'commandComplete' => true,
                    'expectInput' => false,
                    'queue' => false,
                ];
            }

            // Validation Error - Frage wiederholen
            if ($validationResult !== true) {
                return $this->handleValidationError($validationResult, $commandId);
            }
        }

        // Input verarbeiten
        if ($step['processor']) {
            $processorResult = ($step['processor'])($input, $data);
            if (is_array($processorResult) && isset($processorResult['terminate'])) {
                $this->cleanup();

                return [
                    'commandId' => $commandId,
                    'output' => $processorResult['message'],
                    'outputType' => $processorResult['type'] ?? 'success',
                    'commandComplete' => true,
                    'expectInput' => false,
                    'queue' => false,
                ];
            }
            $data = array_merge($data, $processorResult ?? []);
        } else {
            $data[] = $input;
        }

        // Zum nächsten Schritt
        $currentStep++;
        $_SESSION[$this->sessionKey]['step'] = $currentStep;
        $_SESSION[$this->sessionKey]['data'] = $data;

        // Prüfen ob weitere Schritte vorhanden
        if ($currentStep >= count($this->steps)) {
            $this->cleanup();

            return [
                'commandId' => $commandId,
                'output' => 'Prozess abgeschlossen!',
                'outputType' => 'success',
                'commandComplete' => true,
                'expectInput' => false,
                'queue' => false,
            ];
        }

        return $this->askQuestion($currentStep, $commandId);
    }

    private function handleValidationError($validationResult, string $commandId): array
    {
        $outputType = 'warning';
        $message = $validationResult;

        // Erweiterte Validation-Response mit outputType
        if (is_array($validationResult)) {
            $message = $validationResult['message'] ?? $validationResult['output'] ?? 'Fehler';
            $outputType = $validationResult['type'] ?? 'warning';
        }

        return [
            'commandId' => $commandId,
            'output' => $message,
            'outputType' => $outputType,
            'commandComplete' => false,
            'expectInput' => true,
            'queue' => false,
        ];
    }

    private function askQuestion(int $stepIndex, string $commandId): array
    {
        $step = $this->steps[$stepIndex];
        $question = $step['question'];

        // Dynamische Frage basierend auf vorherigen Daten
        if (is_callable($question)) {
            $data = $_SESSION[$this->sessionKey]['data'] ?? [];
            $question = $question($data);
        }

        // Queue Step: Automatische Ausführung ohne User-Input
        if ($step['isQueue']) {
            return [
                'commandId' => $commandId,
                'output' => $question,
                'outputType' => 'info',
                'commandComplete' => false,
                'expectInput' => false,
                'queue' => true,
            ];
        }

        // Normaler Input Step
        return [
            'commandId' => $commandId,
            'output' => $question,
            'outputType' => 'info',
            'commandComplete' => false,
            'expectInput' => true,
            'queue' => false,
        ];
    }
}
