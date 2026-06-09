<?php

namespace Pushword\Api\Tests\Service;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Iterator;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Api\Service\PageFrontmatterMapper;
use Pushword\Core\Entity\Page;
use Pushword\Flat\Converter\PublishedAtConverter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class PageFrontmatterMapperTest extends KernelTestCase
{
    private PageFrontmatterMapper $mapper;

    private EntityManagerInterface $em;

    /** @var int[] */
    private array $createdPageIds = [];

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        /** @var PageFrontmatterMapper $mapper */
        $mapper = self::getContainer()->get(PageFrontmatterMapper::class);
        $this->mapper = $mapper;

        $this->em = self::getContainer()->get('doctrine.orm.default_entity_manager');
    }

    protected function tearDown(): void
    {
        foreach ($this->createdPageIds as $id) {
            $page = $this->em->find(Page::class, $id);
            if (null !== $page) {
                $this->em->remove($page);
            }
        }

        if ([] !== $this->createdPageIds) {
            $this->em->flush();
        }

        parent::tearDown();
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

    public function testVariantOfAndCustomCanonicalRoundtrip(): void
    {
        $host = 'variant-test-'.uniqid().'.example.com';

        // The master must exist so resolvePageRef() can find it by slug+host.
        $master = new Page();
        $master->host = $host;
        $master->setSlug('master-trek');
        $master->setMainContent('# Master');

        $this->em->persist($master);
        $this->em->flush();
        $this->createdPageIds[] = $master->id ?? 0;

        $variant = new Page();
        $variant->host = $host;
        $variant->setSlug('master-trek-self-guided');
        $variant->setMainContent('# Variant');

        $this->mapper->applyFrontmatter($variant, [
            'variantOf' => 'master-trek',
            'customCanonical' => 'https://example.com/canonical',
        ]);

        self::assertSame($master, $variant->getVariantOf());
        self::assertTrue($variant->isVariant());
        self::assertSame('https://example.com/canonical', $variant->getCustomCanonical());

        // Both fields are emitted back in the frontmatter shape.
        $shape = $this->mapper->toArray($variant);
        self::assertSame('master-trek', $shape['frontmatter']['variantOf']);
        self::assertSame('https://example.com/canonical', $shape['frontmatter']['customCanonical']);

        // Clearing the relation un-links and resets the canonical override.
        $this->mapper->applyFrontmatter($variant, ['variantOf' => '', 'customCanonical' => null]);
        self::assertNull($variant->getVariantOf());
        self::assertFalse($variant->isVariant());
        self::assertNull($variant->getCustomCanonical());
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

    /**
     * Page::$publishedAt is mapped DATETIME_MUTABLE; a DateTimeImmutable would be
     * accepted in memory but rejected by Doctrine at flush. Accept both the flat
     * `Y-m-d H:i` shape and ISO 8601, and always store a mutable DateTime.
     *
     * @return Iterator<string, array{string}>
     */
    public static function publishedAtFormatProvider(): Iterator
    {
        yield 'flat Y-m-d H:i' => ['2026-04-09 10:00'];
        yield 'iso 8601' => ['2026-04-09T10:00:00+00:00'];
    }

    #[DataProvider('publishedAtFormatProvider')]
    public function testApplyFrontmatterStoresMutableDateTimeAndFlushes(string $publishedAt): void
    {
        $host = 'api-test-'.uniqid().'.example.com';
        $page = new Page();
        $page->host = $host;
        $page->setSlug('published-'.uniqid());
        $page->setMainContent('# Content');

        $this->mapper->applyFrontmatter($page, ['publishedAt' => $publishedAt]);

        // DateTimeImmutable does not extend DateTime, so this also asserts the
        // stored value is mutable as Doctrine's DATETIME_MUTABLE column requires.
        $stored = $page->getPublishedAt();
        self::assertInstanceOf(DateTime::class, $stored);
        self::assertSame('2026-04-09 10:00', $stored->format('Y-m-d H:i'));

        // Reproduces the production crash: a DateTimeImmutable throws
        // Doctrine\DBAL\Types\Exception\InvalidType here.
        $this->em->persist($page);
        $this->em->flush();
        $this->createdPageIds[] = $page->id ?? 0;

        self::assertNotNull($page->id);
    }

    public function testHoldPublicationRoundtrips(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->setSlug('held');

        // Held via API: stored as a timestamp, exposed back as a boolean.
        $this->mapper->applyFrontmatter($page, ['holdPublication' => true]);
        self::assertTrue($page->isHoldPublication());
        self::assertTrue($this->mapper->toArray($page)['frontmatter']['holdPublication']);

        // Releasing via API clears it.
        $this->mapper->applyFrontmatter($page, ['holdPublication' => false]);
        self::assertFalse($page->isHoldPublication());
        self::assertNull($page->getHoldPublicationAt());
    }

    public function testApplyFrontmatterAcceptsDraftSentinel(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->setSlug('draft-page');

        $this->mapper->applyFrontmatter($page, ['publishedAt' => PublishedAtConverter::DRAFT_VALUE]);

        self::assertNull($page->getPublishedAt());
    }

    /**
     * @return Iterator<string, array{mixed}>
     */
    public static function unparsablePublishedAtProvider(): Iterator
    {
        yield 'garbage string' => ['not-a-date'];
        yield 'empty string' => [''];
        yield 'null' => [null];
    }

    #[DataProvider('unparsablePublishedAtProvider')]
    public function testApplyFrontmatterMapsUnparsablePublishedAtToNull(mixed $publishedAt): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->setSlug('about');

        $this->mapper->applyFrontmatter($page, ['publishedAt' => $publishedAt]);

        self::assertNull($page->getPublishedAt());
    }
}
