<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\UserInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserCreateCommand extends Command
{
    /**
     * @var string|null
     */
    protected static $defaultName = 'pushword:user:create';

    private EntityManagerInterface $em;

    /**
     * @var class-string
     */
    private string $userClass;

    private UserPasswordHasherInterface $passwordEncoder;

    /**
     * @param class-string $userClass
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $userPasswordHasher,
        string $userClass
    ) {
        $this->em = $entityManager;
        $this->passwordEncoder = $userPasswordHasher;
        $this->userClass = $userClass;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Create a new user')
            ->addArgument('email', InputArgument::OPTIONAL)
            ->addArgument('password', InputArgument::OPTIONAL)
            ->addArgument('role', InputArgument::OPTIONAL);
    }

    protected function createUser(string $email, string $password, string $role): void
    {
        /** @var class-string<UserInterface> $userClass */
        $userClass = $this->userClass;
        $user = new $userClass();
        $user->setEmail($email);
        $user->setPassword($this->passwordEncoder->hashPassword($user, $password));
        $user->setRoles([$role]);

        $this->em->persist($user);
        $this->em->flush();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $this->getOrAskIfNotSetted($input, $output, 'email');
        $password = $this->getOrAskIfNotSetted($input, $output, 'password');
        $role = $this->getOrAskIfNotSetted($input, $output, 'role', 'ROLE_SUPER_ADMIN');

        $this->createUser($email, $password, $role);

        $output->writeln('<info>User `'.$email.'` created with success.</info>');

        return 0;
    }

    private function getOrAskIfNotSetted(InputInterface $input, OutputInterface $output, string $argument, string $default = ''): string
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $argumentValue = $input->getArgument($argument);

        if (null !== $argumentValue) {
            return \strval($argumentValue);
        }

        $question = new Question($argument.('' !== $default ? ' (default: '.$default.')' : '').':', $default);
        if ('password' == $argument) {
            $question->setHidden(true);
        }

        $argumentValue = $helper->ask($input, $output, $question);

        if (null === $argumentValue) {
            $output->writeln('<error>'.$argument.' is required.</error>');

            return $this->getOrAskIfNotSetted($input, $output, $argument, $default);
        }

        return \strval($argumentValue);
    }
}
