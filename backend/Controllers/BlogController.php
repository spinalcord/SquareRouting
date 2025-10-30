<?php

namespace SquareRouting\Controllers;

use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\DotEnv;
use SquareRouting\Core\Response;
use SquareRouting\Core\View;

class BlogController
{
    public View $view;

    public function __construct(DependencyContainer $container)
    {
        $this->view = $container->get(View::class);
    }

    public function showBlog(): Response
    {
        $data = [];

        $this->view->setMultiple($data);
        $output = $this->view->render('blog.tpl');

        return (new Response)->html($output);
    }
}
