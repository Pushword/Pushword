<?php

namespace Pushword\Core\Command;

use Pushword\Core\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

/** Database-portable account probe used by the Docker entrypoint. */
#[AsCommand(name: 'pw:user:exists', description: 'Return success when at least one user exists', hidden: true)]
final readonly class UserExistsCommand
{
    public function __construct(private UserRepository $users)
    {
    }

    public function __invoke(): int
    {
        return 0 < $this->users->count([]) ? Command::SUCCESS : Command::FAILURE;
    }
}
