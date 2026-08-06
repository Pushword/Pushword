<?php

namespace Pushword\Admin\Tests\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request;

/**
 * Saving a page without editing it must not write.
 *
 * Two fields used to make the untouched form come back different from what was stored,
 * so the save was a real UPDATE: a bumped `updatedAt` moving the page to the top of the
 * admin index, a version snapshot, a flat export, a purged static cache and a search
 * reindex — all for an edit nobody made.
 *
 * @see \Pushword\Admin\FormField\PagePublishedAtField  (minute-precision widget)
 * @see \Pushword\Admin\Controller\PageCrudController::keepEditMessageWhenItIsTheOnlyChange()
 */
#[Group('integration')]
final class PageEditNoopSaveTest extends AbstractAdminTestClass
{
    public function testSavingAnUntouchedPageWritesNothing(): void
    {
        $client = $this->loginUser();
        $pageId = $this->getPageId();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        // The state that used to dirty an untouched save: seconds on `publishedAt` (what
        // the index toggle and the flat import both store) and a message from a past edit.
        $connection->executeStatement(
            'UPDATE page SET published_at = ?, edit_message = ? WHERE id = ?',
            ['2026-08-05 20:11:32', 'fixed the intro', $pageId],
        );
        $entityManager->clear();

        $before = $this->fetchPage($connection, $pageId);
        $versionsBefore = $this->countVersions($connection);

        $client->submit($this->getEditForm($client, $pageId));

        self::assertSame($before, $this->fetchPage($connection, $pageId), 'An untouched save must leave the row alone');
        self::assertSame($versionsBefore, $this->countVersions($connection), 'An untouched save must not cut a version');
    }

    public function testARealEditStillClearsTheMessageOfThePreviousOne(): void
    {
        $client = $this->loginUser();
        $pageId = $this->getPageId();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        $connection->executeStatement(
            'UPDATE page SET edit_message = ? WHERE id = ?',
            ['fixed the intro', $pageId],
        );
        $entityManager->clear();

        $form = $this->getEditForm($client, $pageId);
        $form['Page[h1]'] = 'Edited without a message';
        $client->submit($form);

        $after = $this->fetchPage($connection, $pageId);
        self::assertSame('Edited without a message', $after['h1']);
        self::assertSame('', $after['edit_message'], 'The message described the state before this edit');
    }

    public function testAMessageTypedOnAnOtherwiseUntouchedSaveIsStillRecorded(): void
    {
        $client = $this->loginUser();
        $pageId = $this->getPageId();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        $connection->executeStatement('UPDATE page SET edit_message = ? WHERE id = ?', ['fixed the intro', $pageId]);

        $entityManager->clear();

        $form = $this->getEditForm($client, $pageId);
        $form['Page[editMessage]'] = 'reviewed, nothing to change';
        $client->submit($form);

        self::assertSame(
            'reviewed, nothing to change',
            $this->fetchPage($connection, $pageId)['edit_message'],
            'Leaving a note is itself a reason to save',
        );
    }

    public function testThePublicationDateIsStillEditable(): void
    {
        $client = $this->loginUser();
        $pageId = $this->getPageId();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        $connection->executeStatement('UPDATE page SET published_at = ? WHERE id = ?', ['2026-08-05 20:11:32', $pageId]);

        $entityManager->clear();

        $form = $this->getEditForm($client, $pageId);
        $form['Page[publishedAt]'] = '2026-08-04T09:30';
        $client->submit($form);

        self::assertSame('2026-08-04 09:30:00', $this->fetchPage($connection, $pageId)['published_at']);
    }

    public function testThePageCanStillBeUnpublishedFromTheForm(): void
    {
        $client = $this->loginUser();
        $pageId = $this->getPageId();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        $connection->executeStatement('UPDATE page SET published_at = ? WHERE id = ?', ['2026-08-05 20:11:32', $pageId]);

        $entityManager->clear();

        $form = $this->getEditForm($client, $pageId);
        $form['Page[publishedAt]'] = '';
        $client->submit($form);

        self::assertNull(
            $this->fetchPage($connection, $pageId)['published_at'],
            'An emptied date is a real edit, not the untouched widget coming back',
        );
    }

    private function getPageId(): int
    {
        /** @var PageRepository $pageRepo */
        $pageRepo = self::getContainer()->get(PageRepository::class);
        $page = $pageRepo->findOneBy(['slug' => 'homepage', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $page);

        return (int) $page->id;
    }

    private function getEditForm(KernelBrowser $client, int $pageId): Form
    {
        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_edit', ['id' => $pageId]));
        self::assertResponseIsSuccessful();

        return $crawler->filter('form[name="Page"]')->form();
    }

    /** @return array<string, mixed> */
    private function fetchPage(Connection $connection, int $pageId): array
    {
        $row = $connection->fetchAssociative('SELECT * FROM page WHERE id = ?', [$pageId]);
        self::assertNotFalse($row);

        return $row;
    }

    private function countVersions(Connection $connection): int
    {
        $count = $connection->fetchOne('SELECT COUNT(*) FROM version_log');
        self::assertIsNumeric($count);

        return (int) $count;
    }
}
