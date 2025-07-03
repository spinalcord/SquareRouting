<?php

namespace SquareRouting\Controllers;

use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\Language;
use SquareRouting\Core\Response;

class LanguageExampleController
{
    public Language $language;

    public function __construct(DependencyContainer $container)
    {
        $this->language = $container->get(Language::class);
    }

    public function languageExample(): Response
    {
        return (new Response)->html($this->language->translate('user.profile', 'foobar', 8));
    }
}
