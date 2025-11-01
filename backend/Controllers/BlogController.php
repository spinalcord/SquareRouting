<?php

namespace SquareRouting\Controllers;

use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\DotEnv;
use SquareRouting\Core\Response;
use SquareRouting\Core\View;

class BlogController
{
    public View $view;
    public string $blogLocation;
 
    public function __construct(DependencyContainer $container)
    {
        $this->view = $container->get(View::class);
        $this->blogLocation = $container->get('blog_location');
    }
     
   
    public function showBlog(string $blogPath): Response
    {
          
        $target_file = $this->blogLocation . $blogPath . '.md'; 

        if(file_exists($target_file))
        {
          $blogContent = file_get_contents($target_file);

          $blogContentJs = str_replace('`', '\\`', $blogContent);
          $blogContentJs = str_replace('${', '\\${', $blogContentJs);

          $data = [
              'blogContentJs' => $blogContentJs,
          ];

          $this->view->setMultiple($data);
          $output = $this->view->render('blog.tpl');
          return (new Response)->html($output);
        }
        else{

          return (new Response)->text("404");
        }
    }
}
