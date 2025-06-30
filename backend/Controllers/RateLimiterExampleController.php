<?php

namespace SquareRouting\Controllers;

use SquareRouting\Core\RateLimiter;
use SquareRouting\Core\Request;
use SquareRouting\Core\Response;
use SquareRouting\Core\DependencyContainer;

class RateLimiterExampleController
{
    public Request $request;
    public RateLimiter $rateLimiter;

    public function __construct(DependencyContainer $container)
    {
        $this->request = $container->get(Request::class);
        $this->rateLimiter = $container->get(RateLimiter::class);
    }

    public function rateLimiterExample(): Response
    {
        $clientId = $this->request->getClientIp(); // Get client IP address
        $key = 'api_access'; // Define a key for the rate limit

        $this->rateLimiter->setLimit($key, 5, 60); // 5 attempts per 60 seconds

        if ($this->rateLimiter->isLimitExceeded($key, $clientId)) {
            $remainingTime = $this->rateLimiter->getRemainingTimeToReset($key, $clientId);

            return (new Response)->json(['status' => 'error', 'message' => 'Rate limit exceeded. Try again in ' . $remainingTime . ' seconds.'], 429);
        }

        $this->rateLimiter->registerAttempt($key, $clientId);
        $remainingAttempts = $this->rateLimiter->getRemainingAttempts($key, $clientId);

        return (new Response)->json(['status' => 'success', 'message' => 'API access granted.', 'remaining_attempts' => $remainingAttempts], 200);
    }
}