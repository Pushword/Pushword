<?php

namespace Pushword\Core\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\MediaUsage;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\MediaUsageRepository;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The listener half of the feature: usage rows have to follow a page write without
 * anybody asking, or every consumer reads a stale table.
 */
#[Group('integration')]
final class MediaUsageTrackerTest extends KernelTestCase
{
    use PathTrait;

    private const string HOST = 'localhost.dev';

    private const string SLUG = 'media-usage-tracker-fixture';

    private const string DISPOSABLE_FILE_NAME = 'media-usage-disposable.png';

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

    /**
     * A page still pointing at a file that was renamed under it renders that file, so
     * it still uses it — the usage map answers historical names too.
     */
    public function testAPageReferencingARenamedFileStillCountsAsUsingIt(): void
    {
        $page = $this->persistPage('Look ![](/media/default/1.jpg)');

        $this->media->setSlugForce('renamed-under-the-page');
        $this->entityManager->flush();
        self::assertSame('renamed-under-the-page.jpg', $this->media->getFileName());

        // Re-save the page without touching the reference: it still reads 1.jpg.
        $page->mainContent = 'Still ![](/media/default/1.jpg)';
        $this->entityManager->flush();

        self::assertContains((int) $this->media->id, $this->mediaUsageRepository->findMediaIdsForPage((int) $page->id));
    }

    /** Its rows point at nothing, and SQLite does not enforce the cascade that would drop them. */
    public function testRemovingAReferencedMediaRemovesItsRows(): void
    {
        $orphanMedia = $this->createDisposableMedia();
        $mediaId = (int) $orphanMedia->id;
        $page = $this->persistPage('Look ![](/media/default/'.$orphanMedia->getFileName().')');

        self::assertContains($mediaId, $this->mediaUsageRepository->findMediaIdsForPage((int) $page->id));

        $this->entityManager->remove($orphanMedia);
        $this->entityManager->flush();

        self::assertSame([], $this->mediaUsageRepository->findSourcesByPageForMedia($mediaId));
    }

    /**
     * A filename resolves against the media that exist when the page is saved, so the
     * page written before its image is uploaded has to be answered again at the upload.
     */
    public function testAMediaUploadedAfterThePageNamingItStillGetsItsRow(): void
    {
        $page = $this->persistPage('Look ![](/media/default/'.self::DISPOSABLE_FILE_NAME.')');

        $media = $this->createDisposableMedia();

        self::assertContains((int) $media->id, $this->mediaUsageRepository->findMediaIdsForPage((int) $page->id));
    }

    /**
     * Delete a media and re-upload it corrected under the same name: the pages still
     * render it, through the filename, under a new id. Without a row, the live image
     * reads as an orphan to `pw:media:clean-unused --force`.
     */
    public function testReuploadingAMediaUnderTheSameNameGivesItTheRowsItsPagesStillEarn(): void
    {
        $media = $this->createDisposableMedia();
        $mediaId = (int) $media->id;
        $page = $this->persistPage('Look ![](/media/default/'.self::DISPOSABLE_FILE_NAME.')');

        $this->entityManager->remove($media);
        $this->entityManager->flush();
        self::assertSame([], $this->mediaUsageRepository->findMediaIdsForPage((int) $page->id));

        $reuploaded = $this->createDisposableMedia();

        self::assertNotSame($mediaId, $reuploaded->id);
        self::assertContains((int) $reuploaded->id, $this->mediaUsageRepository->findMediaIdsForPage((int) $page->id));
    }

    /**
     * Both new in the same flush — an import, a multi-upload alongside its page. The
     * page is tracked while the media row may not be readable yet; the drain at the
     * end of the flush is what settles it.
     */
    public function testAPageAndTheMediaItNamesArrivingInOneFlushEndUpLinked(): void
    {
        $media = $this->newDisposableMedia();

        $page = new Page();
        $page->slug = self::SLUG;
        $page->host = self::HOST;
        $page->h1 = 'Media usage fixture';
        $page->mainContent = 'Look ![](/media/default/'.self::DISPOSABLE_FILE_NAME.')';

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        self::assertContains((int) $media->id, $this->mediaUsageRepository->findMediaIdsForPage((int) $page->id));
    }

