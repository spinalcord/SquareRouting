<?php

namespace SquareRouting\Core\CLI;

class CLIResponsePattern
{
    public function WizardStep(string $message, bool $messageType = false, bool $terminate = true): array
    {
        return [
            'terminate' => $terminate,
            'message' => $message,
            'type' => $messageType,
        ];
    }

    public function Ordinary(string $output, bool $commandComplete = true, bool $expectInput = false, bool $queue = false): array
    {
        return [
            'output' => $output,
            'commandComplete' => $commandComplete,
            'expectInput' => $expectInput,
            // 'queue' => $queue,
        ];
    }
}
