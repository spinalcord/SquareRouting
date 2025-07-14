<?php

namespace SquareRouting\Core\CLI\Commands;
use  SquareRouting\Core\CLI\CommandInterface;

abstract class BaseCommand implements CommandInterface
{
    protected $container;
    protected $account;
    protected $dotEnv;

    public function __construct($container)
    {
        $this->container = $container;
        $this->account = $container->get(\SquareRouting\Core\Account::class);
        $this->dotEnv = $container->get(\SquareRouting\Core\DotEnv::class);
    }

    public function requiresPermission(): ?string
    {
        return null; // Override in subclasses if needed
    }

    protected function createResponse(string $output, string $type = 'ordinary'): array
    {
        return [
            'output' => $output,
            'outputType' => $type,
            'commandComplete' => true,
            'expectInput' => false,
            'queue' => false,
        ];
    }

    protected function createTerminalLabelResponse(string $label, string $output): array
    {
        return [
            'output' => $output,
            'outputType' => 'success',
            'commandComplete' => true,
            'expectInput' => false,
            'queue' => false,
            'setTerminalLabel' => $label,
        ];
    }
}
