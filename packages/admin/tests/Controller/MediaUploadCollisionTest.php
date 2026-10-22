<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheManager;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The production incident of 2026-10-15, replayed through the admin: media A exists; an
 * editor creates B from the media picker with a name that comes out as A's, the upload's
 * background `pw:image:cache` runs, then B is renamed. A lost its file — B had been
 * written over it, then moved away — its derivatives, and its dimensions, rewritten from
 * B's photo.
 */
#[Group('integration')]
final class MediaUploadCollisionTest extends AbstractAdminTestClass
{
    /** @var int[] */
    private array $createdMediaIds = [];

    protected function tearDown(): void
    {
        $em = $this->entityManager();
        $em->clear();
        foreach ($this->createdMediaIds as $id) {
            $media = $em->find(Media::class, $id);
            if (null !== $media) {
                $em->remove($media);
            }
        }

        $em->flush();
        $this->createdMediaIds = [];

        parent::tearDown();
    }

    /** @return iterable<string, array{string, string, string}> client file name, alt, slug — `%s` is A's unique part */
    public static function provideNamesComingOutAsA(): iterable
    {
        yield 'client file name slugified to A' => ['Original_A_%s.png', '', ''];
        yield 'alt slugified to A' => ['b.png', 'Original-A %s', ''];
        yield 'slug filled with A' => ['b.png', '', 'original-a-%s'];
        yield 'alt with © and slug filled with A' => ['b.png', 'Légende © Auteur %s', 'original-a-%s'];
    }

    #[DataProvider('provideNamesComingOutAsA')]
    public function testAnUploadNamedLikeAnotherMediaNeverTakesItsFile(string $clientName, string $alt, string $slug): void
    {
        $client = $this->loginUser();
        $mediaDir = $this->mediaDir();
        $unique = substr(uniqid(), -6);

        $a = $this->createThroughPicker($client, $this->png('a.png', 15, 20), 'Original A '.$unique, '');
        self::assertSame('original-a-'.$unique.'.png', $a->getFileName());
        $aBytes = sha1_file($mediaDir.'/'.$a->getFileName());
        $aPreview = self::getContainer()->get(ImageCacheManager::class)->getFilterPath($a, 'md');
        self::assertFileExists($aPreview);

        $bUpload = $this->png(\sprintf($clientName, $unique), 12, 16);
        $bBytes = sha1_file($bUpload);
        $b = $this->createThroughPicker($client, $bUpload, \sprintf($alt, $unique), \sprintf($slug, $unique));

        // What the upload dispatched to the background, run in the foreground.
        $this->runImageCache($b->getFileName());

        $this->renameThroughEditForm($client, $b, 'Légende © Auteur '.$unique);

        $a = $this->reload($a);
        $b = $this->reload($b);
        self::assertSame('legende_auteur-'.$unique.'.png', $b->getFileName(), 'B was renamed');
        self::assertFileExists($mediaDir.'/'.$a->getFileName(), 'A kept its file');
        self::assertSame($aBytes, sha1_file($mediaDir.'/'.$a->getFileName()), "A's file still holds A's photo");
        self::assertSame([15, 20], [$a->getWidth(), $a->getHeight()], "A's dimensions are not B's");
        self::assertFileExists($aPreview, "A's derivatives were not purged");
        self::assertSame($bBytes, sha1_file($mediaDir.'/'.$b->getFileName()), "B's file holds B's photo");
        self::assertNotContains($a->getFileName(), $b->fileNameHistory, "B never answers to A's name");
    }

    /**
     * The block editor and the API already answer an upload of known bytes with the media
     * holding them; the media form stored a second copy (production, 2026-10-15: the same
     * photo created 18 s apart).
     */
    public function testUploadingAFileTheLibraryHoldsReusesItsMedia(): void
    {
        $client = $this->loginUser();
        $upload = $this->png('dup.png', 4, 4);
        $copy = \dirname($upload, 2).'/'.uniqid('copy-').'/dup-copy.png';
        mkdir(\dirname($copy));
        copy($upload, $copy);

        $first = $this->createThroughPicker($client, $upload, 'Duplicate source '.uniqid(), '');
        $count = $this->entityManager()->getRepository(Media::class)->count();

        $this->submitNewForm($client, $copy, 'Duplicate copy', '', picker: true);

        self::assertSame($count, $this->entityManager()->getRepository(Media::class)->count(), 'no second media');
        $target = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('pwMediaPickerSelect='.$first->id, $target, 'the picker selects the media already holding the file');
        $client->followRedirect();
        self::assertStringContainsString(htmlspecialchars($first->getAlt()), (string) $client->getResponse()->getContent());

        $this->submitNewForm($client, $copy, 'Duplicate copy', '', picker: false);

        self::assertSame($count, $this->entityManager()->getRepository(Media::class)->count());
        self::assertResponseRedirects();
        self::assertStringContainsString('/'.$first->id.'/edit', (string) $client->getResponse()->headers->get('Location'));
    }

    private function createThroughPicker(KernelBrowser $client, string $file, string $alt, string $slug): Media
    {
        $this->submitNewForm($client, $file, $alt, $slug, picker: true);

        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertSame(1, preg_match('/pwMediaPickerSelect=(\d+)/', $location, $match), $location);
        $this->createdMediaIds[] = (int) $match[1];

        $media = $this->entityManager()->find(Media::class, (int) $match[1]);
        self::assertInstanceOf(Media::class, $media);

        return $media;
    }

    private function submitNewForm(KernelBrowser $client, string $file, string $alt, string $slug, bool $picker): void
    {
        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_media_create').($picker ? '?pwMediaPicker=1' : ''));
        $client->submit($crawler->filter('form[method="post"]')->form([
            'Media[mediaFile]' => $file,
            'Media[alt]' => $alt,
            'Media[slugForce]' => $slug,
        ]));

        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), substr((string) $client->getResponse()->getContent(), 0, 2000));
    }

    /** As an editor does: unlock the slug, type the caption as name and slug. */
    private function renameThroughEditForm(KernelBrowser $client, Media $media, string $name): void
    {
        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_media_edit', ['id' => $media->id]));
        $form = $crawler->filter('form[method="post"]')->form();
        // The slug field ships disabled; the lock button beside it enables it.
        $client->request(Request::METHOD_POST, $form->getUri(), array_replace_recursive(
            $form->getPhpValues(),
            ['Media' => ['alt' => $name, 'slugForce' => $name]],
        ));

        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), substr((string) $client->getResponse()->getContent(), 0, 2000));
    }

    private function runImageCache(string $fileName): void
    {
        $application = new Application(self::$kernel ?? self::bootKernel());
        $tester = new CommandTester($application->find('pw:image:cache'));
        $tester->execute(['media' => $fileName, '--force' => true, '--format' => 'text']);
    }

    /**
     * A PNG no other test produces: dedup must not mistake it for another upload.
     *
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function png(string $clientName, int $width, int $height): string
    {
        $dir = sys_get_temp_dir().'/'.uniqid('collision-');
        mkdir($dir);
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, random_int(0, 0xFFFFFF));
        imagesetpixel($image, 0, 0, random_int(0, 0xFFFFFF));
        imagepng($image, $dir.'/'.$clientName);

        return $dir.'/'.$clientName;
    }

    private function reload(Media $media): Media
    {
        $em = $this->entityManager();
        $em->clear();

        $reloaded = $em->find(Media::class, $media->id);
        self::assertInstanceOf(Media::class, $reloaded);

        return $reloaded;
    }

    private function mediaDir(): string
    {
        return self::getContainer()->getParameter('pw.media_dir');
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get('doctrine.orm.default_entity_manager');
    }
}
