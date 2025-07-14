<?php

namespace SquareRouting\Core\CLI;

class StepFeedback
{
    public function error(string $message, bool $terminate = false, array $additionalData = []): array
    {
        return array_merge([
            'terminate' => $terminate,
            'message' => $message,
            'type' => "error",
        ], $additionalData);
    }

    public function success(string $message, bool $terminate = false, array $additionalData = []): array
    {
        return array_merge([
            'terminate' => $terminate,
            'message' => $message,
            'type' => "success",
        ], $additionalData);
    }
    
    public function warning(string $message, bool $terminate = false, array $additionalData = []): array
    {
        return array_merge([
            'terminate' => $terminate,
            'message' => $message,
            'type' => "warning",
        ], $additionalData);
    }
    
    public function info(string $message, bool $terminate = false, array $additionalData = []): array
    {
        return array_merge([
            'terminate' => $terminate,
            'message' => $message,
            'type' => "info",
        ], $additionalData);
    }
}