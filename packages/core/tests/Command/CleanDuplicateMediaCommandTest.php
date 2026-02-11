<?php

namespace Pushword\Core\Tests\Command;

use Doctrine\ORM\EntityManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

#[Group('integration')]
final class CleanDuplicateMediaCommandTest extends KernelTestCase
{
    use PathTrait;

    /** @var int[] media IDs to clean up after each test */
    private array $createdMediaIds = [];

    /** @var int[] page IDs to clean up after each test */
    private array $createdPageIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureMediaFileExists();
        $this->createdMediaIds = [];
        $this->createdPageIds = [];
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    public function testNoDuplicatesFound(): void
    {
        self::bootKernel();

        $commandTester = $this->runCommand([]);

        self::assertStringContainsString('No duplicate media found', $commandTester->getDisplay());
        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testDryRunShowsDuplicatesWithoutModifying(): void
    {
        self::bootKernel();
        $em = $this->getEntityManager();
        $duplicateId = $this->createDuplicateMedia($em, 'piedweb-logo-dup.png', 'Dup For DryRun');

        $commandTester = $this->runCommand(['--dry-run' => true]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('duplicate(s) found', $output);
        self::assertStringContainsString('Keep #', $output);
        self::assertStringContainsString('Remove #', $output);
        self::assertSame(0, $commandTester->getStatusCode());

        // Verify DB untouched
        $em->clear();
        self::assertNotNull($em->find(Media::class, $duplicateId), 'Duplicate should still exist after dry-run');
    }

    public function testMergesDuplicatesAndTransfersPageReferences(): void
    {
        self::bootKernel();
        $em = $this->getEntityManager();
        $duplicateId = $this->createDuplicateMedia($em, 'piedweb-logo-dup.png', 'Dup For Merge');
        $pageId = $this->createPageWithMainImage($em, $duplicateId);

        $commandTester = $this->runCommand([]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Merged', $output);
        self::assertSame(0, $commandTester->getStatusCode());

        $em->clear();

        // Duplicate entity removed
        self::assertNull($em->find(Media::class, $duplicateId), 'Duplicate should be removed');

        // Canonical got the duplicate's filename in history
        $canonical = $em->getRepository(Media::class)->findOneBy(['fileName' => 'piedweb-logo.png']);
        self::assertNotNull($canonical);
        self::assertTrue($canonical->hasFileNameInHistory('piedweb-logo-dup.png'));

        // Page mainImage transferred to canonical
        $page = $em->find(Page::class, $pageId);
        self::assertNotNull($page);
        self::assertNotNull($page->getMainImage());
        self::assertSame($canonical->id, $page->getMainImage()->id, 'Page mainImage should point to canonical');

        // Canonical file still on disk
        self::assertFileExists($this->getMediaDir().'/piedweb-logo.png');

        // Duplicate file cleaned up by MediaStorageListener
        self::assertFileDoesNotExist($this->getMediaDir().'/piedweb-logo-dup.png');

        // Mark as already cleaned (remove skipped in tearDown)
        $this->createdMediaIds = [];
        $em->remove($page);
        $em->flush();

        $this->createdPageIds = [];
    }

    public function testFileNameHistoryIsTransferred(): void
    {
        self::bootKernel();
        $em = $this->getEntityManager();
        $duplicateId = $this->createDuplicateMedia($em, 'piedweb-logo-dup.png', 'Dup With History');

        // Add history entries to the duplicate
        $duplicate = $em->find(Media::class, $duplicateId);
        self::assertNotNull($duplicate);
        $duplicate->addFileNameToHistory('old-name-1.png');
        $duplicate->addFileNameToHistory('old-name-2.png');

        $em->flush();

        $this->runCommand([]);

        $em->clear();

        $canonical = $em->getRepository(Media::class)->findOneBy(['fileName' => 'piedweb-logo.png']);
        self::assertNotNull($canonical);

        // All history entries transferred
        self::assertTrue($canonical->hasFileNameInHistory('piedweb-logo-dup.png'), 'Duplicate filename in history');
        self::assertTrue($canonical->hasFileNameInHistory('old-name-1.png'), 'History entry 1 transferred');
        self::assertTrue($canonical->hasFileNameInHistory('old-name-2.png'), 'History entry 2 transferred');

        $this->createdMediaIds = [];
    }

    public function testMergesThreeOrMoreDuplicates(): void
    {
        self::bootKernel();
        $em = $this->getEntityManager();

        $dup1Id = $this->createDuplicateMedia($em, 'piedweb-logo-dup1.png', 'Dup1 Triple');
        $dup2Id = $this->createDuplicateMedia($em, 'piedweb-logo-dup2.png', 'Dup2 Triple');

        $commandTester = $this->runCommand([]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('2 duplicate(s) found in 1 group(s)', $output);
        self::assertStringContainsString('Merged 2 duplicate media(s)', $output);
        self::assertSame(0, $commandTester->getStatusCode());

        $em->clear();

        self::assertNull($em->find(Media::class, $dup1Id), 'First duplicate removed');
        self::assertNull($em->find(Media::class, $dup2Id), 'Second duplicate removed');

        $canonical = $em->getRepository(Media::class)->findOneBy(['fileName' => 'piedweb-logo.png']);
        self::assertNotNull($canonical);
        self::assertTrue($canonical->hasFileNameInHistory('piedweb-logo-dup1.png'));
        self::assertTrue($canonical->hasFileNameInHistory('piedweb-logo-dup2.png'));

        $this->createdMediaIds = [];
    }

    /** @param array<string, mixed> $options */
    private function runCommand(array $options): CommandTester
    {
        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $commandTester = new CommandTester($application->find('pw:media:clean-duplicates'));
        $commandTester->execute($options);

        return $commandTester;
    }

    private function getEntityManager(): EntityManager
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        return $em;
    }

    private function createDuplicateMedia(EntityManager $em, string $fileName, string $alt): int
    {
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $mediaDir = $this->getMediaDir();

        $this->ensureMediaFileExists();
        new Filesystem()->copy($mediaDir.'/piedweb-logo.png', $mediaDir.'/'.$fileName);

        $original = $em->getRepository(Media::class)->findOneBy(['fileName' => 'piedweb-logo.png']);
        self::assertNotNull($original);

        $duplicate = new Media();
        $duplicate->setProjectDir($projectDir);
        $duplicate->setStoreIn($mediaDir);
        $duplicate->setMimeType('image/png');
        $duplicate->setSize($original->getSize());
        $duplicate->setDimensions([1000, 1000]);
        $duplicate->setFileName($fileName);
        $duplicate->setAlt($alt);
        $duplicate->setHash();

        $em->persist($duplicate);
        $em->flush();

        $this->createdMediaIds[] = (int) $duplicate->id;

        return (int) $duplicate->id;
    }

    private function createPageWithMainImage(EntityManager $em, int $mediaId): int
    {
        $media = $em->find(Media::class, $mediaId);
        self::assertNotNull($media);

        $page = new Page();
        $page->setH1('Test Duplicate Media Page');
        $page->setSlug('test-dup-media-'.uniqid());
        $page->locale = 'en';
        $page->setMainImage($media);
        $page->setMainContent('Test page for duplicate media command.');

        $em->persist($page);
        $em->flush();

        $this->createdPageIds[] = (int) $page->id;

        return (int) $page->id;
    }

    private function cleanupTestData(): void
    {
        try {
            $em = $this->getEntityManager();
            if (! $em->isOpen()) {
                return;
            }

            $em->clear();

            foreach ($this->createdPageIds as $pageId) {
                $page = $em->find(Page::class, $pageId);
                if (null !== $page) {
                    $em->remove($page);
                }
            }

            $em->flush();

            foreach ($this->createdMediaIds as $mediaId) {
                $media = $em->find(Media::class, $mediaId);
                if (null !== $media) {
                    $em->remove($media);
                }
            }

            $em->flush();
        } catch (Throwable) {
        }
    }
}
