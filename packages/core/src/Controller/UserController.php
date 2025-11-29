<?php

namespace Pushword\Core\Controller;

use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class UserController extends AbstractController
{
    public function __construct(private readonly AuthenticationUtils $authenticationUtils)
    {
    }

    #[Route('/login', name: 'pushword_login')]
    public function login(): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('pushword_admin');
        }

        // get the login error if there is one
        $authenticationException = $this->authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $this->authenticationUtils->getLastUsername();

        return $this->render('@Pushword/user/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $authenticationException,
        ]);
    }

    #[Route('/logout', name: 'pushword_logout', methods: ['GET', 'HEAD', 'POST'])]
    public function logout(): never
    {
        throw new LogicException('This method can be blank');
    }
}
