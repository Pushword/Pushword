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
final class NormalizeFileNameCommandTest extends KernelTestCase
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

    public function testDryRunListsRenamesWithoutApplyingThem(): void
    {
        self::bootKernel();
        $mediaId = $this->createMediaWithRawFileName('Norm FileName Test.png');

        $commandTester = $this->runNormalizeCommand(['--dry-run' => true]);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('need filename normalization', $output);
        self::assertStringContainsString('Norm FileName Test.png → norm-filename-test.png', $output);
        self::assertSame(0, $commandTester->getStatusCode());

        $em = $this->getEntityManager();
        $em->clear();

        $media = $em->find(Media::class, $mediaId);
        self::assertNotNull($media);
        self::assertSame('Norm FileName Test.png', $media->getFileName(), 'Dry-run must not rename');
    }

    public function testNormalizesFileName(): void
    {
        self::bootKernel();
        $mediaId = $this->createMediaWithRawFileName('Norm FileName Applied.png');

        $commandTester = $this->runNormalizeCommand([]);

        self::assertStringContainsString('Normalized', $commandTester->getDisplay());
        self::assertSame(0, $commandTester->getStatusCode());

        $em = $this->getEntityManager();
        $em->clear();

        $media = $em->find(Media::class, $mediaId);
        self::assertNotNull($media);
        self::assertSame('norm-filename-applied.png', $media->getFileName());
    }

    public function testReportsNothingToDoWhenEveryNameIsNormalized(): void
    {
        self::bootKernel();

        $commandTester = $this->runNormalizeCommand([]);

        self::assertStringContainsString('already normalized', $commandTester->getDisplay());
        self::assertSame(0, $commandTester->getStatusCode());
    }

    /** @param array<string, mixed> $options */
    private function runNormalizeCommand(array $options): CommandTester
    {
        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $commandTester = new CommandTester($application->find('pw:media:normalize-filenames'));
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
     * setFileName() slugifies, so a non-normalized name can only come from a row
     * written before it did — write it straight to the column, and put the file on
     * disk under that same raw name so the rename has something to move.
     */
    private function createMediaWithRawFileName(string $rawFileName): int
    {
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $mediaDir = $this->getMediaDir();

        $em = $this->getEntityManager();

        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setStoreIn($mediaDir);
        $media->setMimeType('image/png');
        $media->setDimensions([1000, 1000]);
        $media->setFileName('placeholder-'.uniqid().'.png');
        $media->setAlt('Normalize FileName');

        new Filesystem()->copy($mediaDir.'/piedweb-logo.png', $mediaDir.'/'.$media->getFileName());
        $media->setHash();

        $em->persist($media);
        $em->flush();

        $mediaId = (int) $media->id;
        $this->createdMediaIds[] = $mediaId;

        new Filesystem()->rename($mediaDir.'/'.$media->getFileName(), $mediaDir.'/'.$rawFileName, true);

        $em->createQuery('UPDATE '.Media::class.' m SET m.fileName = :fileName WHERE m.id = :id')
            ->setParameter('fileName', $rawFileName)
            ->setParameter('id', $mediaId)
            ->execute();
        $em->clear();

        return $mediaId;
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
