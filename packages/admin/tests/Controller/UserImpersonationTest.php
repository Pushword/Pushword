<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class UserImpersonationTest extends AbstractAdminTestClass
{
    public function testSuperAdminBrowsesAsAnEditorThenSwitchesBack(): void
    {
        $client = $this->loginUser();
        $editor = $this->createAccount('ROLE_EDITOR')->getUserIdentifier();
        $member = $this->createAccount('ROLE_USER')->getUserIdentifier();

        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_user_list'));
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.pw-impersonation-banner');
        self::assertCount(1, $crawler->filter('a[href="/admin?_switch_user='.$editor.'"]'));
        self::assertCount(0, $crawler->filter('a[href*="_switch_user='.$member.'"]'), 'the admin would answer this account with a 403');

        $client->request(Request::METHOD_GET, '/admin?_switch_user='.urlencode($editor));
        self::assertResponseRedirects('/admin');
        $client->followRedirects();
        $crawler = $client->request(Request::METHOD_GET, '/admin');

        self::assertSelectorTextContains('.pw-impersonation-banner', $editor);
        $exitUrl = $crawler->filter('.pw-impersonation-banner__exit')->attr('href');
        self::assertSame('/admin/user?_switch_user=_exit', $exitUrl);

        $client->followRedirects(false);
        $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_user_list'));
        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode(), "the session now carries the editor's roles");

        $client->request(Request::METHOD_GET, $exitUrl);
        self::assertResponseRedirects('/admin/user');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.pw-impersonation-banner');
    }

    /** The action is offered by the roles an account reaches, not by the ones it holds. */
    public function testTheActionFollowsTheRoleHierarchy(): void
    {
        $client = $this->loginUser();
        $admin = $this->createAccount('ROLE_ADMIN')->getUserIdentifier();
        $pending = $this->createAccount('ROLE_EDITOR', pendingPasswordChange: true)->getUserIdentifier();

        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_user_list'));
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('a[href="/admin?_switch_user='.$admin.'"]'), 'ROLE_ADMIN reaches ROLE_EDITOR');
        self::assertStringContainsString($pending, $crawler->filter('table')->text(), 'the pending account is listed');
        self::assertCount(0, $crawler->filter('a[href*="_switch_user='.$pending.'"]'), 'a pending password change holds ROLE_PASSWORD_CHANGE only');

        // Searched for, so the row is surely on the page and its missing link means something.
        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_user_list').'?query=admin%40example.tld');
        self::assertStringContainsString('admin@example.tld', $crawler->filter('table')->text());
        self::assertCount(0, $crawler->filter('a[href="/admin?_switch_user=admin@example.tld"]'), 'never your own row');
    }

    public function testEditorCannotImpersonate(): void
    {
        $client = self::createClient();
        $client->loginUser($this->createAccount('ROLE_EDITOR'));
        $client->request(Request::METHOD_GET, '/admin?_switch_user='.urlencode($this->createAccount('ROLE_EDITOR')->getUserIdentifier()));

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    private function createAccount(string $role, bool $pendingPasswordChange = false): User
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = 'impersonation-'.strtolower(substr($role, 5)).'-'.uniqid().'@example.tld';
        $user->setRoles([$role]);
        if ($pendingPasswordChange) {
            $user->requirePasswordChange();
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
