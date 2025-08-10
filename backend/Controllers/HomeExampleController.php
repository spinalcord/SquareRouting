<?php

namespace SquareRouting\Controllers;

use SquareRouting\Core\Response;
use SquareRouting\Core\Request;
use SquareRouting\Core\DependencyContainer;

class HomeExampleController
{
    private Request $request;
    public function __construct(DependencyContainer $container)
    {
      $this->request = $container->get(Request::class);
    }
    public function home(): Response
    {
      return (new Response)->html('hello');
    }

    // /cmd
    public function cmdExample(): Response
    {
      $cmdName = $this->request->get("cmd");
      if($cmdName === "version")
      {
        return (new Response)->html('1.0');
      }
    }
}

