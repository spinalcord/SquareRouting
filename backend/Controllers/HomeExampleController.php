<?php

namespace SquareRouting\Controllers;

use SquareRouting\Core\Response;
use SquareRouting\Core\Request;
use SquareRouting\Core\DependencyContainer;

class HomeExampleController
{
    private Request $request;
    private DependencyContainer $container;

    public function __construct(DependencyContainer $container)
    {
      $this->container = $container->get(DependencyContainer::class);
      $this->request = $container->get(Request::class);
    }
    public function home(): Response
    {
      return (new Response)->html('hello');
    }

    // /cmd
    public function cmdExample(): Response
    {
      $this->request = $this->container->get(Request::class);
      if($this->request->json("command") === "version")
      {
        return new Response()->html("1.0");
      }
      else {
        return new Response()->html("No command: ". $this->request->json("command"));
       
      }
    }
}

