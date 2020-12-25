<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserCreateCommand extends Command
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
            ->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('password', InputArgument::REQUIRED)
            ->addArgument('role', InputArgument::REQUIRED);
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
        $this->createUser($input->getArgument('email'), $input->getArgument('password'), $input->getArgument('role'));

        $output->writeln('<info>User `'.$input->getArgument('email').'` created with success.</info>');

        return 0;
    }
}
