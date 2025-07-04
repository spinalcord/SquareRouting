<?php

namespace SquareRouting\Core;

class JsonResponsePattern
{
    public function success(mixed $body = null, string $message = ''): array
    {
        $response = ['success' => true];
        
        if ($body !== null) {
            $response['body'] = $body;
        }
        
        if (!empty($message)) {
            $response['message'] = $message;
        }
        
        return $response;
    }

    public function error(string $code, string $message, mixed $details = null): array
    {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];
        
        if ($details !== null) {
            $response['error']['details'] = $details;
        }
        
        return $response;
    }
}
