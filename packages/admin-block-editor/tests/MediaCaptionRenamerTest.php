<?php

namespace Pushword\AdminBlockEditor\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\AdminBlockEditor\Service\MediaCaptionRenamer;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Tests\PathTrait;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Group('integration')]
final class MediaCaptionRenamerTest extends AbstractAdminTestClass
{
    use PathTrait;

    private const string FIXTURE = __DIR__.'/../../core/tests/EventListener/media/2.jpg';

    /** @var int[] media IDs to clean up after each test */
    private array $createdMediaIds = [];

    protected function tearDown(): void
    {
        // Every media created here is a copy of the same fixture, so they all
        // share one content hash. Left in the shared per-worker DB they surface
        // as a duplicate group to sibling tests (e.g. CleanDuplicateMediaCommandTest).
        $em = $this->em();
        foreach ($this->createdMediaIds as $id) {
            $media = $em->getRepository(Media::class)->find($id);
            if ($media instanceof Media) {
                $em->remove($media);
            }
        }

        $em->flush();
        $this->createdMediaIds = [];

        parent::tearDown();
    }

    public function testRenamesImageFromCaption(): void
    {
        self::bootKernel();
        $media = $this->createAutoNamedMedia('Zugspitze Photo.jpg');
        self::assertSame('zugspitze-photo.jpg', $media->getFileName());

        $this->renamer()->renameFromContent(
            $this->page('![Hikers Zugspitze Austria](/media/md/zugspitze-photo.jpg)'),
        );

        self::assertSame('hikers-zugspitze-austria.jpg', $media->getFileName());
        self::assertTrue($media->hasFileNameInHistory('zugspitze-photo.jpg'));
        self::assertFileExists($this->getMediaDir().'/hikers-zugspitze-austria.jpg');
    }

    public function testRenamesSingleWordCaption(): void
    {
        self::bootKernel();
        $media = $this->createAutoNamedMedia('Some Random Upload.jpg');

        $this->renamer()->renameFromContent(
            $this->page('![Matterhorn](/media/md/'.$media->getFileName().')'),
        );

        self::assertSame('matterhorn.jpg', $media->getFileName());
    }

    public function testRenamesFromGalleryCaption(): void
    {
        self::bootKernel();
        $media = $this->createAutoNamedMedia('Gallery Source.jpg');

        $this->renamer()->renameFromContent(
            $this->page('{{ gallery({"'.$media->getFileName().'":"Lake Sunset View"}) }}'),
        );

        self::assertSame('lake-sunset-view.jpg', $media->getFileName());
    }

    public function testSkipsManuallyRenamedMedia(): void
    {
        self::bootKernel();
        $media = $this->createAutoNamedMedia('Original Upload.jpg');

        // Simulate a manual slug change in admin: filename now diverges from alt.
        $media->setFileName('manual-name.jpg');
        $this->em()->flush();

        $this->renamer()->renameFromContent(
            $this->page('![New Descriptive Caption](/media/md/manual-name.jpg)'),
        );

        self::assertSame('manual-name.jpg', $media->getFileName());
    }

    public function testRenamesEvenWhenAltAlreadySyncedToNewCaptionInMemory(): void
    {
        self::bootKernel();
        $media = $this->createAutoNamedMedia('Dolomites Source.jpg');

        // MediaExtension syncs alt = caption in memory while rendering content, before the
        // renamer runs. The live alt then no longer matches the old filename; only the
        // persisted alt still does. The rename must rely on the persisted alt and still fire.
        $media->setAlt('Tre Cime Lavaredo Sunset');

        $this->renamer()->renameFromContent(
            $this->page('![Tre Cime Lavaredo Sunset](/media/md/dolomites-source.jpg)'),
        );

        self::assertSame('tre-cime-lavaredo-sunset.jpg', $media->getFileName());
        self::assertTrue($media->hasFileNameInHistory('dolomites-source.jpg'));
    }

    public function testNoOpWhenCaptionMatchesCurrentName(): void
    {
        self::bootKernel();
        $media = $this->createAutoNamedMedia('Steady Name.jpg');

        $this->renamer()->renameFromContent(
            $this->page('![Steady Name](/media/md/steady-name.jpg)'),
        );

        self::assertSame('steady-name.jpg', $media->getFileName());
        self::assertSame([], $media->getFileNameHistory());
    }

    public function testSkipsEmptyCaption(): void
    {
        self::bootKernel();
        $media = $this->createAutoNamedMedia('Solo Name.jpg');

        $this->renamer()->renameFromContent(
            $this->page('![](/media/md/solo-name.jpg)'),
        );

        self::assertSame('solo-name.jpg', $media->getFileName());
        self::assertSame([], $media->getFileNameHistory());
    }

    public function testRenameIntoExistingNameIsSuffixed(): void
    {
        self::bootKernel();
        $occupant = $this->createAutoNamedMedia('Existing Name.jpg');
        self::assertSame('existing-name.jpg', $occupant->getFileName());

        $media = $this->createAutoNamedMedia('Fresh Upload.jpg');

        $this->renamer()->renameFromContent(
            $this->page('![Existing Name](/media/md/'.$media->getFileName().')'),
        );

        // Collision is resolved (not overwritten) and the occupant keeps its name.
        self::assertNotSame('existing-name.jpg', $media->getFileName());
        self::assertStringStartsWith('existing-name', $media->getFileName());
        self::assertSame('existing-name.jpg', $occupant->getFileName());
        self::assertFileExists($this->getMediaDir().'/'.$media->getFileName());
    }

    private function createAutoNamedMedia(string $originalName): Media
    {
        $tmp = sys_get_temp_dir().'/'.uniqid('cap-', true).'-'.$originalName;
        new Filesystem()->copy(self::FIXTURE, $tmp);

        $media = new Media();
        $media->setMediaFile(new UploadedFile($tmp, $originalName, 'image/jpeg', null, true));

        $this->em()->persist($media);
        $this->em()->flush();

        // Reload from DB so the instance has no pending upload file, as in the real edit flow.
        $id = $media->id;
        if (null !== $id) {
            $this->createdMediaIds[] = $id;
        }

        $this->em()->clear();

        return $this->em()->getRepository(Media::class)->find($id) ?? throw new Exception();
    }

    private function page(string $content): Page
    {
        $page = new Page();
        $page->setMainContent($content);

        return $page;
    }

    private function renamer(): MediaCaptionRenamer
    {
        $em = $this->em();

        return new MediaCaptionRenamer($em, $em->getRepository(Media::class));
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get('doctrine.orm.default_entity_manager');
    }
}
