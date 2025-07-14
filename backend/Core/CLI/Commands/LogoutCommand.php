<?php

namespace SquareRouting\Core\CLI\Commands;
class LogoutCommand extends BaseCommand
{
    public function getName(): string
    {
        return 'logout';
    }

    public function getDescription(): string
    {
        return 'Logout from current session';
    }

    public function execute(array $args, string $input, string $commandId): array
    {
        $this->account->logout();
        return $this->createTerminalLabelResponse('', 'logged out');
    }
}
