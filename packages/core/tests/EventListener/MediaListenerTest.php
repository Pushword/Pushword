<?php

namespace Pushword\Core\Tests\Controller;

use Exception;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ExternalImageImporter;
use Pushword\Core\Image\ImageCacheGenerator;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ImageEncoder;
use Pushword\Core\Image\ImageReader;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Service\SafeRemoteFileFetcher;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class MediaListenerTest extends AbstractAdminTestClass // PantherTestCase // KernelTestCase
{
    use PathTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureMediaFileExists();
    }

    public function testRenameMediaOnNameUpdate(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $mediaRepo = $em->getRepository(Media::class);

        $media = $mediaRepo->findOneBy(['fileName' => 'piedweb-logo.png']) ?? throw new Exception();
        $media->setFileName('piedweb.png');

        $em->flush();
        self::assertFileExists($this->getMediaDir().'/piedweb.png');

        $media->setFileName('piedweb-logo.png');
        $em->flush();
    }

    /**
     * A rename moves the file only once the row is committed. It used to move it in
     * preUpdate: when anything later in the same flush threw, the transaction rolled back
     * and left the DB naming a file that had already been renamed away — every reference
     * to it 404ing (production, 2026-08-16). Here the second rename is impossible, so the
     * flush dies; the first one must have touched nothing.
     */
    public function testAFlushThatThrowsMovesNoFile(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $suffix = getmypid().'.png';
        // Persisted first so Doctrine writes it first: the rename that dies has to come after
        // one that already "succeeded", the shape that used to leave disk and DB disagreeing.
        $movable = $this->probeMedia('movable-'.$suffix, onDisk: true);
        $ghost = $this->probeMedia('ghost-'.$suffix, onDisk: false);
        $em->persist($movable);
        $em->persist($ghost);
        $em->flush();

        $movable->setFileName('movable-renamed-'.$suffix);
        // No file behind this one: MediaStorageListener refuses the rename, aborting the flush.
        $ghost->setFileName('ghost-renamed-'.$suffix);

        try {
            $em->flush();
            self::fail('the flush must not survive an impossible rename');
        } catch (Exception) {
        }

        $connection = $em->getConnection();

        try {
            self::assertFileExists($this->getMediaDir().'/movable-'.$suffix);
            self::assertFileDoesNotExist($this->getMediaDir().'/movable-renamed-'.$suffix);
            self::assertSame(
                'movable-'.$suffix,
                $connection->fetchOne('SELECT media FROM media WHERE id = ?', [$movable->id]),
                'the rolled-back row must still name the file that is on disk',
            );
        } finally {
            $connection->delete('media', ['id' => $movable->id]);
            $connection->delete('media', ['id' => $ghost->id]);
            @unlink($this->getMediaDir().'/movable-'.$suffix);
            @unlink($this->getMediaDir().'/movable-renamed-'.$suffix);
        }
    }

    /**
     * A rename already done on disk but not in the DB is what a crashed run leaves behind —
     * the state production was found in. The next save must adopt it, not fail on it.
     */
    public function testARenameAlreadyDoneOnDiskIsAdopted(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $suffix = getmypid().'.png';
        $old = 'adopted-'.$suffix;
        $new = 'adopted-target-'.$suffix;

        $media = $this->probeMedia($old, onDisk: true);
        $em->persist($media);
        $em->flush();

        rename($this->getMediaDir().'/'.$old, $this->getMediaDir().'/'.$new);

        $media->setFileName($new);
        $em->flush();

        try {
            self::assertFileExists($this->getMediaDir().'/'.$new);
            self::assertSame($new, $em->getConnection()->fetchOne('SELECT media FROM media WHERE id = ?', [$media->id]));
        } finally {
            $em->getConnection()->delete('media', ['id' => $media->id]);
            @unlink($this->getMediaDir().'/'.$new);
        }
    }

    /**
     * The destination is a file no row claims, so the conflict resolver — which only reads
     * the DB — waves the rename through. Only the disk knows, and it must refuse before the
     * commit rather than overwrite.
     */
    public function testARenameOntoAFileNoRowClaimsIsRefused(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $suffix = getmypid().'.png';
        $orphan = 'orphan-'.$suffix;
        imagepng(imagecreatetruecolor(4, 4), $this->getMediaDir().'/'.$orphan);

        $media = $this->probeMedia('claimant-'.$suffix, onDisk: true);
        $em->persist($media);
        $em->flush();

        $media->setFileName($orphan);

        try {
            $em->flush();
            self::fail('renaming onto an existing file must not be allowed');
        } catch (Exception) {
        }

        $connection = $em->getConnection();

        try {
            self::assertFileExists($this->getMediaDir().'/claimant-'.$suffix);
            self::assertSame(
                'claimant-'.$suffix,
                $connection->fetchOne('SELECT media FROM media WHERE id = ?', [$media->id]),
            );
        } finally {
            $connection->delete('media', ['id' => $media->id]);
            @unlink($this->getMediaDir().'/claimant-'.$suffix);
            @unlink($this->getMediaDir().'/'.$orphan);
        }
    }

    private function probeMedia(string $fileName, bool $onDisk): Media
    {
        if ($onDisk) {
            imagepng(imagecreatetruecolor(4, 4), $this->getMediaDir().'/'.$fileName);
        }

        $media = new Media();
        $media->setFileName($fileName);
        // Unique: MediaConflictResolver renames a media whose alt another one already has.
        $media->setAlt($fileName);
        $media->setMimeType('image/png');
        $media->setHash(sha1($fileName, true));

        return $media;
    }

    /**
     * // This is not testing MediaListner bug ImageImport (ImageManager Service).
     */
    public function testRenameAndCo(): void
    {
        self::bootKernel();

        $url = 'https://fixture.invalid/media-listener-2.jpg';
        new Filesystem()->copy(__DIR__.'/media/2.jpg', sys_get_temp_dir().'/pushword-safe-image-'.sha1($url), true);

        $mediaEntity = $this->getImporter()->importExternal($url, '1', '', false);
        // $em->persist($mediaEntity);
        self::assertFileExists($this->getMediaDir().'/1-2.jpg');

        // If import twice, return the existing one and not create a new copy
        $mediaEntity = $this->getImporter()->importExternal($url, '1', '', false);
        self::assertFileDoesNotExist($this->getMediaDir().'/1-3.jpg');
        self::assertSame('1-2.jpg', $mediaEntity->getFileName());
        unlink($this->getMediaDir().'/1-2.jpg');
        self::assertFileDoesNotExist($this->getMediaDir().'/1-2.jpg');
    }

    // 1. A new image is properly renamed when another image with the same name already exists (no overwrite)
    public function testRenameNewMediaIfAnotherMediaHasSameName(): void
    {
        $file = __DIR__.'/media/2.jpg';
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $crawler = $this->requestMediaCreateForm($client);
        $fileInput = $crawler->filter('[type="file"]');
        $formId = strtok($fileInput->getNode(0)->getAttribute('name'), '['); // @phpstan-ignore-line
        $form = $crawler->filter('form[method="post"]')->form([
            $formId.'[mediaFile]' => $file,
        ]);
        $client->submit($form);
        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        self::assertFileExists($this->getMediaDir().'/2-2.jpg');

        $crawler = $this->requestMediaCreateForm($client);
        $fileInput = $crawler->filter('[type="file"]');
        $formId = strtok($fileInput->getNode(0)->getAttribute('name'), '['); // @phpstan-ignore-line
        $form = $crawler->filter('form[method="post"]')->form([
            $formId.'[mediaFile]' => $file,
            $formId.'[alt]' => '1',
        ]);

        $client->submit($form);
        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        self::assertFileExists($this->getMediaDir().'/1-2.jpg');

        $crawler = $this->requestMediaCreateForm($client);
        $fileInput = $crawler->filter('[type="file"]');
        $formId = strtok($fileInput->getNode(0)->getAttribute('name'), '['); // @phpstan-ignore-line
        $form = $crawler->filter('form[method="post"]')->form([
            $formId.'[mediaFile]' => $file,
            $formId.'[slugForce]' => '1',
        ]);

        $client->submit($form);
        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        self::assertFileExists($this->getMediaDir().'/1-3.jpg');

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $mediaRepo = $em->getRepository(Media::class);

        $medias = $mediaRepo->findBy([], ['id' => 'DESC'], 3, 0);
        foreach ($medias as $m) {
            $em->remove($m);
        }

        $em->flush();
        self::assertFileDoesNotExist($this->getMediaDir().'/1-4.jpg');
        self::assertFileDoesNotExist($this->getMediaDir().'/1-3.jpg');
    }

    // Todo
    // 1. When I change a slug, the file is properly renamed
    // 2. When I replace a media, it keeps the same file path
    // 3. When I change a name, only the name is modified

    private ?ExternalImageImporter $importer = null;

    private function getImporter(): ExternalImageImporter
    {
        if (null !== $this->importer) {
            return $this->importer;
        }

        /** @var MediaStorageAdapter $mediaStorage */
        $mediaStorage = self::getContainer()->get(MediaStorageAdapter::class);
        $imageReader = new ImageReader($mediaStorage);
        $imageEncoder = new ImageEncoder();
        $imageCacheManager = new ImageCacheManager([], $this->publicMediaDir, $this->getMediaCacheDir(), $mediaStorage);
        $backgroundTaskDispatcher = self::getContainer()->get(BackgroundTaskDispatcherInterface::class);
        $imageCacheGenerator = new ImageCacheGenerator($imageReader, $imageEncoder, $imageCacheManager, $backgroundTaskDispatcher, $mediaStorage);

        return $this->importer = new ExternalImageImporter(
            $mediaStorage,
            $imageCacheGenerator,
            $this->getMediaDir(),
            $this->projectDir,
            self::getContainer()->get(SafeRemoteFileFetcher::class),
        );
    }

    private function requestMediaCreateForm(KernelBrowser $client): Crawler
    {
        $createUrl = $this->generateAdminUrl('admin_media_create');

        return $client->request(Request::METHOD_GET, $createUrl);
    }
}
