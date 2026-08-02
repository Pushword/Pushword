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
final class CleanMissingMediaCommandTest extends KernelTestCase
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

    public function testDryRunListsMissingMediaWithoutRemovingIt(): void
    {
        self::bootKernel();
        $mediaId = $this->createMediaWithoutItsFile('clean-missing-dry-run.png');

        $commandTester = $this->runCleanMissingCommand(['--dry-run' => true]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('with missing file(s)', $output);
        self::assertStringContainsString('clean-missing-dry-run.png', $output);
        self::assertSame(0, $commandTester->getStatusCode());

        $em = $this->getEntityManager();
        $em->clear();
        self::assertNotNull($em->find(Media::class, $mediaId), 'Dry-run must leave the entry in place');
    }

    public function testRemovesMediaWhoseFileIsGone(): void
    {
        self::bootKernel();
        $mediaId = $this->createMediaWithoutItsFile('clean-missing-removed.png');

        $commandTester = $this->runCleanMissingCommand([]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Removed', $output);
        self::assertSame(0, $commandTester->getStatusCode());

        $em = $this->getEntityManager();
        $em->clear();
        self::assertNull($em->find(Media::class, $mediaId), 'Entry with a missing file should be removed');

        $this->createdMediaIds = [];
    }

    public function testReportsNothingToDoWhenEveryFileIsPresent(): void
    {
        self::bootKernel();

        $commandTester = $this->runCleanMissingCommand([]);

        self::assertStringContainsString('No missing media found', $commandTester->getDisplay());
        self::assertSame(0, $commandTester->getStatusCode());
    }

    /** @param array<string, mixed> $options */
    private function runCleanMissingCommand(array $options): CommandTester
    {
        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $commandTester = new CommandTester($application->find('pw:media:clean-missing'));
        $commandTester->execute($options);

        return $commandTester;
    }

    private function getEntityManager(): EntityManager
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        return $em;
    }

    /**
     * The entity needs a real file to be hashed at persist time; deleting it
     * afterwards is what leaves the dangling row the command looks for.
     */
    private function createMediaWithoutItsFile(string $fileName): int
    {
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $mediaDir = $this->getMediaDir();

        $filesystem = new Filesystem();
        $filesystem->copy($mediaDir.'/piedweb-logo.png', $mediaDir.'/'.$fileName);

        $em = $this->getEntityManager();

        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setStoreIn($mediaDir);
        $media->setMimeType('image/png');
        $media->setDimensions([1000, 1000]);
        $media->setFileName($fileName);
        $media->setAlt('Clean Missing Media');
        $media->setHash();

        $em->persist($media);
        $em->flush();

        $this->createdMediaIds[] = (int) $media->id;

        $filesystem->remove($mediaDir.'/'.$fileName);

        return (int) $media->id;
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
