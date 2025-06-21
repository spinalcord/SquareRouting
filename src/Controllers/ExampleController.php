<?php
namespace SquareRouting\Controllers;

use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Response; // Important for the Return Type Hint

class ExampleController  {
  public Response $response;

  public function __construct(DependencyContainer $container) {
   $this->response = $container->get(Response::class);
  } 

  public function someTest(int $num): Response {
    $data = [
        'status' => 'success',
        'received_number' => $num,
        'message' => 'This is a proper JSON response!'
    ];
    
    return (new Response)->json($data, 200);
  }
  
  public function showHtmlPage(): Response {
      $html = "<h1>Hello World!</h1><p>This is an HTML page.</p>";
      return (new Response)->html($html);
  }

  public function redirectToGoogle(): Response {
      return (new Response)->reroute('https://www.google.com');
  }
}
