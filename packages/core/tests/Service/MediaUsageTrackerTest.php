<?php

namespace Pushword\Core\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\MediaUsage;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\MediaUsageRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The listener half of the feature: usage rows have to follow a page write without
 * anybody asking, or every consumer reads a stale table.
 */
#[Group('integration')]
final class MediaUsageTrackerTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    private const string SLUG = 'media-usage-tracker-fixture';

    private EntityManagerInterface $entityManager;

    private MediaUsageRepository $mediaUsageRepository;

    private Media $media;

    private Media $otherMedia;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->mediaUsageRepository = self::getContainer()->get(MediaUsageRepository::class);

        $this->media = $this->getMedia('1.jpg');
        $this->otherMedia = $this->getMedia('2.jpg');

        $this->removeFixturePage();
    }

    protected function tearDown(): void
    {
        $this->removeFixturePage();
        parent::tearDown();
    }

    public function testAPageWriteRecordsItsMediaWithTheirSource(): void
    {
        $pageId = (int) $this->persistPage('Look ![](/media/default/1.jpg)', $this->otherMedia)->id;

        self::assertSame(
            [MediaUsage::SOURCE_CONTENT],
            $this->mediaUsageRepository->findSourcesByPageForMedia((int) $this->media->id)[$pageId] ?? [],
        );
        self::assertSame(
            [MediaUsage::SOURCE_MAIN_IMAGE],
            $this->mediaUsageRepository->findSourcesByPageForMedia((int) $this->otherMedia->id)[$pageId] ?? [],
        );
    }

    public function testEditingTheContentToDropAnImageDropsItsUsageRow(): void
    {
        $page = $this->persistPage('Look ![](/media/default/1.jpg)');

        self::assertContains((int) $this->media->id, $this->mediaUsageRepository->findMediaIdsForPage((int) $page->id));

        $page->mainContent = 'Nothing left here.';
        $this->entityManager->flush();

        self::assertSame([], $this->mediaUsageRepository->findMediaIdsForPage((int) $page->id));
    }

    public function testAMediaUsedOnlyAsMainImageIsNotReportedUnreferenced(): void
    {
        $this->persistPage('No image in this body.', $this->media);

        /** @var MediaRepository $mediaRepository */
        $mediaRepository = self::getContainer()->get(MediaRepository::class);
        $unusedIds = array_map(static fn (Media $media): ?int => $media->id, $mediaRepository->findNotReferencedByAPage());

        self::assertNotContains($this->media->id, $unusedIds);
    }

    public function testRemovingThePageRemovesItsRows(): void
    {
        $page = $this->persistPage('Look ![](/media/default/1.jpg)');
        $pageId = (int) $page->id;

        $this->entityManager->remove($page);
        $this->entityManager->flush();

        self::assertSame([], $this->mediaUsageRepository->findMediaIdsForPage($pageId));
    }

    public function testTheMediaInheritsTheTagsOfThePagesUsingIt(): void
    {
        $page = $this->persistPage('Look ![](/media/default/1.jpg)');
        $page->setTags('lodge mountain');

        $this->entityManager->flush();

        self::assertSame(['lodge', 'mountain'], $this->readPageTags($this->media));
    }

    /** The whole point of the separate column: a derivation must not touch a human decision. */
    public function testInheritedTagsDoNotTouchTheMediaOwnTags(): void
    {
        $this->media->setTags('photo');
        $this->entityManager->flush();

        $page = $this->persistPage('Look ![](/media/default/1.jpg)');
        $page->setTags('mountain');

        $this->entityManager->flush();

        $this->entityManager->refresh($this->media);

        self::assertSame(['photo'], $this->media->getTagList());
        self::assertSame(['mountain'], $this->readPageTags($this->media));
    }

    public function testInheritedTagsAreDroppedWhenThePageStopsUsingTheMedia(): void
    {
        $page = $this->persistPage('Look ![](/media/default/1.jpg)');
        $page->setTags('mountain');

        $this->entityManager->flush();

        self::assertSame(['mountain'], $this->readPageTags($this->media));

        $page->mainContent = 'Nothing left here.';
        $this->entityManager->flush();

        self::assertSame([], $this->readPageTags($this->media));
    }

    public function testClearingThePageTagsClearsWhatTheMediaInheritedFromIt(): void
    {
        $page = $this->persistPage('Look ![](/media/default/1.jpg)');
        $page->setTags('mountain');

        $this->entityManager->flush();

        self::assertSame(['mountain'], $this->readPageTags($this->media));

        $page->setTags('');
        $this->entityManager->flush();

        self::assertSame([], $this->readPageTags($this->media));
    }

    private function persistPage(string $mainContent, ?Media $mainImage = null): Page
    {
        $page = new Page();
        $page->slug = self::SLUG;
        $page->host = self::HOST;
        $page->h1 = 'Media usage fixture';
        $page->mainContent = $mainContent;
        $page->mainImage = $mainImage;

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        return $page;
    }

    private function getMedia(string $fileName): Media
    {
        $media = $this->entityManager->getRepository(Media::class)->findOneBy(['fileName' => $fileName]);
        self::assertInstanceOf(Media::class, $media, 'The fixtures are expected to hold '.$fileName);

        return $media;
    }

    /**
     * The column is written by raw SQL on purpose, so an entity already in the
     * identity map still carries the value it was loaded with.
     *
     * @return list<string>
     */
    private function readPageTags(Media $media): array
    {
        /** @var MediaRepository $mediaRepository */
        $mediaRepository = self::getContainer()->get(MediaRepository::class);

        return $mediaRepository->findPageTags([(int) $media->id])[(int) $media->id] ?? [];
    }

    private function removeFixturePage(): void
    {
        $page = $this->entityManager->getRepository(Page::class)
            ->findOneBy(['slug' => self::SLUG, 'host' => self::HOST]);

        if (null !== $page) {
            $this->entityManager->remove($page);
            $this->entityManager->flush();
        }

        $this->media->setTags('');
        $this->entityManager->flush();
    }
}
