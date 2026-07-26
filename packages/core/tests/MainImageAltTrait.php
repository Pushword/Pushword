<?php

namespace Pushword\Core\Tests;

use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Twig\Environment;

/**
 * The alt cascade every main image template owes: localized alt, then the media's
 * own alt, then the page title. Templates opt in by naming their template.
 */
trait MainImageAltTrait
{
    abstract protected function mainImageTemplate(): string;

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

    public function testTheAltStaysEmptyWhenNothingCanFillIt(): void
    {
        // Nothing to say about the image: a valueless alt marks it decorative, which
        // beats leaking the file name to a screen reader.
        $html = $this->renderMainImage($this->createMedia());

        self::assertStringContainsString('<img ', $html);
        self::assertStringNotContainsString('alt="', $html);
    }

    protected function renderMainImage(Media $media, string $title = '', string $locale = 'en'): string
    {
        self::bootKernel();

        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        $page = new Page();
        $page->setSlug('a-page');
        $page->locale = $locale;
        $page->setTitle($title);
        $page->setMainImage($media);

        return $twig->load($this->mainImageTemplate())
            ->renderBlock('main_image', ['page' => $page]);
    }

    protected function createMedia(): Media
    {
        $media = new Media();
        $media->setFileName('mountain.jpg');
        $media->imageData->setDimensions([1200, 800]);

        return $media;
    }
}
