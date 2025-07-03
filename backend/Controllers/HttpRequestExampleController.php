<?php

namespace SquareRouting\Controllers;

use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Request;
use SquareRouting\Core\Response;

class HttpRequestExampleController
{
    public Request $request;

    public function __construct(DependencyContainer $container)
    {
        $this->request = $container->get(Request::class);
    }

    public function handlePutRequest(int $id): Response
    {
        $data = $this->request->json();
        $response = new Response;

        return $response->json(['message' => "Put request received for ID: {$id}", 'data' => $data]);
    }

    public function handleDeleteRequest(int $id): Response
    {
        $response = new Response;

        return $response->json(['message' => "Delete request received for ID: {$id}"]);
    }

    public function handlePatchRequest(int $id): Response
    {
        $data = $this->request->json();
        $response = new Response;

        return $response->json(['message' => "Patch request received for ID: {$id}", 'data' => $data]);
    }

    public function redirectToGoogle(): Response
    {
        return (new Response)->redirect('https://www.google.com');
    }
}
