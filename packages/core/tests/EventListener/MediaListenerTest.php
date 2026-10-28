<?php

declare(strict_types=1);

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
use Symfony\Component\HttpFoundation\File\UploadedFile;
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

    /**
     * Once stored, an upload is an ordinary media, and a rename later in the same request
     * moves the stored file. The media used to keep holding a file whose path led
     * nowhere, and the rename read its extension from it.
     */
    public function testAMediaUploadedEarlierInTheRequestCanBeRenamed(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $suffix = uniqid();
        $image = imagecreatetruecolor(2, 2);
        self::assertNotFalse($image);
        imagesetpixel($image, 0, 0, random_int(1, 0xFFFFFF));
        $path = sys_get_temp_dir().'/uploaded-'.$suffix.'.png';
        imagepng($image, $path);

        $media = new Media();
        $media->setMediaFile(new UploadedFile($path, basename($path), 'image/png', null, true));

        $em->persist($media);
        $em->flush();

        $uploaded = $media->getFileName();

        try {
            $media->setSlugForce('renamed-'.$suffix);
            $em->flush();

            self::assertSame('renamed-'.$suffix.'.png', $media->getFileName());
            self::assertFileExists($this->getMediaDir().'/renamed-'.$suffix.'.png');
            self::assertFileDoesNotExist($this->getMediaDir().'/'.$uploaded);
        } finally {
            $em->remove($media);
            $em->flush();
        }
    }

    /**
     * The incident of 2026-10-15 at its root: MediaConflictResolver judged an unnamed
     * upload by its raw alt — `Held 1a2b.png` — while the file went to the slug of it,
     * `held-1a2b.png`, another media's file, which Vich wrote over. Every form names the
     * upload before persisting now; this persists one unnamed, as any code still may.
     */
    public function testAnUploadNamedAfterAnotherMediaIsNotWrittenOverIt(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $suffix = uniqid();
        $held = $this->probeMedia('held-'.$suffix.'.png', onDisk: true);
        $em->persist($held);
        $em->flush();

        $heldBytes = sha1_file($this->getMediaDir().'/held-'.$suffix.'.png');

        $upload = new Media();
        $upload->setMediaFile($this->upload());
        $upload->setAlt('Held '.$suffix);

        $em->persist($upload);
        $em->flush();

        try {
            self::assertNotSame('held-'.$suffix.'.png', $upload->getFileName());
            self::assertSame($heldBytes, sha1_file($this->getMediaDir().'/held-'.$suffix.'.png'));
        } finally {
            $em->remove($held);
            $em->remove($upload);
            $em->flush();
        }
    }

    /** No row claims the file, so only the disk can tell the name is taken. */
    public function testAnUploadIsNotWrittenOverAFileNoRowClaims(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $orphan = 'unclaimed-'.uniqid().'.png';
        imagepng(imagecreatetruecolor(4, 4), $this->getMediaDir().'/'.$orphan);
        $orphanBytes = sha1_file($this->getMediaDir().'/'.$orphan);

        $upload = new Media();
        $upload->setMediaFile($this->upload());
        $upload->setSlugForce(basename($orphan, '.png'));

        $em->persist($upload);
        $em->flush();

        try {
            self::assertNotSame($orphan, $upload->getFileName());
            self::assertSame($orphanBytes, sha1_file($this->getMediaDir().'/'.$orphan));
        } finally {
            $em->remove($upload);
            $em->flush();
            @unlink($this->getMediaDir().'/'.$orphan);
        }
    }

    /**
     * An upload refused the name of another media used to keep that name as the one it
     * was "renamed from", so its next rename in the request moved the other media's file.
     */
    public function testRenamingAnUploadMovesItsOwnFileNotTheOneItWasRefused(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $suffix = uniqid();
        $held = $this->probeMedia('refused-'.$suffix.'.png', onDisk: true);
        $em->persist($held);
        $em->flush();

        $heldBytes = sha1_file($this->getMediaDir().'/refused-'.$suffix.'.png');

        $file = $this->upload();
        $uploadBytes = sha1_file($file->getPathname());
        $upload = new Media();
        $upload->setMediaFile($file);
        $upload->setSlugForce('refused-'.$suffix);

        $em->persist($upload);
        $em->flush();

        try {
            $upload->setSlugForce('moved-'.$suffix);
            $em->flush();

            self::assertSame($heldBytes, sha1_file($this->getMediaDir().'/refused-'.$suffix.'.png'));
            self::assertSame($uploadBytes, sha1_file($this->getMediaDir().'/moved-'.$suffix.'.png'));
            self::assertSame([], array_intersect(['refused-'.$suffix.'.png'], $upload->fileNameHistory));
        } finally {
            $em->remove($held);
            $em->remove($upload);
            $em->flush();
        }
    }

    /**
     * Two rows naming one file is what the overwrite left behind. Renaming the newer one
     * moved the file away from the older and purged its derivatives: refused instead.
     */
    public function testARenameDoesNotTakeTheFileOfAnotherMediaWithTheSameName(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $shared = 'shared-'.uniqid().'.png';
        $older = $this->probeMedia($shared, onDisk: true);
        $newer = $this->probeMedia($shared, onDisk: false);
        $newer->setAlt('newer '.$shared);

        $em->persist($older);
        $em->persist($newer);
        $em->flush();

        $newer->setFileName('unshared-'.$shared);

        try {
            $em->flush();
            self::fail('a rename must not take the file another media is named after');
        } catch (Exception $exception) {
            self::assertStringContainsString('is also the file of media #'.$older->id, $exception->getMessage());
        }

        $connection = $em->getConnection();

        try {
            self::assertFileExists($this->getMediaDir().'/'.$shared);
            self::assertFileDoesNotExist($this->getMediaDir().'/unshared-'.$shared);
            self::assertSame($shared, $connection->fetchOne('SELECT media FROM media WHERE id = ?', [$newer->id]));
        } finally {
            $connection->delete('media', ['id' => $older->id]);
            $connection->delete('media', ['id' => $newer->id]);
            @unlink($this->getMediaDir().'/'.$shared);
        }
    }

    /** Removing the wrong row of such a pair is the repair: the file stays with the other. */
    public function testRemovingAMediaKeepsTheFileAnotherMediaIsNamedAfter(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $shared = 'shared-removed-'.uniqid().'.png';
        $older = $this->probeMedia($shared, onDisk: true);
        $newer = $this->probeMedia($shared, onDisk: false);
        $newer->setAlt('newer '.$shared);

        $em->persist($older);
        $em->persist($newer);
        $em->flush();

        $em->remove($newer);
        $em->flush();

        try {
            self::assertFileExists($this->getMediaDir().'/'.$shared);
        } finally {
            $em->remove($older);
            $em->flush();
        }

        self::assertFileDoesNotExist($this->getMediaDir().'/'.$shared, 'the last holder takes the file with it');
    }

    /**
     * The edit form's "replace file" flow: a stored media uploads new bytes at its own
     * name. The disk check that protects new uploads must not make it take a suffix.
     */
    public function testReplacingAStoredMediaFileKeepsItsNameAndOverwritesItsFile(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $name = 'replaced-'.uniqid().'.png';
        $media = $this->probeMedia($name, onDisk: true);
        $em->persist($media);
        $em->flush();

        $replacement = $this->upload();
        $replacementHash = sha1_file($replacement->getPathname());

        try {
            $media->setMediaFile($replacement);
            $em->flush();

            self::assertSame($name, $media->getFileName());
            self::assertSame($replacementHash, sha1_file($this->getMediaDir().'/'.$name));
        } finally {
            $em->remove($media);
            $em->flush();
        }
    }

    /** A PNG with bytes of its own, as an editor would upload it. */
    private function upload(): UploadedFile
    {
        $image = imagecreatetruecolor(3, 3);
        self::assertNotFalse($image);
        imagesetpixel($image, 0, 0, random_int(1, 0xFFFFFF));
        $path = sys_get_temp_dir().'/upload-'.uniqid().'.png';
        imagepng($image, $path);

        return new UploadedFile($path, basename($path), 'image/png', null, true);
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
            $formId.'[mediaFile]' => $this->distinctCopy($file),
        ]);
        $client->submit($form);
        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        self::assertFileExists($this->getMediaDir().'/2-2.jpg');

        $crawler = $this->requestMediaCreateForm($client);
        $fileInput = $crawler->filter('[type="file"]');
        $formId = strtok($fileInput->getNode(0)->getAttribute('name'), '['); // @phpstan-ignore-line
        $form = $crawler->filter('form[method="post"]')->form([
            $formId.'[mediaFile]' => $this->distinctCopy($file),
            $formId.'[alt]' => '1',
        ]);

        $client->submit($form);
        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        self::assertFileExists($this->getMediaDir().'/1-2.jpg');

        $crawler = $this->requestMediaCreateForm($client);
        $fileInput = $crawler->filter('[type="file"]');
        $formId = strtok($fileInput->getNode(0)->getAttribute('name'), '['); // @phpstan-ignore-line
        $form = $crawler->filter('form[method="post"]')->form([
            $formId.'[mediaFile]' => $this->distinctCopy($file),
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
        if ($this->importer instanceof ExternalImageImporter) {
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

    /** The same picture under the same client name, with bytes of its own: identical bytes are reused, not renamed. */
    private function distinctCopy(string $file): string
    {
        $copy = sys_get_temp_dir().'/'.uniqid('copy-').'/'.basename($file);
        mkdir(\dirname($copy));
        file_put_contents($copy, file_get_contents($file).uniqid());

        return $copy;
    }

    private function requestMediaCreateForm(KernelBrowser $client): Crawler
    {
        $createUrl = $this->generateAdminUrl('admin_media_create');

        return $client->request(Request::METHOD_GET, $createUrl);
    }
}
