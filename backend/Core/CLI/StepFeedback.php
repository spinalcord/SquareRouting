<?php

namespace SquareRouting\Core\CLI;

class StepFeedback
{
    public function error(string $message, bool $terminate = true): array
    {
        return [
            'terminate' => $terminate,
            'message' => $message,
            'type' => "error",
        ];
    }

    public function success(string $message, bool $terminate = true): array
    {
        return [
            'terminate' => $terminate,
            'message' => $message,
            'type' => "success",
        ];
    }
    public function warning(string $message, bool $terminate = false): array
    {
        return [
            'terminate' => $terminate,
            'message' => $message,
            'type' => "warning",
        ];
    }
    public function info(string $message, bool $terminate = false): array
    {
        return [
            'terminate' => $terminate,
            'message' => $message,
            'type' => "info",
        ];
    }
}
