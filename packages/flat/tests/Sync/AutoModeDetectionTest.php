<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Sync;

use Doctrine\ORM\EntityManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Flat\FlatFileContentDirFinder;
use Pushword\Flat\Sync\MediaSync;
use Pushword\Flat\Sync\PageSync;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class AutoModeDetectionTest extends KernelTestCase
{
    private EntityManager $em;

    private PageSync $pageSync;

    private MediaSync $mediaSync;

    private string $contentDir;

    private Filesystem $filesystem;

    /** @var string[] */
    private array $createdFiles = [];

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->filesystem = new Filesystem();

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->em = $em;

        /** @var PageSync $pageSync */
        $pageSync = self::getContainer()->get(PageSync::class);
        $this->pageSync = $pageSync;

        /** @var MediaSync $mediaSync */
        $mediaSync = self::getContainer()->get(MediaSync::class);
        $this->mediaSync = $mediaSync;

        /** @var FlatFileContentDirFinder $contentDirFinder */
        $contentDirFinder = self::getContainer()->get(FlatFileContentDirFinder::class);
        $this->contentDir = $contentDirFinder->get('localhost.dev');

        // Export so flat files match DB
        $this->pageSync->export('localhost.dev', true, $this->contentDir);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            @unlink($file);
        }

        foreach (['auto-detect-new-page', 'auto-detect-future'] as $slug) {
            $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => $slug, 'host' => 'localhost.dev']);
            if ($page instanceof Page) {
                $this->em->remove($page);
            }
        }

        $this->em->flush();

        parent::tearDown();
    }

    private function createMd(string $fileName, string $content, ?int $mtime = null): void
    {
        $path = $this->contentDir.'/'.$fileName;
        $this->filesystem->dumpFile($path, $content);
        if (null !== $mtime) {
            touch($path, $mtime);
        }

        $this->createdFiles[] = $path;
    }

    public function testAutoModeChoosesImportWhenFileIsNewer(): void
    {
        // Create a new .md file with future mtime — guarantees mustImport
        $this->createMd('auto-detect-future.md', "---\nh1: 'Future Page'\n---\n\nFuture content", time() + 200);

        self::assertTrue($this->pageSync->mustImport('localhost.dev'), 'mustImport should return true when file is newer');
    }

    public function testAutoModeChoosesExportWhenDbIsNewer(): void
    {
        // Import then export to fully synchronize
        $this->pageSync->import('localhost.dev');
        $this->pageSync->export('localhost.dev', true, $this->contentDir);

        // After a full round-trip, files should be in sync with DB
        self::assertFalse($this->pageSync->mustImport('localhost.dev'), 'mustImport should return false when files match DB');
    }

    public function testAutoModeChoosesImportForNewFile(): void
    {
        // Export first
        $this->pageSync->export('localhost.dev', true, $this->contentDir);

        // Create a new .md file with no DB match
        $this->createMd('auto-detect-new-page.md', "---\nh1: 'Auto Detect New'\n---\n\nNew page content", time() + 100);

        self::assertTrue($this->pageSync->mustImport('localhost.dev'), 'mustImport should return true for new .md file with no DB match');
    }

    public function testAutoModeIgnoresNonMdFiles(): void
    {
        // Export first
        $this->pageSync->export('localhost.dev', true, $this->contentDir);

        // Create a .txt file (not .md) — should NOT influence detection
        $txtPath = $this->contentDir.'/not-a-page.txt';
        $this->filesystem->dumpFile($txtPath, 'Just a text file');
        touch($txtPath, time() + 200);
        $this->createdFiles[] = $txtPath;

        // mustImport should still be false (only .md files matter for page detection)
        self::assertFalse($this->pageSync->mustImport('localhost.dev'), 'mustImport should return false — .txt files are ignored');
    }

    public function testMediaAutoModeDetectsNewFile(): void
    {
        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        // Create new media file in content dir
        $mediaPath = $this->contentDir.'/media';
        $this->filesystem->mkdir($mediaPath);
        $newFile = $mediaPath.'/auto-detect-test.txt';
        $this->filesystem->dumpFile($newFile, 'new media content');
        $this->createdFiles[] = $newFile;

        self::assertTrue($this->mediaSync->mustImport('localhost.dev'), 'mustImport should return true for new media file');

        // Cleanup dir
        @rmdir($mediaPath);
    }

    public function testMediaAutoModeDetectsHashChange(): void
    {
        /** @var string $mediaDir */
        $mediaDir = self::getContainer()->getParameter('pw.media_dir');

        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        // Create media in DB with hash
        $testPath = $mediaDir.'/auto-detect-hash.txt';
        file_put_contents($testPath, 'original content');

        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setFileName('auto-detect-hash.txt');
        $media->setAlt('Auto Detect Hash');
        $media->setMimeType('text/plain');
        $media->setSize(16);
        $media->setStoreIn($mediaDir);
        $media->setHash((string) sha1_file($testPath, true));

        $this->em->persist($media);
        $this->em->flush();

        // Modify file content (hash will differ)
        file_put_contents($testPath, 'modified content that is different');

        self::assertTrue($this->mediaSync->mustImport('localhost.dev'), 'mustImport should return true when file hash differs from DB');

        // Cleanup
        $this->em->remove($media);
        $this->em->flush();
        @unlink($testPath);
    }
}
