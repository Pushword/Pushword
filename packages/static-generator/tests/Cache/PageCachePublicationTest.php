<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Tests\Cache;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\RevisionCalculator;
use Pushword\StaticGenerator\Cache\PageCachePublication;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class PageCachePublicationTest extends KernelTestCase
{
    private string $directory;

    private PageCachePublication $publication;

    /** @var list<int> */
    private array $pageIds = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->directory = self::getContainer()->getParameter('pw.var_dir').'/publication-test';
        new Filesystem()->mkdir($this->directory);
        $this->publication = self::getContainer()->get(PageCachePublication::class);
    }

    protected function tearDown(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach ($this->pageIds as $id) {
            $page = $em->find(Page::class, $id);
            if ($page instanceof Page) {
                $em->remove($page);
            }
        }

        $em->flush();
        new Filesystem()->remove($this->directory);
        parent::tearDown();
    }

    public function testAnInFlightRenderCannotPublishAfterASave(): void
    {
        $page = $this->page();
        $oldSnapshot = $this->snapshot($page);
        $oldRevision = $this->publication->revision($oldSnapshot);
        $path = $this->directory.'/article.html';
        $filesystem = new Filesystem();
        $filesystem->dumpFile($path, 'old');

        // Keep updatedAt untouched: even two saves within one second differ.
        $page->mainContent = 'new';
        $page->skipAutoTimestamp = true;
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->publication->invalidate($page, static fn () => $filesystem->remove($path));
        $this->publication->publish($oldSnapshot, $oldRevision, static fn () => $filesystem->dumpFile($path, 'old'));
        self::assertFileDoesNotExist($path);

        // A new service instance mirrors a different web or Messenger process.
        $otherProcess = new PageCachePublication(
            self::getContainer()->get(RevisionCalculator::class),
            self::getContainer()->get(EntityManagerInterface::class),
            self::getContainer()->getParameter('pw.var_dir'),
        );
        $otherProcess->publish($page, $otherProcess->revision($page), static fn () => $filesystem->dumpFile($path, 'new'));
        self::assertSame('new', file_get_contents($path));
    }

    public function testAnInFlightRenderCannotRecreateARemovedPage(): void
    {
        $page = $this->page();
        $snapshot = $this->snapshot($page);
        $revision = $this->publication->revision($snapshot);
        $path = $this->directory.'/article.html';
        $filesystem = new Filesystem();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->remove($page);
        $em->flush();

        $this->publication->publish($snapshot, $revision, static fn () => $filesystem->dumpFile($path, 'old'));
        self::assertFileDoesNotExist($path);
    }

    public function testAFirstRenderNeedsNoPreviousInvalidation(): void
    {
        $page = $this->page();
        $path = $this->directory.'/article.html';
        $filesystem = new Filesystem();

        $this->publication->publish($page, $this->publication->revision($page), static fn () => $filesystem->dumpFile($path, 'first'));
        self::assertSame('first', file_get_contents($path));
    }

    public function testChangingAnAssociationInvalidatesAnInFlightRender(): void
    {
        $page = $this->page();
        $oldRevision = $this->publication->revision($page);
        $parent = new Page();
        $parent->host = 'localhost.dev';
        $parent->slug = 'publication-parent';

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($parent);
        $em->flush();
        $this->pageIds[] = (int) $parent->id;
        $page->parentPage = $parent;
        $page->skipAutoTimestamp = true;

        $em->flush();
        $path = $this->directory.'/article.html';
        $filesystem = new Filesystem();

        $this->publication->invalidate($page, static fn () => $filesystem->remove($path));
        $this->publication->publish($page, $oldRevision, static fn () => $filesystem->dumpFile($path, 'old'));
        self::assertFileDoesNotExist($path);
    }

    public function testAWorkerCannotPublishItsStaleIdentityMap(): void
    {
        $page = $this->page();
        $oldRevision = $this->publication->revision($page);
        $path = $this->directory.'/article.html';
        $filesystem = new Filesystem();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        // Another process changed the row; this worker still holds the old entity.
        $em->getConnection()->executeStatement('UPDATE page SET main_content = ? WHERE id = ?', ['restored content', $page->id]);
        $this->publication->publish($page, $oldRevision, static fn () => $filesystem->dumpFile($path, 'old'));
        self::assertFileDoesNotExist($path);

        $this->publication->publish($page, $this->publication->revision($page), static fn () => $filesystem->dumpFile($path, 'restored content'));
        self::assertSame('restored content', file_get_contents($path));
    }

    private function page(): Page
    {
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->slug = 'publication-test';
        $page->mainContent = 'old';

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($page);
        $em->flush();
        $this->pageIds[] = (int) $page->id;

        return $page;
    }

    private function snapshot(Page $page): Page
    {
        $snapshot = clone $page;
        // Page::__clone() normally prepares a new entity; a worker snapshot keeps its ID.
        new ReflectionProperty(Page::class, 'id')->setValue($snapshot, $page->id);

        return $snapshot;
    }
}
