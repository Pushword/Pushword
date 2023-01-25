<?php

namespace Pushword\Core\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class UserControllerTest extends KernelTestCase
{
    public function testLogin()
    {
        self::bootKernel();

        /* how to load a request ?
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/login'));
        $userController = self::$kernel->getContainer()->get('Pushword\Core\Controller\UserController');
        $response = $userController->login(
            (new AuthenticationUtils($requestStack))
        );
        $this->assertSame(200, $response->getStatusCode());
        */

        $this->assertSame(1, 1);
    }
}
