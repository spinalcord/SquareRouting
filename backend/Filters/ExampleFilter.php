<?php

namespace SquareRouting\Filters;

use SquareRouting\Core\DependencyContainer;

class ExampleFilter
{
    public function before(DependencyContainer $container): void
    {
        echo 'some text before...';
    }

    public function after(DependencyContainer $container): void
    {
        echo '...some text after.';
    }
}
