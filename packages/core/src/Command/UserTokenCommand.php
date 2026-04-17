<?php

namespace Pushword\Core\Command;

use Pushword\Core\Repository\UserRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pw:user:token', description: 'Output the API token for a user')]
final readonly class UserTokenCommand
{
    public function __construct(private UserRepository $userRepo)
    {
    }

    public function __invoke(
        #[Argument(name: 'email')]
        string $email,
        OutputInterface $output,
    ): int {
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        $user = $this->userRepo->findOneBy(['email' => $email]);
        if (null === $user) {
            $errorOutput->writeln('<error>User `'.$email.'` not found.</error>');

            return Command::FAILURE;
        }

        if (null === $user->apiToken) {
            $errorOutput->writeln('<error>User `'.$email.'` has no API token.</error>');

            return Command::FAILURE;
        }

        $output->write($user->apiToken);

        return Command::SUCCESS;
    }
}
