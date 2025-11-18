<?php

namespace SquareRouting\Controllers;

use SquareRouting\Core\DependencyContainer;
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
        $this->view->set('blogPath', $blogPath);
        $output = $this->view->render('blog.tpl');
        return (new Response)->html($output);
    }



    public function getBlogContent(string $blogPath): Response
    {
        $target_file = $this->blogLocation . $blogPath . '.md';

        if(file_exists($target_file))
        {
          $blogContent = file_get_contents($target_file);
          return (new Response)->text($blogContent);
        }
        else{
          return (new Response)->json([
              'error' => 'Blog post not found',
              'success' => false
          ], 404);
        }
    }
}
