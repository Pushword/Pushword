<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Sync;

use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Sync\MediaSync;
use Pushword\Flat\Sync\PageSync;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class CachingOptimizationsTest extends KernelTestCase
{
    private MediaSync $mediaSync;

    private PageSync $pageSync;

    private Filesystem $filesystem;

    private string $contentDir;

    private ?string $isolatedContentDir = null;

    /** @var string[] */
    private array $createdFiles = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $cacheFile = getenv('PUSHWORD_TEST_DB_CACHE_FILE');
        $dbUrl = getenv('PUSHWORD_TEST_DATABASE_URL');
        if (false !== $cacheFile && '' !== $cacheFile && false !== $dbUrl && file_exists($cacheFile)) {
            $dbPath = preg_replace('#^sqlite:///+#', '/', $dbUrl);
            if (null !== $dbPath && file_exists($dbPath)) {
                copy($cacheFile, $dbPath);
            }
        }
    }

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->filesystem = new Filesystem();

        $this->isolatedContentDir = sys_get_temp_dir().'/pushword-caching-test-'.getmypid().'-'.mt_rand();
        $this->filesystem->mkdir($this->isolatedContentDir);

        $container = self::getContainer();

        $siteRegistry = $container->get(SiteRegistry::class);
        $siteConfig = $siteRegistry->switchSite('localhost.dev')->get();
        $siteConfig->setCustomProperty('flat_content_dir', $this->isolatedContentDir);

        $contentDirFinder = $container->get(FlatFileContentDirFinder::class);
        $ref = new ReflectionProperty(FlatFileContentDirFinder::class, 'contentDir');
        $ref->setValue($contentDirFinder, []);

        $this->contentDir = $contentDirFinder->get('localhost.dev');

        /** @var MediaSync $mediaSync */
        $mediaSync = $container->get(MediaSync::class);
        $this->mediaSync = $mediaSync;

        /** @var PageSync $pageSync */
        $pageSync = $container->get(PageSync::class);
        $this->pageSync = $pageSync;

        $this->pageSync->export('localhost.dev', true, $this->contentDir);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            @unlink($file);
        }

        if (null !== $this->isolatedContentDir) {
            $this->filesystem->remove($this->isolatedContentDir);
        }

        parent::tearDown();
    }

    public function testMediaIndexCacheReusedAcrossHosts(): void
    {
        // Call mustImport for first host — populates the cache
        $this->mediaSync->mustImport('localhost.dev');

        // Access the private cache via reflection to verify it's populated
        $ref = new ReflectionProperty(MediaSync::class, 'mediaIndexCache');
        $cacheAfterFirst = $ref->getValue($this->mediaSync);

        self::assertIsArray($cacheAfterFirst, 'mediaIndexCache should be populated after first mustImport call');

        // Call mustImport for second host
        $this->mediaSync->mustImport('pushword.piedweb.com');

        // Cache reference should be the same array (not rebuilt)
        $cacheAfterSecond = $ref->getValue($this->mediaSync);
        self::assertSame($cacheAfterFirst, $cacheAfterSecond, 'mediaIndexCache should be reused across hosts, not rebuilt');
    }

    public function testMediaIndexCacheContainsAllMedia(): void
    {
        /** @var MediaRepository $mediaRepo */
        $mediaRepo = self::getContainer()->get(MediaRepository::class);
        $allMedia = $mediaRepo->findAll();

        // Trigger cache population
        $this->mediaSync->mustImport('localhost.dev');

        $ref = new ReflectionProperty(MediaSync::class, 'mediaIndexCache');
        /** @var array<string, Media> $cache */
        $cache = $ref->getValue($this->mediaSync);

        self::assertCount(\count($allMedia), $cache, 'Cache should contain all media entities');

        foreach ($allMedia as $media) {
            self::assertArrayHasKey($media->getFileName(), $cache);
            self::assertSame($media, $cache[$media->getFileName()]);
        }
    }

    public function testPageExportTimestampSkipBeforeContentGeneration(): void
    {
        // Force export to create all files with correct timestamps
        $this->pageSync->export('localhost.dev', true, $this->contentDir);

        // Non-forced export should skip all pages via timestamp fast path
        $this->pageSync->export('localhost.dev', false, $this->contentDir);

        self::assertSame(0, $this->pageSync->getExportedCount(), 'No pages should be exported when files are up to date');
        self::assertGreaterThan(0, $this->pageSync->getExportSkippedCount(), 'Pages should be skipped via timestamp check');
    }

    public function testPageExportTimestampSkipDoesNotMissChanges(): void
    {
        // Force export
        $this->pageSync->export('localhost.dev', true, $this->contentDir);

        // Modify a page in DB so content differs, and backdate the file
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $page = $em->getRepository(Page::class)->findOneBy(['host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $page);

        $filePath = $this->contentDir.'/'.$page->getSlug().'.md';
        self::assertFileExists($filePath);

        // Change DB content and set file mtime to the past
        $page->setMainContent($page->getMainContent()."\n<!-- modified for cache test -->");
        $em->flush();
        touch($filePath, $page->updatedAt->getTimestamp() - 100); // @phpstan-ignore method.nonObject

        // Non-forced export should detect the older file and re-export
        $this->pageSync->export('localhost.dev', false, $this->contentDir);

        self::assertGreaterThan(0, $this->pageSync->getExportedCount(), 'Changed page should be exported when file is older than DB');
    }

    public function testPageExportContentCheckCatchesDiffWhenTimestampMatches(): void
    {
        // Force export creates all files
        $this->pageSync->export('localhost.dev', true, $this->contentDir);

        // Corrupt a file's content but keep the same mtime as page.updatedAt
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $page = $em->getRepository(Page::class)->findOneBy(['host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $page);

        $filePath = $this->contentDir.'/'.$page->getSlug().'.md';
        self::assertFileExists($filePath);

        $originalMtime = (int) filemtime($filePath);
        file_put_contents($filePath, 'corrupted content');
        // Restore mtime so the timestamp check says "file is newer" and would skip
        touch($filePath, $originalMtime);

        // Force export should bypass timestamp and catch the content diff
        $this->pageSync->export('localhost.dev', true, $this->contentDir);

        self::assertGreaterThan(0, $this->pageSync->getExportedCount(), 'Force export should detect content diff even when timestamp matches');
    }
}
