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
final class NormalizeMimeTypeCommandTest extends KernelTestCase
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

    public function testConvertsLegacyJpgMimeType(): void
    {
        self::bootKernel();
        $mediaId = $this->createMediaWithLegacyMimeType('normalize-mime-legacy.png');

        $commandTester = $this->runNormalizeCommand();

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Normalizing', $output);
        self::assertStringContainsString('Successfully normalized', $output);
        self::assertSame(0, $commandTester->getStatusCode());

        $em = $this->getEntityManager();
        $em->clear();

        $media = $em->find(Media::class, $mediaId);
        self::assertNotNull($media);
        self::assertSame('image/jpeg', $media->getMimeType());
    }

    public function testReportsNothingToDoWhenAlreadyNormalized(): void
    {
        self::bootKernel();

        // The first run clears whatever the fixtures left behind, so the second
        // one is guaranteed to hit the early-return branch.
        $this->runNormalizeCommand();
        $commandTester = $this->runNormalizeCommand();

        self::assertStringContainsString('Database is already normalized', $commandTester->getDisplay());
        self::assertSame(0, $commandTester->getStatusCode());
    }

    private function runNormalizeCommand(): CommandTester
    {
        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $commandTester = new CommandTester($application->find('pw:media:normalize-mime-type'));
        $commandTester->execute([]);

        return $commandTester;
    }

    private function getEntityManager(): EntityManager
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        return $em;
    }

    /**
     * setMimeType() normalizes on the way in, so the legacy value only exists in
     * rows written before that setter did — reproduce it with a direct UPDATE.
     */
    private function createMediaWithLegacyMimeType(string $fileName): int
    {
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $mediaDir = $this->getMediaDir();

        new Filesystem()->copy($mediaDir.'/piedweb-logo.png', $mediaDir.'/'.$fileName);

        $em = $this->getEntityManager();

        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setStoreIn($mediaDir);
        $media->setMimeType('image/png');
        $media->setDimensions([1000, 1000]);
        $media->setFileName($fileName);
        $media->setAlt('Normalize Mime Legacy');
        $media->setHash();

        $em->persist($media);
        $em->flush();

        $mediaId = (int) $media->id;
        $this->createdMediaIds[] = $mediaId;

        $em->createQuery('UPDATE '.Media::class.' m SET m.mimeType = :mimeType WHERE m.id = :id')
            ->setParameter('mimeType', 'image/jpg')
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
