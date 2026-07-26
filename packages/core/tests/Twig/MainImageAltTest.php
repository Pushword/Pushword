<?php

namespace Pushword\Core\Tests\Twig;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * The main image alt cascade: localized alt, then the media's own alt, then the
 * page title as a last resort.
 */
#[Group('integration')]
final class MainImageAltTest extends KernelTestCase
{
    private function renderMainImage(Media $media, string $title = '', string $locale = 'en'): string
    {
        self::bootKernel();

        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        $page = new Page();
        $page->setSlug('a-page');
        $page->locale = $locale;
        $page->setTitle($title);
        $page->setMainImage($media);

        return $twig->load('@PushwordCore/page/_content.html.twig')
            ->renderBlock('main_image', ['page' => $page]);
    }

    private function createMedia(): Media
    {
        $media = new Media();
        $media->setFileName('mountain.jpg');
        $media->imageData->setDimensions([1200, 800]);

        return $media;
    }

    public function testTheMediaAltWinsOverThePageTitle(): void
    {
        $media = $this->createMedia();
        $media->setAlt('A snowy summit');

        $html = $this->renderMainImage($media, 'Mountains of the world | Demo');

        self::assertStringContainsString('alt="A snowy summit"', $html);
        self::assertStringNotContainsString('Mountains of the world', $html);
    }

    public function testTheLocalizedAltWinsOverTheMediaAlt(): void
    {
        $media = $this->createMedia();
        $media->setAlt('A snowy summit');
        $media->editLocalizedAlt('fr', 'Un sommet enneigé');

        $html = $this->renderMainImage($media, 'Montagnes du monde', 'fr');

        self::assertStringContainsString('alt="Un sommet enneigé"', $html);
    }

    public function testThePageTitleIsUsedWhenTheMediaHasNoAlt(): void
    {
        $html = $this->renderMainImage($this->createMedia(), 'Mountains of the world');

        self::assertStringContainsString('alt="Mountains of the world"', $html);
    }

    public function testACaptionFromTheBodyDoesNotLeakIntoTheMainImage(): void
    {
        // Rendering `![caption](src)` in the content soft-sets the media alt in
        // memory, and the content is parsed before this block runs. The main image
        // is a distinct placement — it must keep its own alt.
        $media = $this->createMedia();
        $media->setAlt('A snowy summit');
        $media->setAlt('Caption from the body', true);

        $html = $this->renderMainImage($media, 'Mountains of the world');

        self::assertStringContainsString('alt="A snowy summit"', $html);
    }
}
