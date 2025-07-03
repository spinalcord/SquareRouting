<?php

namespace SquareRouting\Controllers;

use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\DotEnv;
use SquareRouting\Core\Response;

class EnvExampleController
{
    public DotEnv $dotEnv;

    public function __construct(DependencyContainer $container)
    {
        $this->dotEnv = $container->get(DotEnv::class);
    }

    public function envExample(): Response
    {
        $testValue = $this->dotEnv->get('TESTVALUE');

        return (new Response)->html('The .env value is: ' . $testValue);
    }
}
