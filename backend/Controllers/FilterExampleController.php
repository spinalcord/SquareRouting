<?php

namespace SquareRouting\Controllers;

use SquareRouting\Core\Response;

class FilterExampleController
{
    public function filterTest(): Response
    {
        return (new Response)->html(' Filter Test ');
    }
}