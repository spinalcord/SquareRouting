<?php

namespace SquareRouting\Controllers;

use SquareRouting\Core\Response;

class HomeExampleController
{
    public function home(): Response
    {
        return (new Response)->html('hello');
    }
}
