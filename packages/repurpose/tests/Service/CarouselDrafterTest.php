<?php

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Page;
use Pushword\Repurpose\Model\Carousel;
use Pushword\Repurpose\Service\CarouselDrafter;
use Pushword\Repurpose\Service\CarouselFactory;
use Pushword\Repurpose\Service\FormatRegistry;

#[Group('integration')]
final class CarouselDrafterTest extends TestCase
{
    private CarouselDrafter $drafter;

    protected function setUp(): void
    {
        $this->drafter = new CarouselDrafter(new FormatRegistry());
    }

    /**
     * Draft, then hydrate into the typed model so assertions run against real
     * Slide objects (this also proves the drafter emits a well-formed spec).
     */
    private function draftCarousel(Page $page, string $network = 'linkedin', string $format = 'linkedin-4-5'): Carousel
    {
        return new CarouselFactory()->fromArray($this->drafter->draft($page, $network, $format));
    }

    private function page(): Page
    {
        $page = new Page();
        $page->setSlug('blog/my-article');
        $page->setH1('How to repurpose your content');
        $page->setMainContent(<<<'MD'
            An intro paragraph that is not a section.

            ## First takeaway

            This is the lead sentence of the first section. And a second one.

            ![a photo](photo-one.jpg)

            ## Second takeaway

            {{ someTwigCall() }} The lead of the second section, after a Twig call.
            MD);

        return $page;
    }

    public function testDraftsCoverBodyAndCtaSlides(): void
    {
        $carousel = $this->draftCarousel($this->page());

        self::assertSame('blog/my-article', $carousel->page);
        self::assertSame('linkedin', $carousel->network);
        self::assertSame('draft', $carousel->status);

        // Cover + 2 sections + CTA = 4 slides.
        self::assertCount(4, $carousel->slides);
        self::assertSame('How to repurpose your content', $carousel->slides[0]->title);
        self::assertSame('First takeaway', $carousel->slides[1]->title);
        self::assertSame('Second takeaway', $carousel->slides[2]->title);
    }

    public function testSectionLeadSentenceIsExtractedAndStripped(): void
    {
        $carousel = $this->draftCarousel($this->page());

        self::assertSame('This is the lead sentence of the first section.', $carousel->slides[1]->paragraph);
        // Twig is stripped from the second section's lead.
        self::assertNotNull($carousel->slides[2]->paragraph);
        self::assertStringNotContainsString('{{', $carousel->slides[2]->paragraph);
        self::assertStringContainsString('The lead of the second section', $carousel->slides[2]->paragraph);
    }

    public function testSectionImageBecomesTheSlideBackground(): void
    {
        $carousel = $this->draftCarousel($this->page());

        self::assertNotNull($carousel->slides[1]->image);
        self::assertSame('photo-one.jpg', $carousel->slides[1]->image->media);
    }

    public function testUnknownFormatFallsBackToDefault(): void
    {
        $carousel = $this->draftCarousel($this->page(), 'linkedin', 'no-such-format');

        self::assertSame('linkedin-4-5', $carousel->format);
    }
}
