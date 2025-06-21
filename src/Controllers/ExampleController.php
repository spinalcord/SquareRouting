<?php
namespace SquareRouting\Controllers;

use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Response; // Wichtig für den Return Type Hint

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
    
    return $this->response->json($data, 200);
  }
  
  public function showHtmlPage(): Response {
      $html = "<h1>Hallo Welt!</h1><p>Dies ist eine HTML-Seite.</p>";
      return $this->response->html($html);
  }

  public function redirectToGoogle(): Response {
      return $this->response->reroute('https://www.google.com');
  }
}