    /** A property holds a filename as readily as a body does, and the backfill reads both. */
    public function testAMediaUploadedAfterAPropertyNamingItGetsAPropertyRow(): void
    {
        $page = $this->persistPage('Nothing in this body.');
        $page->setCustomProperty('cover', self::DISPOSABLE_FILE_NAME);

        $this->entityManager->flush();

        $media = $this->createDisposableMedia();

        self::assertSame(
            [MediaUsage::SOURCE_PROPERTY],
            $this->mediaUsageRepository->findSourcesByPageForMedia((int) $media->id)[(int) $page->id] ?? [],
        );
    }

    /** The rows the backfill writes carry the derived tags with them, like any other write. */
    public function testAMediaUploadedAfterATaggedPageInheritsItsTags(): void
    {
        $page = $this->persistPage('Look ![](/media/default/'.self::DISPOSABLE_FILE_NAME.')');
        $page->setTags('mountain');

        $this->entityManager->flush();

        $media = $this->createDisposableMedia();

        self::assertSame(['mountain'], $this->readPageTags($media));
    }

    /**
     * The candidate query is a `LIKE`, so this page is one — and extraction is what says
     * no. The whole superset design rests on that second answer being the one stored.
     */
    public function testAPageNamingALongerFilenameIsACandidateAndStillNotAUse(): void
    {
        $page = $this->persistPage('Look ![](/media/default/not-the-'.self::DISPOSABLE_FILE_NAME.')');

        $media = $this->createDisposableMedia();

        self::assertSame([], $this->mediaUsageRepository->findSourcesByPageForMedia((int) $media->id));
        self::assertSame([], $this->mediaUsageRepository->findMediaIdsForPage((int) $page->id));
    }

    /** A name nobody wrote down costs the scan and nothing else. */
    public function testUploadingAMediaNoPageNamesRecordsNothing(): void
    {
        $page = $this->persistPage('Look ![](/media/default/1.jpg)');

        $media = $this->createDisposableMedia();

        self::assertSame(
            [(int) $this->media->id],
            $this->mediaUsageRepository->findMediaIdsForPage((int) $page->id),
        );
        self::assertSame([], $this->mediaUsageRepository->findSourcesByPageForMedia((int) $media->id));
    }

    /** The admin's "used on" panel reads this; it used to be a LIKE over mainContent. */
    public function testGetPagesUsingMediaAnswersFromTheStoredRelation(): void
    {
        $page = $this->persistPage('Look ![](/media/default/1.jpg)');

        /** @var PageRepository $pageRepository */
        $pageRepository = self::getContainer()->get(PageRepository::class);
        $slugs = array_map(static fn (Page $found): string => $found->slug, $pageRepository->getPagesUsingMedia($this->media));

        self::assertContains($page->slug, $slugs);
        self::assertNotContains($page->slug, array_map(
            static fn (Page $found): string => $found->slug,
            $pageRepository->getPagesUsingMedia($this->media, 'admin-block-editor.test'),
        ), 'the host argument scopes the answer');
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

    private function createDisposableMedia(): Media
    {
        $media = $this->newDisposableMedia();
        $this->entityManager->flush();

        return $media;
    }

    /** A media of this test's own, so removing it cannot take a fixture with it. */
    private function newDisposableMedia(): Media
    {
        /** @var string $projectDir */
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $mediaDir = $this->getMediaDir();

        new Filesystem()->copy($mediaDir.'/piedweb-logo.png', $mediaDir.'/'.self::DISPOSABLE_FILE_NAME, true);

        $media = new Media();
        $media->setProjectDir($projectDir);
        $media->setStoreIn($mediaDir);
        $media->setMimeType('image/png');
        $media->setDimensions([1000, 1000]);
        $media->setFileName(self::DISPOSABLE_FILE_NAME);
        $media->setAlt('Media usage disposable');
        $media->setHash();

        $this->entityManager->persist($media);

        return $media;
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

        if ('1.jpg' !== $this->media->getFileName()) {
            $this->media->setSlugForce('1');
        }

        $this->entityManager->flush();

        $disposable = $this->entityManager->getRepository(Media::class)
            ->findOneBy(['fileName' => self::DISPOSABLE_FILE_NAME]);
        if (null !== $disposable) {
            $this->entityManager->remove($disposable);
            $this->entityManager->flush();
        }

        new Filesystem()->remove($this->getMediaDir().'/'.self::DISPOSABLE_FILE_NAME);
    }
}
