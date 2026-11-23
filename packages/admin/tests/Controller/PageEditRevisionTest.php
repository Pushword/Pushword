<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\UserRepository;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class PageEditRevisionTest extends AbstractAdminTestClass
{
    private ?int $pageId = null;

    protected function tearDown(): void
    {
        if (null !== $this->pageId) {
            $em = self::getContainer()->get(EntityManagerInterface::class);
            $page = $em->find(Page::class, $this->pageId);
            if ($page instanceof Page) {
                $em->remove($page);
                $em->flush();
            }
        }

        parent::tearDown();
    }

    public function testAgentSaveChangesThePingRevisionAndProtectsManualAndShortcutSaves(): void
    {
        $client = $this->loginUser();
        $page = $this->createPage();
        $editUrl = $this->generateAdminUrl('admin_page_edit', ['id' => $page->id]);
        $crawler = $client->request(Request::METHOD_GET, $editUrl);
        $form = $crawler->filter('form[name="Page"]')->form();
        $openedRevision = $this->revisionOf($form);
        self::assertSame($openedRevision, $crawler->filter('[data-pw-page-revision]')->attr('data-pw-server-revision'));
        $form['Page[h1]'] = 'Editor draft';

        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@example.tld']);
        self::assertInstanceOf(User::class, $user);
        self::assertNotNull($user->apiToken);
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer '.$user->apiToken];
        $api = '/api/page/'.$page->host.'/'.$page->slug;
        $client->request(Request::METHOD_GET, $api, server: $headers);
        self::assertResponseIsSuccessful();
        $etag = $client->getResponse()->headers->get('ETag');
        self::assertNotNull($etag);
        $client->jsonRequest(Request::METHOD_PUT, $api, ['body' => 'Body from the agent'], $headers + ['HTTP_IF_MATCH' => $etag]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest(Request::METHOD_POST, '/admin/page/'.$page->id.'/lock/ping', ['tabId' => 'revision-test']);
        self::assertResponseIsSuccessful();
        $ping = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($ping);
        self::assertIsString($ping['revision']);
        self::assertNotSame($openedRevision, $ping['revision']);

        $client->submit($form, serverParameters: ['HTTP_HX_REQUEST' => 'true']);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        $stale = $client->submit($form)->filter('form[name="Page"]')->form();
        self::assertSelectorTextContains('.form-error-message', 'This page changed since you opened it.');
        self::assertSame($openedRevision, $this->revisionOf($stale));
        self::assertSame('Editor draft', $stale->getValues()['Page[h1]']);

        $latest = $client->request(Request::METHOD_GET, $editUrl)->filter('form[name="Page"]')->form();
        self::assertSame('Concurrency test', $latest->getValues()['Page[h1]']);
        self::assertSame($ping['revision'], $this->revisionOf($latest));
        $latest['Page[h1]'] = 'Editor draft';
        $client->submit($latest, serverParameters: ['HTTP_HX_REQUEST' => 'true']);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $savedRevision = $client->getResponse()->headers->get('X-Pushword-Page-Revision');
        self::assertNotNull($savedRevision);
        self::assertNotSame($ping['revision'], $savedRevision);

        $final = $client->request(Request::METHOD_GET, $editUrl)->filter('form[name="Page"]')->form();
        self::assertSame($savedRevision, $this->revisionOf($final));
        self::assertSame('Editor draft', $final->getValues()['Page[h1]']);
        $client->request(Request::METHOD_GET, $api, server: $headers);
        self::assertStringContainsString('Body from the agent', (string) $client->getResponse()->getContent());
    }

    public function testShortcutDoesNotConfirmARejectedForm(): void
    {
        $client = $this->loginUser();
        $page = $this->createPage();
        $editUrl = $this->generateAdminUrl('admin_page_edit', ['id' => $page->id]);
        $form = $client->request(Request::METHOD_GET, $editUrl)->filter('form[name="Page"]')->form();
        $revision = $this->revisionOf($form);
        $headers = ['HTTP_HX_REQUEST' => 'true'];

        $form['Page[_pwRevision]'] = '';
        $client->submit($form, serverParameters: $headers);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        $form['Page[_pwRevision]'] = $revision;
        $form['Page[slug]'] = 'homepage';
        $client->submit($form, serverParameters: $headers);
        self::assertResponseStatusCodeSame(422);
        self::assertNull($client->getResponse()->headers->get('X-Pushword-Page-Revision'));
    }

    public function testAConcurrentDatabaseWriteCannotReplaceAnAlreadyLoadedPage(): void
    {
        $this->loginUser();
        $page = $this->createPage();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $otherEditor = new EntityManager($em->getConnection(), $em->getConfiguration());
        $stale = $otherEditor->find(Page::class, $page->id);
        self::assertInstanceOf(Page::class, $stale);

        $page->h1 = 'Saved first';
        $em->flush();

        $stale->h1 = 'Out of date';

        try {
            $otherEditor->flush();
            self::fail('A stale page was saved.');
        } catch (OptimisticLockException) {
            $em->refresh($page);
            self::assertSame('Saved first', $page->h1);
        }
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testTheFormHandlesAWriteDuringSave(bool $shortcut): void
    {
        $client = $this->loginUser();
        $client->disableReboot();

        $page = $this->createPage();
        $editUrl = $this->generateAdminUrl('admin_page_edit', ['id' => $page->id]);
        $form = $client->request(Request::METHOD_GET, $editUrl)->filter('form[name="Page"]')->form();
        $form['Page[h1]'] = 'Unsuccessful draft';
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $listener = new readonly class($em->getConnection(), $page->id) {
            public function __construct(private Connection $connection, private ?int $id)
            {
            }

            public function preFlush(): void
            {
                $this->connection->executeStatement('UPDATE page SET h1 = ?, write_version = write_version + 1 WHERE id = ?', ['Saved concurrently', $this->id]);
            }
        };
        $events = $em->getEventManager();
        $events->addEventListener([Events::preFlush], $listener);

        try {
            $client->submit($form, serverParameters: $shortcut ? ['HTTP_HX_REQUEST' => 'true'] : []);
        } finally {
            $events->removeEventListener([Events::preFlush], $listener);
        }

        if ($shortcut) {
            self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        } else {
            self::assertResponseRedirects($editUrl, Response::HTTP_SEE_OTHER);
        }

        $client->enableReboot();
        $latest = $client->request(Request::METHOD_GET, $editUrl)->filter('form[name="Page"]')->form();
        self::assertSame('Saved concurrently', $latest->getValues()['Page[h1]']);
        if (! $shortcut) {
            self::assertSelectorTextContains('.alert-warning', 'This page changed since you opened it.');
        }
    }

    private function revisionOf(Form $form): string
    {
        $revision = $form->getValues()['Page[_pwRevision]'];
        self::assertIsString($revision);

        return $revision;
    }

    private function createPage(): Page
    {
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->slug = 'concurrent-edit-'.bin2hex(random_bytes(5));
        $page->h1 = 'Concurrency test';

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($page);
        $em->flush();

        $this->pageId = $page->id;

        return $page;
    }
}
