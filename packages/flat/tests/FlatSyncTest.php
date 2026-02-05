<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests;

use Doctrine\ORM\EntityManager;
use FilesystemIterator;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\FlatFileSync;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class FlatSyncTest extends KernelTestCase
{
    private string $contentDir;

    /** @var string[] Files in content dir before test (to restore on tearDown) */
    private array $preExistingFiles = [];

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $this->contentDir = $contentDirFinder->get('localhost.dev');

        $this->preExistingFiles = $this->listAllFiles($this->contentDir);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->cleanAllCreatedFiles();
        parent::tearDown();
    }

    public function testImportReplacesMarkdownLinks(): void
    {
        $this->cleanGlobalIndexBeforeTest();

        // Export existing pages first so the content dir has all .md files.
        // Without this, import() would delete all DB pages not found as files.
        /** @var FlatFileSync $sync */
        $sync = self::getContainer()->get(FlatFileSync::class);
        $sync->export('localhost.dev', $this->contentDir);

        $this->prepareFixtures();

        $sync->import('localhost.dev');

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $page = $em->getRepository(Page::class)->findOneBy(['slug' => 'test-link']);

        self::assertInstanceOf(Page::class, $page);
        self::assertStringContainsString('](/test-content)', $page->getMainContent());

        // Cleanup imported pages
        $testContent = $em->getRepository(Page::class)->findOneBy(['slug' => 'test-content']);
        if ($testContent instanceof Page) {
            $em->remove($testContent);
        }

        $em->remove($page);
        $em->flush();
    }

    private function prepareFixtures(): void
    {
        $filesystem = new Filesystem();

        $filesystem->mkdir($this->contentDir.'/media');
        $filesystem->copy(__DIR__.'/content/test-content.md', $this->contentDir.'/test-content.md', true);
        $filesystem->copy(__DIR__.'/content/test-link.md', $this->contentDir.'/test-link.md', true);
        $filesystem->copy(__DIR__.'/content/media/logo-test.png', $this->contentDir.'/media/logo-test.png', true);
        $filesystem->copy(__DIR__.'/content/media/index.csv', $this->contentDir.'/media/index.csv', true);
    }

    /**
     * Remove all files created during the test (export + fixtures), keeping pre-existing ones.
     */
    private function cleanAllCreatedFiles(): void
    {
        $fs = new Filesystem();

        // Remove test fixtures from media dir
        $fs->remove([
            $this->getMediaDir().'/logo-test.png',
            $this->getMediaDir().'/index.csv',
        ]);

        // Remove any files created during the test in the content dir
        $currentFiles = $this->listAllFiles($this->contentDir);
        $createdFiles = array_diff($currentFiles, $this->preExistingFiles);

        // Remove in reverse order so files are removed before their parent dirs
        foreach (array_reverse($createdFiles) as $file) {
            $fs->remove($file);
        }
    }

    private function cleanGlobalIndexBeforeTest(): void
    {
        new Filesystem()->remove($this->getMediaDir().'/index.csv');
    }

    private function getMediaDir(): string
    {
        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        return $mediaDir;
    }

    /**
     * @return string[] Absolute paths of all files and directories
     */
    private function listAllFiles(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $files[] = $item->getPathname();
        }

        /** @var string[] $files */
        return $files;
    }
}
