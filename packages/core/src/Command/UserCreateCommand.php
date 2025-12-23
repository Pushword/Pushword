<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\User;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'pw:user:create', description: 'Create a new user')]
final readonly class UserCreateCommand
{
    use AskIfNotSettedTrait;

    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $passwordEncoder)
    {
    }

    protected function createUser(string $email, string $password, string $role): void
    {
        $user = new User();
        $user->email = $email;
        $user->setPassword($this->passwordEncoder->hashPassword($user, $password));
        $user->setRoles([$role]);

        $this->em->persist($user);
        $this->em->flush();
    }

    public function __invoke(#[Argument(name: 'email')]
        ?string $email, #[Argument(name: 'password')]
        ?string $password, #[Argument(name: 'role')]
        ?string $role, InputInterface $input, OutputInterface $output): int
    {
        $email = $this->getOrAskIfNotSetted($input, $output, 'email');
        $password = $this->getOrAskIfNotSetted($input, $output, 'password');
        $role = $this->getOrAskIfNotSetted($input, $output, 'role', 'ROLE_SUPER_ADMIN');

        $this->createUser($email, $password, $role);

        $output->writeln('<info>User `'.$email.'` created with success.</info>');

        return Command::SUCCESS;
    }
}
