<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

final class UserCreateCommand extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var string
     */
    private $userClass;

    /**
     * @var UserPasswordEncoderInterface
     */
    private $passwordEncoder;

    public function __construct(
        EntityManagerInterface $em,
        UserPasswordEncoderInterface $passwordEncoder,
        $userClass
    ) {
        $this->em = $em;
        $this->passwordEncoder = $passwordEncoder;
        $this->userClass = $userClass;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('pushword:user:create')
            ->setDescription('Create a new user')
            ->addArgument('email', InputArgument::OPTIONAL)
            ->addArgument('password', InputArgument::OPTIONAL)
            ->addArgument('role', InputArgument::OPTIONAL);
    }

    protected function createUser($email, $password, $role)
    {
        $userClass = $this->userClass;
        $user = new $userClass();
        $user->setEmail($email);
        $user->setPassword($this->passwordEncoder->encodePassword($user, $password));
        $user->setRoles([$role]);

        $this->em->persist($user);
        $this->em->flush();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $email = $this->getOrAskIfNotSetted($input, $output, 'email');
        $password = $this->getOrAskIfNotSetted($input, $output, 'password');
        $role = $this->getOrAskIfNotSetted($input, $output, 'role', 'ROLE_SUPER_ADMIN');

        $this->createUser($email, $password, $role);

        $output->writeln('<info>User `'.$email.'` created with success.</info>');

        return 0;
    }

    private function getOrAskIfNotSetted(InputInterface $input, OutputInterface $output, string $argument, $default = null)
    {
        $helper = $this->getHelper('question');
        $argumentValue = $input->getArgument($argument);

        if (null !== $argumentValue) {
            return $argumentValue;
        }

        $question = new Question($argument.(null !== $default ? ' (default: '.$default.')' : '').':', $default);
        if ('password' == $argument) {
            $question->setHidden(true);
        }
        $argumentValue = $helper->ask($input, $output, $question);

        if (null === $argumentValue) {
            $output->writeln('<error>'.$argument.' is required. Command will probably failed.</error>');

            return false;
        }

        return $argumentValue;
    }
}
