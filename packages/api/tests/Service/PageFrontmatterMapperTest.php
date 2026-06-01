<?php

namespace Pushword\Api\Tests\Service;

use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Api\Service\PageFrontmatterMapper;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class PageFrontmatterMapperTest extends KernelTestCase
{
    private PageFrontmatterMapper $mapper;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        /** @var PageFrontmatterMapper $mapper */
        $mapper = self::getContainer()->get(PageFrontmatterMapper::class);
        $this->mapper = $mapper;
    }

    public function testToArraySplitsFrontmatterFromBody(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->setSlug('about');
        $page->setH1('About us');
        $page->setTitle('About | Example');
        $page->setMetaRobots('noindex');
        $page->setMainContent('# Hello');
        $page->setTags(['team', 'history']);
        $page->setCustomProperty('ogTitle', 'OG About');

        $shape = $this->mapper->toArray($page);

        self::assertSame('example.com', $shape['frontmatter']['host']);
        self::assertSame('about', $shape['frontmatter']['slug']);
        self::assertSame('About us', $shape['frontmatter']['h1']);
        self::assertSame('About | Example', $shape['frontmatter']['title']);
        self::assertSame('noindex', $shape['frontmatter']['metaRobots']);
        self::assertSame(['history', 'team'], $shape['frontmatter']['tags']);
        self::assertSame(['ogTitle' => 'OG About'], $shape['frontmatter']['customProperties']);
        self::assertSame('# Hello', $shape['body']);
    }

    public function testApplyFrontmatterRoundtrips(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->setSlug('initial');

        $this->mapper->applyFrontmatter($page, [
            'h1' => 'New title',
            'title' => 'SEO',
            'name' => 'Breadcrumb',
            'metaRobots' => 'index',
            'locale' => 'fr',
            'template' => 'custom.twig',
            'editMessage' => 'set via api',
            'tags' => ['a', 'b', 42, 'c'], // mixed types filtered to strings
            'weight' => '5',
            'customProperties' => ['ogDescription' => 'desc'],
        ]);

        self::assertSame('New title', $page->getH1());
        self::assertSame('SEO', $page->title);
        self::assertSame('Breadcrumb', $page->name);
        self::assertSame('index', $page->metaRobots);
        self::assertSame('fr', $page->locale);
        self::assertSame('custom.twig', $page->template);
        self::assertSame('set via api', $page->editMessage);
        self::assertSame(['a', 'b', 'c'], $page->getTagList());
        self::assertSame(5, $page->getWeight());
        self::assertSame(['ogDescription' => 'desc'], $page->getCustomProperties());
    }

    public function testRedirectFromRoundtrips(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->setSlug('dest');

        // Accepts a {path: code} map and a Jekyll-style bare list (→ 301).
        $this->mapper->applyFrontmatter($page, ['redirectFrom' => ['old-one' => 302, 'old-two']]);
        self::assertSame(['old-one' => 302, 'old-two' => 301], $page->getRedirectFromMap());

        // Emitted back in the frontmatter shape.
        $shape = $this->mapper->toArray($page);
        self::assertSame(['old-one' => 302, 'old-two' => 301], $shape['frontmatter']['redirectFrom']);
    }

    public function testApplyFrontmatterSkipsUnknownTypesAndPreservesExisting(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->setSlug('about');
        $page->setH1('Keep me');

        // Wrong type for h1 (int) should be ignored
        $this->mapper->applyFrontmatter($page, ['h1' => 42, 'name' => null]);

        self::assertSame('Keep me', $page->getH1());
        self::assertSame('', $page->name);
    }

    public function testCustomPropertyDotKeyIsRoutedToCustomProperties(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->setSlug('about');

        $this->mapper->applyFrontmatter($page, ['customProperty.searchExcerpt' => 'short summary']);

        self::assertSame('short summary', $page->getCustomProperty('searchExcerpt'));
    }

    public function testTopLevelConverterManagedPropertyIsApplied(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->setSlug('about');

        // mainImageFormat is a managed custom property exposed at the top level in
        // the on-disk frontmatter shape; the human label must reach the entity as
        // its integer database value instead of being silently dropped.
        $this->mapper->applyFrontmatter($page, ['mainImageFormat' => 'Normal']);

        self::assertSame(0, $page->getCustomProperty('mainImageFormat'));
    }

    public function testTopLevelConverterManagedPropertyAcceptsRawValue(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->setSlug('about');

        // A machine client sends the raw integer instead of the human label.
        $this->mapper->applyFrontmatter($page, ['mainImageFormat' => 2]);

        self::assertSame(2, $page->getCustomProperty('mainImageFormat'));
    }

    public function testUnknownTopLevelKeyIsIgnored(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->setSlug('about');

        // A top-level key without a converter must not slip into custom properties:
        // the allowlist stays strict; custom data goes through customProperty.* only.
        $this->mapper->applyFrontmatter($page, ['notAColumn' => 'value']);

        self::assertNull($page->getCustomProperty('notAColumn'));
    }

    public function testSummaryReturnsLightProjection(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->setSlug('about');
        $page->setH1('About');
        $page->locale = 'en';

        $summary = $this->mapper->summary($page);

        self::assertSame('example.com', $summary['host']);
        self::assertSame('about', $summary['slug']);
        self::assertSame('About', $summary['h1']);
        self::assertSame('en', $summary['locale']);
        self::assertArrayHasKey('updatedAt', $summary);
    }

    public function testBuildTransientDoesNotPersist(): void
    {
        $page = $this->mapper->buildTransient('example.com', 'preview-page', ['h1' => 'Preview'], '# Body');

        self::assertNull($page->id);
        self::assertSame('Preview', $page->getH1());
        self::assertSame('# Body', $page->getMainContent());
    }
}
