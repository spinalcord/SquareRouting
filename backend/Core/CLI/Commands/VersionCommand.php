<?php

namespace SquareRouting\Core\CLI\Commands;


class VersionCommand extends BaseCommand
{
    public function getName(): string
    {
        return 'version';
    }

    public function getDescription(): string
    {
        return 'Show version information';
    }

    public function execute(array $args, string $input, string $commandId): array
    {
        $ascii = '
███████╗ ██████╗    ██████╗  ██████╗ ██╗   ██╗████████╗
██╔════╝██╔═══██╗   ██╔══██╗██╔═══██╗██║   ██║╚══██╔══╝
███████╗██║   ██║   ██████╔╝██║   ██║██║   ██║   ██║   
╚════██║██║▄▄ ██║   ██╔══██╗██║   ██║██║   ██║   ██║   
███████║╚██████╔╝██╗██║  ██║╚██████╔╝╚██████╔╝   ██║██╗
╚══════╝ ╚══▀▀═╝ ╚═╝╚═╝  ╚═╝ ╚═════╝  ╚═════╝    ╚═╝╚═╝
Web-Terminal Version 1.0';

        return $this->createResponse($ascii);
    }
}
