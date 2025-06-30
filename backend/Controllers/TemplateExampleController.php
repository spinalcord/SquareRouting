<?php

namespace SquareRouting\Controllers;

use SquareRouting\Core\Response;
use SquareRouting\Core\View;
use SquareRouting\Core\DependencyContainer;

class TemplateExampleController
{
    public View $view;

    public function __construct(DependencyContainer $container)
    {
        $this->view = $container->get(View::class);
    }

    public function templateExample(): Response
    {
        $data = [
            'pageTitle' => 'Template Engine Example',
            'greeting' => 'Hello',
            'userName' => 'World',
            'currentYear' => date('Y'),
            'currentTime' => date('H:i:s'),
            'features' => [
                ['name' => 'Variables', 'description' => 'Dynamic content display'],
                ['name' => 'Loops', 'description' => 'Iterating over data collections'],
                ['name' => 'Conditionals', 'description' => 'Displaying content based on logic'],
                ['name' => 'Includes', 'description' => 'Reusing template partials'],
                ['name' => 'Translations', 'description' => 'Multilingual support'],
                ['name' => 'Events', 'description' => 'Injecting dynamic content via callbacks'],
                ['name' => 'Caching', 'description' => 'Improved performance'],
                ['name' => 'Auto-escaping', 'description' => 'XSS protection by default'],
            ],
            'isAdmin' => true,
            'showExtraContent' => true,
            'rawHtml' => '<strong>This is raw HTML!</strong> <script>alert("Test XSS attempt!");</script>',
        ];

        $this->view->setMultiple($data);
        $output = $this->view->render('demo.tpl');

        return (new Response)->html($output);
    }
}