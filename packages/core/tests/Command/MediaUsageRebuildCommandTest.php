<?php

namespace Pushword\Core\Tests\Command;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\MediaUsage;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaUsageRepository;

use function Safe\json_decode;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class MediaUsageRebuildCommandTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    private const string SLUG = 'media-usage-rebuild-fixture';

    private EntityManagerInterface $entityManager;

    private MediaUsageRepository $mediaUsageRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->mediaUsageRepository = self::getContainer()->get(MediaUsageRepository::class);
        $this->removeFixturePage();
    }

    protected function tearDown(): void
    {
        $this->removeFixturePage();
        $this->executeRebuild(['--format' => 'agent']);
        parent::tearDown();
    }

    public function testItRebuildsWhatWasWipedBehindTheListenerBack(): void
    {
        $pageId = (int) $this->persistPage()->id;

        // The shape the command exists for: rows gone without a page write to notice.
        $this->mediaUsageRepository->deleteAll();
        self::assertSame([], $this->mediaUsageRepository->findMediaIdsForPage($pageId));

        $commandTester = $this->executeRebuild(['--format' => 'text']);

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertStringContainsString('page(s) scanned', $commandTester->getDisplay());
        self::assertStringContainsString('referenced by no page', $commandTester->getDisplay());

        self::assertSame(
            [MediaUsage::SOURCE_CONTENT],
            $this->mediaUsageRepository->findSourcesByPageForMedia($this->getMediaId('1.jpg'))[$pageId] ?? [],
        );
    }

    public function testItEmitsOneJsonDocumentForAnAgent(): void
    {
        $this->persistPage();

        $commandTester = $this->executeRebuild(['--format' => 'agent']);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(trim($commandTester->getDisplay()), true);

        self::assertSame('pw:media:usage:rebuild', $decoded['tool']);
        self::assertSame('done', $decoded['result']);
        self::assertIsInt($decoded['pages']);
        self::assertGreaterThan(0, $decoded['usages']);
        self::assertArrayHasKey('media_not_referenced_by_a_page', $decoded);
    }

    /** @param array<string, mixed> $options */
    private function executeRebuild(array $options): CommandTester
    {
        $application = new Application(self::$kernel); // @phpstan-ignore-line
        $commandTester = new CommandTester($application->find('pw:media:usage:rebuild'));
        $commandTester->execute($options);

        return $commandTester;
    }

    private function persistPage(): Page
    {
        $page = new Page();
        $page->slug = self::SLUG;
        $page->host = self::HOST;
        $page->h1 = 'Media usage rebuild fixture';
        $page->mainContent = 'Look ![](/media/default/1.jpg)';

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        return $page;
    }

    private function getMediaId(string $fileName): int
    {
        $media = $this->entityManager->getRepository(Media::class)->findOneBy(['fileName' => $fileName]);
        self::assertInstanceOf(Media::class, $media, 'The fixtures are expected to hold '.$fileName);

        return (int) $media->id;
    }

    private function removeFixturePage(): void
    {
        $page = $this->entityManager->getRepository(Page::class)
            ->findOneBy(['slug' => self::SLUG, 'host' => self::HOST]);

        if (null !== $page) {
            $this->entityManager->remove($page);
            $this->entityManager->flush();
        }
    }
}
