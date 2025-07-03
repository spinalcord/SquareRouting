<?php

namespace SquareRouting\Controllers;

use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Request;
use SquareRouting\Core\Response;

class HtmlPageExampleController
{
    public Request $request;

    public function __construct(DependencyContainer $container)
    {
        $this->request = $container->get(Request::class);
    }

    public function showHtmlPage(): Response
    {
        $html = '<h1>Hello World!</h1><p>This is an HTML page.</p>
               <form action="/post-example" method="POST">
                   <button type="submit">Send POST Request</button>
               </form>';

        return (new Response)->html($html);
    }

    public function handlePostRequest(): Response
    {
        $data = $this->request->post();

        return (new Response)->json(['status' => 'success', 'message' => 'POST request received!', 'data' => $data], 200);
    }
}
