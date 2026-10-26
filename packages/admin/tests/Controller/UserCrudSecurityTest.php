<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class UserCrudSecurityTest extends AbstractAdminTestClass
{
    /** @return iterable<string, array{?string}> */
    public static function apiTokenStates(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'existing' => [str_repeat('a', 64)];
    }

    #[DataProvider('apiTokenStates')]
    public function testUserEditExplainsPasswordAndShowsOnlyRelevantTokenActions(?string $apiToken): void
    {
        $client = $this->loginUser();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@example.tld']);
        self::assertInstanceOf(User::class, $user);
        $user->apiToken = $apiToken;
        $entityManager->flush();

        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_user_edit', ['id' => $user->id]));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#User_email[type="email"]');
        self::assertSelectorNotExists('#User_plainPassword[required]');
        self::assertStringContainsString('Leave blank to keep the current password.', $crawler->filter('#User_plainPassword')->ancestors()->filter('.form-group')->text());
        self::assertSame('Registration date', $crawler->filter('label[for="User_createdAt"]')->text());
        self::assertSelectorExists('#User_apiToken[readonly][data-api-token-field]');
        self::assertSame($apiToken ?? '', $crawler->filter('#User_apiToken')->attr('value'));

        $hasToken = null !== $apiToken && '' !== $apiToken;
        self::assertSame($hasToken, null !== $crawler->filter('#User_apiToken_generate')->attr('hidden'));
        foreach (['copy', 'regenerate', 'revoke'] as $action) {
            self::assertSame(! $hasToken, null !== $crawler->filter('#User_apiToken_'.$action)->attr('hidden'));
            self::assertNotSame('', trim($crawler->filter('#User_apiToken_'.$action)->text()));
        }
    }

    public function testUserRolesAreReadableAndIndividuallySelectable(): void
    {
        $client = $this->loginUser();
        $crawler = $client->request(Request::METHOD_GET, '/admin/user');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Super Administrator', $crawler->filter('.datagrid')->text());
        self::assertStringNotContainsString('ROLE_', $crawler->filter('.datagrid')->text());
        self::assertStringNotContainsString('ROLE_', $crawler->filter('.datagrid td[data-label="Role"]')->html());

        $crawler = $client->request(Request::METHOD_GET, '/admin/user/new');
        self::assertResponseIsSuccessful();
        self::assertCount(4, $crawler->filter('input[type="checkbox"][name="User[roles][]"]'));
        self::assertSelectorExists('#User_plainPassword[required]');
        self::assertStringNotContainsString('Leave blank to keep the current password.', $crawler->filter('form[name="User"]')->text());
    }

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
