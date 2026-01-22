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

    protected function createUser(string $email, string $password, string $role, ?string $username = null): User
    {
        $user = new User();
        $user->email = $email;
        $user->username = $username;
        $user->setPassword($this->passwordEncoder->hashPassword($user, $password));
        $user->setRoles([$role]);

        // Auto-generate API token for super admins
        if (User::ROLE_SUPER_ADMIN === $role) {
            $user->generateApiToken();
        }

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function __invoke(
        #[Argument(name: 'email')]
        ?string $email,
        #[Argument(name: 'password')]
        ?string $password,
        #[Argument(name: 'role')]
        ?string $role,
        #[Argument(name: 'username')]
        ?string $username,
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $email = $this->getOrAskIfNotSetted($input, $output, 'email');
        $password = $this->getOrAskIfNotSetted($input, $output, 'password');
        $role = $this->getOrAskIfNotSetted($input, $output, 'role', 'ROLE_SUPER_ADMIN');
        $username = $this->getOrAskIfNotSetted($input, $output, 'username', null, allowEmpty: true);

        $user = $this->createUser($email, $password, $role, $username ?: null);

        $output->writeln('<info>User `'.$email.'` created with success.</info>');

        if (null !== $user->apiToken) {
            $output->writeln('<info>API Token: '.$user->apiToken.'</info>');
        }

        return Command::SUCCESS;
    }
}
