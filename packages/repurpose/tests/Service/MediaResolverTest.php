<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheGenerator;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Tests\PathTrait;
use Pushword\Repurpose\Service\CarouselFactory;
use Pushword\Repurpose\Service\MediaResolver;
use Pushword\Repurpose\Service\SlideRenderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class MediaResolverTest extends KernelTestCase
{
    use PathTrait;

    /**
     * The reported case: right after an upload, core has not run its background
     * `pw:image:cache` yet, so the image passes validation but has no derivative,
     * and the slide rendered as the grey placeholder.
     */
    public function testAnImageUploadedMomentsAgoRendersInsteadOfThePlaceholder(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');
        $cache = $container->get(ImageCacheManager::class);
        $filename = 'repurpose-fresh-upload-'.bin2hex(random_bytes(4)).'.jpg';
        new Filesystem()->copy($this->projectDir.'/media~/1.jpg', $this->getMediaDir().'/'.$filename);
        $media = new Media()
            ->setFileName($filename)
            ->setMimeType('image/jpeg')
            ->setDimensions([1000, 1000])
            ->setProjectDir($container->getParameter('kernel.project_dir'))
            ->setStoreIn($this->getMediaDir())
            ->setHash();
        $em->persist($media);
        $em->flush();

        $cache->remove($media);

        try {
            $resolver = $container->get(MediaResolver::class);
            self::assertNotNull($resolver->sourceDimensions($filename));
            self::assertNotNull($resolver->derivativePath($filename, 'md'));

            $svg = $container->get(SlideRenderer::class)->renderSlide(new CarouselFactory()->fromArray([
                'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
                'slides' => [['title' => 'Fresh', 'image' => ['media' => $filename]]],
            ]), 0);
            self::assertStringContainsString('<image href="data:image/', $svg);
            self::assertStringNotContainsString(SlideRenderer::MISSING_MEDIA_BG, $svg);
        } finally {
            $cache->remove($media);
            $em->remove($media);
            $em->flush();
            new Filesystem()->remove($this->getMediaDir().'/'.$filename);
        }
    }

    public function testAFileOutsideTheLibraryStaysMissing(): void
    {
        self::bootKernel();
        $resolver = self::getContainer()->get(MediaResolver::class);

        self::assertNull($resolver->derivativePath('nowhere.jpg', 'md'));
        self::assertNull($resolver->sourceDimensions('nowhere.jpg'));
    }

    public function testAnEmptyDerivativeCountsAsMissing(): void
    {
        self::bootKernel();
        $directory = sys_get_temp_dir().'/pw-media-resolver-'.bin2hex(random_bytes(4));
        new Filesystem()->dumpFile($directory.'/default/broken.png', '');
        $resolver = $this->resolverFor($directory);

        try {
            self::assertNull($resolver->derivativePath('broken.png', 'md'), 'an optimizer killed mid-write leaves 0 bytes');
        } finally {
            new Filesystem()->remove($directory);
        }
    }

    public function testAnUnreadableSourceKeepsThePlaceholderWithoutThrowing(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');
        $cache = $container->get(ImageCacheManager::class);
        $filename = 'repurpose-unreadable-'.bin2hex(random_bytes(4)).'.jpg';
        new Filesystem()->dumpFile($this->getMediaDir().'/'.$filename, 'not an image');
        $media = new Media()
            ->setFileName($filename)
            ->setMimeType('image/jpeg')
            ->setDimensions([10, 10])
            ->setProjectDir($container->getParameter('kernel.project_dir'))
            ->setStoreIn($this->getMediaDir())
            ->setHash();
        $em->persist($media);
        $em->flush();

        $cache->remove($media);

        try {
            $resolver = $container->get(MediaResolver::class);
            self::assertNull($resolver->derivativePath($filename, 'md'));
            self::assertNull($resolver->sourceDimensions($filename));
        } finally {
            $cache->remove($media);
            $em->remove($media);
            $em->flush();
            new Filesystem()->remove($this->getMediaDir().'/'.$filename);
        }
    }

    public function testAMissingFilterFallsBackToTheDefaultDerivative(): void
    {
        self::bootKernel();
        $directory = sys_get_temp_dir().'/pw-media-resolver-'.bin2hex(random_bytes(4));
        new Filesystem()->copy($this->projectDir.'/media~/1.jpg', $directory.'/default/photo.jpg');
        $resolver = $this->resolverFor($directory);

        try {
            self::assertSame($directory.'/default/photo.jpg', $resolver->derivativePath('photo.jpg', 'md'));
            self::assertSame($directory.'/default/photo.jpg', $resolver->derivativePath('photo.jpg', 'default'));
            self::assertNotNull($resolver->sourceDimensions('photo.jpg'));
        } finally {
            new Filesystem()->remove($directory);
        }
    }

    private function resolverFor(string $mediaCacheDir): MediaResolver
    {
        return new MediaResolver(
            $mediaCacheDir,
            self::getContainer()->get(MediaRepository::class),
            self::getContainer()->get(ImageCacheGenerator::class),
        );
    }
}
