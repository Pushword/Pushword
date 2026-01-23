<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Repository\UserRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pw:user:delete', description: 'Delete a user')]
final readonly class UserDeleteCommand
{
    use AskIfNotSettedTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepo
    ) {
    }

    public function __invoke(
        #[Argument(name: 'email')]
        ?string $email,
        InputInterface $input,
        OutputInterface $output
    ): int {
        $email = $this->getOrAskIfNotSetted($input, $output, 'email', currentValue: $email);

        $user = $this->userRepo->findOneBy(['email' => $email]);
        if (null === $user) {
            $output->writeln('<error>User `'.$email.'` not found.</error>');

            return Command::FAILURE;
        }

        $this->em->remove($user);
        $this->em->flush();

        $output->writeln('<info>User `'.$email.'` deleted with success.</info>');

        return Command::SUCCESS;
    }
}
