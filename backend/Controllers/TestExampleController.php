<?php

namespace SquareRouting\Controllers;

use SquareRouting\Core\Response;

class TestExampleController
{
    public function someTest(int $mynum): Response
    {
        $data = [
            'status' => 'success',
            'received_number' => $mynum,
            'message' => 'This is a proper JSON response!',
        ];

        return (new Response)->json($data, 200);
    }
}