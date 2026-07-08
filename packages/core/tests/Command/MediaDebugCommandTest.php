<?php

namespace Pushword\Core\Tests\Command;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

#[Group('integration')]
final class MediaDebugCommandTest extends KernelTestCase
{
    use PathTrait;

    /** @var int[] media IDs to clean up after each test */
    private array $createdMediaIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureMediaFileExists();
        $this->createdMediaIds = [];
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    public function testFindsMediaByHistoricalNameInTable(): void
    {
        self::bootKernel();
        $this->createMediaWithHistory('media-debug-current.png', 'media-debug-old.png');

        $commandTester = $this->runDebugCommand(['search' => 'media-debug-old.png']);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Media found!', $output);
        self::assertStringContainsString('media-debug-current.png', $output);
        self::assertStringContainsString('fileNameHistory', $output);
        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testFindsMediaByHistoricalNameInJson(): void
    {
        self::bootKernel();
        $this->createMediaWithHistory('media-debug-current.png', 'media-debug-old.png');

        $commandTester = $this->runDebugCommand(['search' => 'media-debug-old.png', '--json' => true]);

        /** @var array<string, string|null> $result */
        $result = json_decode(trim($commandTester->getDisplay()), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('media-debug-current.png', $result['media-debug-old.png'] ?? null);
        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testFindsMediaByExactCurrentName(): void
    {
        self::bootKernel();
        $this->createMediaWithHistory('media-debug-current.png', 'media-debug-old.png');

        $commandTester = $this->runDebugCommand(['search' => 'media-debug-current.png']);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Media found!', $output);
        self::assertStringContainsString('media-debug-current.png', $output);
        self::assertSame(0, $commandTester->getStatusCode());
    }

    public function testReportsNotFoundForUnknownName(): void
    {
        self::bootKernel();

        $commandTester = $this->runDebugCommand(['search' => 'media-debug-does-not-exist.png']);

        self::assertStringContainsString('No media found', $commandTester->getDisplay());
        self::assertSame(1, $commandTester->getStatusCode());
    }

    public function testMultipleTermsReportEachAndFailWhenAnyMissing(): void
    {
        self::bootKernel();
        $this->createMediaWithHistory('media-debug-current.png', 'media-debug-old.png');

        $commandTester = $this->runDebugCommand([
            'search' => "media-debug-old.png\nmedia-debug-missing.png",
        ]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Media found!', $output);
        self::assertStringContainsString('No media found for "media-debug-missing.png"', $output);
        // Exit code 1 because at least one term was missing, even though another was found.
        self::assertSame(1, $commandTester->getStatusCode());
    }

    /** @param array<string, mixed> $args */
    private function runDebugCommand(array $args): CommandTester
    {
        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $commandTester = new CommandTester($application->find('pw:media:debug'));
        $commandTester->execute($args);

        return $commandTester;
    }

    private function getEntityManager(): EntityManager
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        return $em;
    }

    private function createMediaWithHistory(string $fileName, string $historicalName): void
    {
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $mediaDir = $this->getMediaDir();

        $this->ensureMediaFileExists();
        new Filesystem()->copy($mediaDir.'/piedweb-logo.png', $mediaDir.'/'.$fileName);

        $em = $this->getEntityManager();

        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setStoreIn($mediaDir);
        $media->setMimeType('image/png');
        $media->setDimensions([1000, 1000]);
        $media->setFileName($fileName);
        $media->setAlt('Media Debug History');
        $media->addFileNameToHistory($historicalName);
        $media->setHash();

        $em->persist($media);
        $em->flush();

        $this->createdMediaIds[] = (int) $media->id;
    }

    private function cleanupTestData(): void
    {
        try {
            $em = $this->getEntityManager();
            if (! $em->isOpen()) {
                return;
            }

            $em->clear();

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
