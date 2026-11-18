<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Tests\Cache\Admin;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\Cache\Message\PageCacheRefreshMessage;
use Pushword\StaticGenerator\Cache\MessageHandler\PageCacheRefreshHandler;
use Pushword\StaticGenerator\Generator\CompressionAlgorithm;
use Pushword\StaticGenerator\StaticAppGenerator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

#[Group('integration')]
final class PageCacheSaveTest extends AbstractAdminTestClass
{
    private ?int $pageId = null;

    private string $cachePath = '';

    protected function tearDown(): void
    {
        if (null !== $this->pageId) {
            $em = self::getContainer()->get(EntityManagerInterface::class);
            $page = $em->find(Page::class, $this->pageId);
            if (null !== $page) {
                $em->remove($page);
                $em->flush();
            }
        }

        if ('' !== $this->cachePath) {
            foreach (CompressionAlgorithm::fileSuffixes() as $suffix) {
                new Filesystem()->remove($this->cachePath.$suffix);
            }
        }

        parent::tearDown();
    }

    public function testSaveAndReturnInvalidatesBeforeAnAsyncWorkerRuns(): void
    {
        $client = $this->loginUser();
        $page = $this->createCachedPage();

        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_edit', ['id' => $page->id]));
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('button[type="submit"][value="saveAndReturn"]')->form();
        $form['Page[mainContent]'] = 'The freshly saved article content.';
        $client->submit($form);

        self::assertResponseRedirects('/cache-save-redirect');
        $this->assertCacheRemoved();

        self::assertNotEmpty($this->transport()->getSent(), 'The refresh must remain queued.');

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('The freshly saved article content.', (string) $client->getResponse()->getContent());

        $this->refresh();
        self::assertFileExists($this->cachePath);
        self::assertStringContainsString('The freshly saved article content.', (string) file_get_contents($this->cachePath));
    }

    #[DataProvider('preservedFileModes')]
    public function testHeldPagesAndBulkImportsPreserveTheirFiles(bool $held): void
    {
        $this->loginUser();
        $page = $this->createCachedPage();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $page->mainContent = 'The freshly saved article content.';

        if ($held) {
            $page->setHoldPublication(true);
            $em->flush();
        } else {
            self::getContainer()->get(PageCacheSuppressor::class)->suppress($em->flush(...));
        }

        foreach (CompressionAlgorithm::fileSuffixes() as $suffix) {
            self::assertSame('The previous article content.', file_get_contents($this->cachePath.$suffix));
        }

        self::assertCount(0, $this->transport()->getSent());

        if ($held) {
            $page->setHoldPublication(false);
            $em->flush();
            $this->assertCacheRemoved();
        }

        // The preserved revision must not prevent a later refresh/bulk build.
        $this->refresh();
        self::assertStringContainsString('The freshly saved article content.', (string) file_get_contents($this->cachePath));
    }

    /** @return iterable<string, array{bool}> */
    public static function preservedFileModes(): iterable
    {
        yield 'publication hold' => [true];
        yield 'bulk import' => [false];
    }

    #[DataProvider('uncacheableModes')]
    public function testAnUncacheablePageLosesItsOldFileImmediately(bool $unpublish): void
    {
        $this->loginUser();
        $page = $this->createCachedPage();
        if ($unpublish) {
            $page->publishedAt = null;
        } else {
            $page->setCustomProperty('cache', false);
        }

        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->assertCacheRemoved();
        $this->refresh();
        $this->assertCacheRemoved();
    }

    /** @return iterable<string, array{bool}> */
    public static function uncacheableModes(): iterable
    {
        yield 'unpublished' => [true];
        yield 'cache opt-out' => [false];
    }

    private function createCachedPage(): Page
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->slug = 'cache-save-redirect';
        $page->title = 'Cached article';
        $page->h1 = 'Cached article';
        $page->mainContent = 'The previous article content.';

        $em->persist($page);
        $em->flush();

        $this->pageId = $page->id;

        $site = self::getContainer()->get(SiteRegistry::class)->get('localhost.dev');
        $this->cachePath = self::getContainer()->get(StaticAppGenerator::class)->getCacheDir($site).'/'.$page->slug.'.html';
        foreach (CompressionAlgorithm::fileSuffixes() as $suffix) {
            new Filesystem()->dumpFile($this->cachePath.$suffix, 'The previous article content.');
        }

        $this->transport()->reset();

        return $page;
    }

    private function assertCacheRemoved(): void
    {
        foreach (CompressionAlgorithm::fileSuffixes() as $suffix) {
            self::assertFileDoesNotExist($this->cachePath.$suffix, 'The redirect must not reach an old cache file while the worker is pending.');
        }
    }

    private function refresh(): void
    {
        self::getContainer()->get(PageCacheRefreshHandler::class)(new PageCacheRefreshMessage((int) $this->pageId));
    }

    private function transport(): InMemoryTransport
    {
        $senders = self::getContainer()->get('messenger.senders_locator')->getSenders(new Envelope(new PageCacheRefreshMessage(0)));
        $transport = iterator_to_array($senders)['page_refresh'];
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }
}
