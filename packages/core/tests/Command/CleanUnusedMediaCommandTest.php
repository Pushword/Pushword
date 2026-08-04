<?php

namespace Pushword\Core\Tests\Command;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\MediaUsageRepository;
use Pushword\Core\Tests\PathTrait;

use function Safe\json_decode;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

#[Group('integration')]
final class CleanUnusedMediaCommandTest extends KernelTestCase
{
    use PathTrait;

    private const string HOST = 'localhost.dev';

    /** Holds every media the command must not touch, by referencing it. */
    private const string GUARD_SLUG = 'clean-unused-guard-fixture';

    private const string ORPHAN_FILE_NAME = 'clean-unused-orphan.png';

    private EntityManagerInterface $entityManager;

    private MediaUsageRepository $mediaUsageRepository;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->ensureMediaFileExists();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->mediaUsageRepository = self::getContainer()->get(MediaUsageRepository::class);
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    public function testItRefusesToRunAgainstAUsageTableThatWasNeverBuilt(): void
    {
        $this->mediaUsageRepository->deleteAll();

        $commandTester = $this->executeCleanUnused(['--format' => 'text']);

        self::assertSame(1, $commandTester->getStatusCode());
        self::assertStringContainsString('pw:media:usage:rebuild', $commandTester->getDisplay());
    }

    public function testTheDryRunListsWithoutRemoving(): void
    {
        $orphanId = $this->createOrphanMedia();
        $this->rebuildUsage();

        $commandTester = $this->executeCleanUnused(['--format' => 'text']);

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertStringContainsString(self::ORPHAN_FILE_NAME, $commandTester->getDisplay());
        // Never call it "unused" without saying what that leaves out.
        self::assertStringContainsString('nothing scans templates', $commandTester->getDisplay());

        $this->entityManager->clear();
        self::assertNotNull($this->entityManager->find(Media::class, $orphanId), 'The dry run must leave the row in place');
    }

    public function testForceRemovesTheMediaNoPageReferences(): void
    {
        $orphanId = $this->createOrphanMedia();
        $guardedIds = $this->guardEveryOtherUnreferencedMedia();
        $this->rebuildUsage();

        $commandTester = $this->executeCleanUnused(['--force' => true, '--format' => 'text']);

        self::assertSame(0, $commandTester->getStatusCode());

        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(Media::class, $orphanId));
        self::assertFileDoesNotExist($this->getMediaDir().'/'.self::ORPHAN_FILE_NAME);

        foreach ($guardedIds as $guardedId) {
            self::assertNotNull($this->entityManager->find(Media::class, $guardedId), 'A referenced media must survive');
        }
    }

    public function testItEmitsOneJsonDocumentForAnAgent(): void
    {
        $this->createOrphanMedia();
        $this->rebuildUsage();

        $commandTester = $this->executeCleanUnused(['--format' => 'agent']);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(trim($commandTester->getDisplay()), true);

        self::assertSame('pw:media:clean-unused', $decoded['tool']);
        self::assertTrue($decoded['dry_run']);
        self::assertSame(0, $decoded['removed']);
        self::assertGreaterThan(0, $decoded['found']);
    }

    /** @param array<string, mixed> $options */
    private function executeCleanUnused(array $options): CommandTester
    {
        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $commandTester = new CommandTester($application->find('pw:media:clean-unused'));
        $commandTester->execute($options);

        return $commandTester;
    }

    private function rebuildUsage(): void
    {
        $application = new Application(self::$kernel); // @phpstan-ignore-line
        new CommandTester($application->find('pw:media:usage:rebuild'))->execute(['--format' => 'agent']);
    }

    private function createOrphanMedia(): int
    {
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $mediaDir = $this->getMediaDir();

        new Filesystem()->copy($mediaDir.'/piedweb-logo.png', $mediaDir.'/'.self::ORPHAN_FILE_NAME, true);

        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setStoreIn($mediaDir);
        $media->setMimeType('image/png');
        $media->setDimensions([1000, 1000]);
        $media->setFileName(self::ORPHAN_FILE_NAME);
        $media->setAlt('Clean unused orphan');
        $media->setHash();

        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return (int) $media->id;
    }

    /**
     * `--force` deletes every media no page references, and the fixtures ship some
     * that other tests need (the logo lives in a template). One page referencing them
     * all is what makes this test's blast radius exactly the media it created.
     *
     * @return list<int>
     */
    private function guardEveryOtherUnreferencedMedia(): array
    {
        /** @var MediaRepository $mediaRepository */
        $mediaRepository = self::getContainer()->get(MediaRepository::class);

        $guarded = [];
        $content = '';
        foreach ($mediaRepository->findNotReferencedByAPage() as $media) {
            if (self::ORPHAN_FILE_NAME === $media->getFileName()) {
                continue;
            }

            $guarded[] = (int) $media->id;
            $content .= '![](/media/default/'.$media->getFileName().')'."\n";
        }

        $page = new Page();
        $page->slug = self::GUARD_SLUG;
        $page->host = self::HOST;
        $page->h1 = 'Clean unused guard';
        $page->mainContent = $content;

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        return $guarded;
    }

    private function cleanUp(): void
    {
        try {
            $this->entityManager->clear();

            $page = $this->entityManager->getRepository(Page::class)
                ->findOneBy(['slug' => self::GUARD_SLUG, 'host' => self::HOST]);
            if (null !== $page) {
                $this->entityManager->remove($page);
            }

            $media = $this->entityManager->getRepository(Media::class)
                ->findOneBy(['fileName' => self::ORPHAN_FILE_NAME]);
            if (null !== $media) {
                $this->entityManager->remove($media);
            }

            $this->entityManager->flush();
        } catch (Throwable) {
        }

        new Filesystem()->remove($this->getMediaDir().'/'.self::ORPHAN_FILE_NAME);
        $this->rebuildUsage();
    }
}
