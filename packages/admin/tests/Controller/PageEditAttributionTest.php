<?php

namespace Pushword\Admin\Tests\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * Editing a page authored elsewhere does not make you its author.
 *
 * A page imported by the flat sync or written before the site had users carries no
 * `createdBy`. Backfilling it on update handed authorship to whoever happened to save
 * first — a claim nothing in the interface contradicted.
 *
 * @see \Pushword\Core\EventListener\PageListener::updatePageEditor()
 */
#[Group('integration')]
final class PageEditAttributionTest extends AbstractAdminTestClass
{
    public function testSavingAPageAuthoredElsewhereDoesNotClaimItsAuthorship(): void
    {
        $client = $this->loginUser();

        /** @var PageRepository $pageRepo */
        $pageRepo = self::getContainer()->get(PageRepository::class);
        $page = $pageRepo->findOneBy(['slug' => 'homepage', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $page);
        $pageId = (int) $page->id;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        $connection->executeStatement(
            'UPDATE page SET created_by_id = NULL, edited_by_id = NULL WHERE id = ?',
            [$pageId],
        );
        $entityManager->clear();

        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_edit', ['id' => $pageId]));
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="Page"]')->form();
        $form['Page[h1]'] = 'Edited by someone who did not write it';
        $client->submit($form);

        $row = $this->fetchPage($connection, $pageId);

        self::assertNull($row['created_by_id'], 'Editing a page is not authoring it');
        self::assertNotNull($row['edited_by_id'], 'The editor of this edit is still recorded');
    }

    public function testCreatingAPageStillRecordsItsAuthor(): void
    {
        $client = $this->loginUser();

        /** @var PageRepository $pageRepo */
        $pageRepo = self::getContainer()->get(PageRepository::class);
        $page = $pageRepo->findOneBy(['slug' => 'homepage', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $page);

        // Cloning is the cheapest page creation that runs through a logged-in request.
        $pageId = (int) $page->id;
        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_list'));
        $token = $crawler->filter('form[action$="'.$this->buildClonePath($pageId).'"] input[name="_token"]')->attr('value');
        self::assertNotNull($token);
        $client->request(Request::METHOD_POST, $this->buildClonePath($pageId), ['_token' => $token]);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        /** @var PageRepository $pageRepo */
        $pageRepo = self::getContainer()->get(PageRepository::class);
        $clone = $pageRepo->findOneBy(['slug' => 'homepage-copy', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $clone);
        self::assertSame('admin@example.tld', $clone->createdBy?->email);

        $entityManager->remove($clone);
        $entityManager->flush();
    }

    /** @return array<string, mixed> */
    private function fetchPage(Connection $connection, int $pageId): array
    {
        $row = $connection->fetchAssociative('SELECT * FROM page WHERE id = ?', [$pageId]);
        self::assertNotFalse($row);

        return $row;
    }

    /** The clone action has no alias route, so this one is still built by hand. */
    private function buildClonePath(int $pageId): string
    {
        return self::getContainer()->get('router')->generate('admin_page_clone_page', ['entityId' => $pageId]);
    }
}
