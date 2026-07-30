<?php

namespace Pushword\Newsletter\Tests\Content;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Newsletter\Content\PagePlaceholders;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class PagePlaceholdersTest extends KernelTestCase
{
    public function testTheFourValuesAPageMayLend(): void
    {
        $rendered = $this->placeholders()->render(
            '# {{ page.h1 }}'."\n\n".'{{ page.excerpt }}'."\n\n".'![]({{ page.mainImage }})'."\n\n".'[Read]({{ page.url }})',
            $this->page(),
        );

        self::assertSame(
            '# Hello'."\n\n".'What it is about.'."\n\n"
            .'![](https://localhost.dev/media/default/photo.jpg)'."\n\n"
            .'[Read](https://localhost.dev/blog/hello)',
            $rendered,
        );
    }

    /** Spacing inside the braces is Twig's habit; the substitution keeps it. */
    public function testWhitespaceInsideTheBracesIsTolerated(): void
    {
        self::assertSame('Hello / Hello', $this->placeholders()->render('{{page.h1}} / {{   page.h1   }}', $this->page()));
    }

    /** A typo must show up in the preview rather than vanish from the mail. */
    public function testAnUnknownPlaceholderIsLeftWhereItIs(): void
    {
        self::assertSame('{{ page.title }}', $this->placeholders()->render('{{ page.title }}', $this->page()));
    }

    /** The homepage has no slug of its own; the URL must still be one. */
    public function testTheHomepageStillGetsAUrl(): void
    {
        $page = $this->page();
        $page->setSlug('homepage');

        self::assertSame('https://localhost.dev/', $this->placeholders()->url($page));
    }

    public function testAPageWithoutAMainImageLendsAnEmptyString(): void
    {
        $page = $this->page();
        $page->setMainImage(null);

        self::assertSame('![]()', $this->placeholders()->render('![]({{ page.mainImage }})', $page));
    }

    private function page(): Page
    {
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->setSlug('blog/hello');
        $page->setH1('Hello');
        $page->setSearchExcerpt('What it is about.');

        // Not persisted: the renderer only ever reads the file name, but a page
        // refuses a main image that has no dimensions.
        $media = new Media();
        $media->setFileName('photo.jpg');
        $media->setDimensions([800, 600]);

        $page->setMainImage($media);

        return $page;
    }

    private function placeholders(): PagePlaceholders
    {
        self::bootKernel();

        return self::getContainer()->get(PagePlaceholders::class);
    }
}
