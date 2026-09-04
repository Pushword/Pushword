<?php

namespace Pushword\Admin\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class UserCrudSecurityTest extends AbstractAdminTestClass
{
    public function testEditorCannotOpenUserAdministration(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $editor = new $userClass();
        $editor->email = 'user-crud-editor-'.uniqid().'@example.tld';
        $editor->setRoles(['ROLE_EDITOR']);

        $entityManager->persist($editor);
        $entityManager->flush();

        $client->loginUser($editor);
        $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_user_list'));

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    public function testSavingUserWithoutANewPasswordPreservesTheHash(): void
    {
        $client = $this->loginUser();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $repository = self::getContainer()->get(UserRepository::class);
        $user = $repository->findOneBy(['email' => 'admin@example.tld']);
        self::assertInstanceOf(User::class, $user);
        self::assertNotNull($user->id);
        $passwordHash = $user->getPassword();

        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_user_edit', ['id' => $user->id]));
        $client->submit($crawler->selectButton('Save changes')->form());

        self::assertResponseRedirects();
        $entityManager->clear();
        $savedUser = $repository->find($user->id);
        self::assertInstanceOf(User::class, $savedUser);
        self::assertSame($passwordHash, $savedUser->getPassword());
    }
}
