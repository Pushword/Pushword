<?php

namespace Pushword\Api\Tests\Service;

use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Iterator;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Api\Service\InvalidFrontmatterException;
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
                // Unlink first: translation rows point at the page from both sides.
                foreach ($page->translations->toArray() as $translation) {
                    $page->removeTranslation($translation);
                }

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
        $page->slug = 'about';
        $page->h1 = 'About us';
        $page->title = 'About | Example';
        $page->metaRobots = 'noindex';
        $page->mainContent = '# Hello';
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
        $page->slug = 'initial';

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

        self::assertSame('New title', $page->h1);
        self::assertSame('SEO', $page->title);
        self::assertSame('Breadcrumb', $page->name);
        self::assertSame('index', $page->metaRobots);
        self::assertSame('fr', $page->locale);
        self::assertSame('custom.twig', $page->template);
        self::assertSame('set via api', $page->editMessage);
        self::assertSame(['a', 'b', 'c'], $page->getTagList());
        self::assertSame(5, $page->weight);
        self::assertSame(['ogDescription' => 'desc'], $page->customProperties);
    }

    public function testRedirectFromRoundtrips(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->slug = 'dest';

        // Accepts a {path: code} map and a Jekyll-style bare list (→ 301).
        $this->mapper->applyFrontmatter($page, ['redirectFrom' => ['old-one' => 302, 'old-two']]);
        self::assertSame(['old-one' => 302, 'old-two' => 301], $page->redirectFrom);

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
        $master->slug = 'master-trek';
        $master->mainContent = '# Master';

        $this->em->persist($master);
        $this->em->flush();
        $this->createdPageIds[] = $master->id ?? 0;

        $variant = new Page();
        $variant->host = $host;
        $variant->slug = 'master-trek-self-guided';
        $variant->mainContent = '# Variant';

        $this->mapper->applyFrontmatter($variant, [
            'variantOf' => 'master-trek',
            'customCanonical' => 'https://example.com/canonical',
        ]);

        self::assertSame($master, $variant->variantOf);
        self::assertTrue($variant->isVariant());
        self::assertSame('https://example.com/canonical', $variant->customCanonical);

        // Both fields are emitted back in the frontmatter shape.
        $shape = $this->mapper->toArray($variant);
        self::assertSame('master-trek', $shape['frontmatter']['variantOf']);
        self::assertSame('https://example.com/canonical', $shape['frontmatter']['customCanonical']);

        // Clearing the relation un-links and resets the canonical override.
        $this->mapper->applyFrontmatter($variant, ['variantOf' => '', 'customCanonical' => null]);
        self::assertNull($variant->variantOf);
        self::assertFalse($variant->isVariant());
        self::assertNull($variant->customCanonical);
    }

    public function testTranslationsLinkAcrossHostsAndRoundTrip(): void
    {
        $suffix = uniqid();
        // One locale per host is the norm, so the sibling lives on another host —
        // which must be a registered one for the "host/slug" ref to be told apart
        // from a nested slug.
        $english = $this->persistPage('pushword.piedweb.com', 'about-'.$suffix, 'en');
        $french = $this->persistPage('api-test-'.$suffix.'.example.com', 'a-propos-'.$suffix, 'fr');

        $reference = 'pushword.piedweb.com/about-'.$suffix;
        $this->mapper->applyFrontmatter($french, ['translations' => [$reference]]);

        self::assertTrue($french->translations->contains($english));
        // The relation is kept symmetric, so only one side needs to be sent.
        self::assertTrue($english->translations->contains($french));
        self::assertSame($english, $french->getTranslation('en'));

        // The exported ref carries the host: a GET payload can be PUT back as-is.
        self::assertSame([$reference], $this->mapper->toArray($french)['frontmatter']['translations']);

        // The list is authoritative — an empty one unlinks the whole group.
        $this->mapper->applyFrontmatter($french, ['translations' => []]);
        self::assertCount(0, $french->translations);
        self::assertCount(0, $english->translations);
    }

    public function testTranslationsAcceptBareSlugOnTheSameHost(): void
    {
        $suffix = uniqid();
        $host = 'api-test-'.$suffix.'.example.com';
        $english = $this->persistPage($host, 'about-'.$suffix, 'en');
        $french = $this->persistPage($host, 'a-propos-'.$suffix, 'fr');

        // An app serving two locales from one host: the ref needs no host prefix,
        // and the export emits it back bare.
        $this->mapper->applyFrontmatter($french, ['translations' => ['about-'.$suffix]]);

        self::assertTrue($french->translations->contains($english));
        self::assertSame(['about-'.$suffix], $this->mapper->toArray($french)['frontmatter']['translations']);
    }

    public function testTranslationsAcceptNestedSlugNotMistakenForAHost(): void
    {
        $suffix = uniqid();
        $host = 'api-test-'.$suffix.'.example.com';
        $english = $this->persistPage($host, 'blog/about-'.$suffix, 'en');
        $french = $this->persistPage($host, 'a-propos-'.$suffix, 'fr');

        // "blog/…" looks like a "host/slug" ref but blog is not a registered host,
        // so the whole string must be read back as a nested same-host slug.
        $this->mapper->applyFrontmatter($french, ['translations' => ['blog/about-'.$suffix]]);

        self::assertTrue($french->translations->contains($english));
    }

    public function testTranslationsUnlinkOnlyTheDroppedReference(): void
    {
        $suffix = uniqid();
        $host = 'api-test-'.$suffix.'.example.com';
        $english = $this->persistPage($host, 'about-'.$suffix, 'en');
        $spanish = $this->persistPage($host, 'acerca-'.$suffix, 'es');
        $french = $this->persistPage($host, 'a-propos-'.$suffix, 'fr');
        $french->addTranslation($english);
        $french->addTranslation($spanish);

        // Re-sending the list without the Spanish page unlinks it and keeps the rest.
        $this->mapper->applyFrontmatter($french, ['translations' => ['about-'.$suffix]]);

        self::assertTrue($french->translations->contains($english));
        self::assertFalse($french->translations->contains($spanish));
        self::assertFalse($spanish->translations->contains($french));
    }

    public function testOmittedTranslationsKeyLeavesTheGroupUntouched(): void
    {
        $suffix = uniqid();
        $host = 'api-test-'.$suffix.'.example.com';
        $english = $this->persistPage($host, 'about-'.$suffix, 'en');
        $french = $this->persistPage($host, 'a-propos-'.$suffix, 'fr');
        $french->addTranslation($english);

        // A partial PATCH must not clear a group it says nothing about.
        $this->mapper->applyFrontmatter($french, ['h1' => 'À propos']);

        self::assertTrue($french->translations->contains($english));
    }

    /**
     * @return Iterator<string, array{mixed}>
     */
    public static function malformedTranslationsProvider(): Iterator
    {
        yield 'bare string instead of a list' => ['en/about'];
        yield 'list holding a non-string' => [[42]];
        yield 'list holding an empty string' => [['']];
    }

    #[DataProvider('malformedTranslationsProvider')]
    public function testTranslationsRejectMalformedPayload(mixed $translations): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->slug = 'a-propos';

        // A shape the mapper cannot read is a 422, never a silently ignored key.
        $this->expectException(InvalidFrontmatterException::class);
        $this->mapper->applyFrontmatter($page, ['translations' => $translations]);
    }

    public function testTranslationsRejectUnknownReferenceWithoutTouchingTheGroup(): void
    {
        $suffix = uniqid();
        $host = 'api-test-'.$suffix.'.example.com';
        $english = $this->persistPage($host, 'about-'.$suffix, 'en');
        $french = $this->persistPage($host, 'a-propos-'.$suffix, 'fr');
        $french->addTranslation($english);

        // A ref pointing nowhere is a client error (422), not a silent drop — and
        // it must leave the existing link intact rather than half-apply the list.
        try {
            $this->mapper->applyFrontmatter($french, ['translations' => ['about-'.$suffix, 'ghost-'.$suffix]]);
            self::fail('Expected an InvalidFrontmatterException for the unknown translation ref.');
        } catch (InvalidFrontmatterException $invalidFrontmatterException) {
            self::assertSame('translations', $invalidFrontmatterException->key);
            self::assertSame('ghost-'.$suffix, $invalidFrontmatterException->value);
        }

        self::assertTrue($french->translations->contains($english));
    }

    public function testApplyFrontmatterSkipsUnknownTypesAndPreservesExisting(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->slug = 'about';
        $page->h1 = 'Keep me';

        // Wrong type for h1 (int) should be ignored
        $this->mapper->applyFrontmatter($page, ['h1' => 42, 'name' => null]);

        self::assertSame('Keep me', $page->h1);
        self::assertSame('', $page->name);
    }

    public function testCustomPropertyDotKeyIsRoutedToCustomProperties(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->slug = 'about';

        $this->mapper->applyFrontmatter($page, ['customProperty.searchExcerpt' => 'short summary']);

        self::assertSame('short summary', $page->getCustomProperty('searchExcerpt'));
    }

    public function testTopLevelConverterManagedPropertyIsApplied(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->slug = 'about';

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
        $page->slug = 'about';

        // A machine client sends the raw integer instead of the human label.
        $this->mapper->applyFrontmatter($page, ['mainImageFormat' => 2]);

        self::assertSame(2, $page->getCustomProperty('mainImageFormat'));
    }

    public function testTopLevelConverterManagedPropertyResolvesHumanName(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->slug = 'about';

        // The None format's label is the symbol "∅"; the human name "None" (the
        // key suffix) must still resolve to its integer value, case-insensitively.
        $this->mapper->applyFrontmatter($page, ['mainImageFormat' => 'None']);
        self::assertSame(1, $page->getCustomProperty('mainImageFormat'));

        $this->mapper->applyFrontmatter($page, ['mainImageFormat' => 'none']);
        self::assertSame(1, $page->getCustomProperty('mainImageFormat'));
    }

    public function testTopLevelConverterManagedPropertyRejectsUnknownValue(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->slug = 'about';

        // Neither a label, a translation key, a number nor a known human name, so
        // it cannot resolve to the integer-backed mainImageFormat. Rejected here
        // so the API returns 422 instead of storing a string that crashes the
        // int-typed hero render.
        $this->expectException(InvalidFrontmatterException::class);
        $this->mapper->applyFrontmatter($page, ['mainImageFormat' => 'banana']);
    }

    public function testTopLevelConverterManagedPropertyNullIsSkipped(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->slug = 'about';

        // An explicit null is "unset", not an invalid value: skip it silently
        // rather than rejecting it as an unresolvable value.
        $this->mapper->applyFrontmatter($page, ['mainImageFormat' => null]);
        self::assertNull($page->getCustomProperty('mainImageFormat'));
    }

    public function testBareTopLevelCustomPropertyKeysRoundTripFromFlatSnapshot(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->slug = 'about';

        // The flat exporter unpacks customProperties to the top level, so a payload
        // built from a .md snapshot arrives with bare keys (no customProperty.*
        // prefix, no customProperties nesting). Both a managed key (searchExcerpt,
        // which has a dedicated getter) and an arbitrary unmanaged one (targetKeyword)
        // must survive the round-trip instead of being silently dropped.
        $this->mapper->applyFrontmatter($page, [
            'h1' => 'About',
            'searchExcerpt' => 'A short SEO summary.',
            'targetKeyword' => 'about us',
            'toc' => true,
        ]);

        self::assertSame('About', $page->h1); // recognized column still applied
        self::assertSame('A short SEO summary.', $page->getCustomProperty('searchExcerpt'));
        self::assertSame('A short SEO summary.', $page->getSearchExcerpt());
        self::assertSame('about us', $page->getCustomProperty('targetKeyword'));
        self::assertTrue($page->getCustomProperty('toc'));
    }

    public function testExportOnlyRevisionStampIsNotStoredAsCustomProperty(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->slug = 'about';

        // Every exported .md carries a read-only `revision:` stamp; an editor that
        // PUTs the snapshot's frontmatter verbatim includes it. It is the ETag
        // value, not page data — the reserved-keys guard must keep it out of
        // customProperties (storing it would also churn the computed revision).
        $this->mapper->applyFrontmatter($page, ['h1' => 'About', 'revision' => 'abc123']);

        self::assertNull($page->getCustomProperty('revision'));
        self::assertSame([], $page->customProperties);
    }

    public function testRealEntityColumnIsNotMisroutedToCustomProperties(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->slug = 'about';

        // holdPublicationAt is a real declared column the flat exporter can emit at
        // the top level; property_exists() must keep it out of customProperties so
        // it is not stored as a stray string key.
        $this->mapper->applyFrontmatter($page, ['holdPublicationAt' => '2026-01-01 00:00']);

        self::assertNull($page->getCustomProperty('holdPublicationAt'));
    }

    public function testSummaryReturnsLightProjection(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->slug = 'about';
        $page->h1 = 'About';
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
        self::assertSame('Preview', $page->h1);
        self::assertSame('# Body', $page->mainContent);
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
        $page->slug = 'published-'.uniqid();
        $page->mainContent = '# Content';

        $this->mapper->applyFrontmatter($page, ['publishedAt' => $publishedAt]);

        // DateTimeImmutable does not extend DateTime, so this also asserts the
        // stored value is mutable as Doctrine's DATETIME_MUTABLE column requires.
        $stored = $page->publishedAt;
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
        $page->slug = 'held';

        // Held via API: stored as a timestamp, exposed back as a boolean.
        $this->mapper->applyFrontmatter($page, ['holdPublication' => true]);
        self::assertTrue($page->isHoldPublication());
        self::assertTrue($this->mapper->toArray($page)['frontmatter']['holdPublication']);

        // Releasing via API clears it.
        $this->mapper->applyFrontmatter($page, ['holdPublication' => false]);
        self::assertFalse($page->isHoldPublication());
        self::assertNull($page->holdPublicationAt);
    }

    public function testApplyFrontmatterAcceptsDraftSentinel(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->slug = 'draft-page';

        $this->mapper->applyFrontmatter($page, ['publishedAt' => PublishedAtConverter::DRAFT_VALUE]);

        self::assertNull($page->publishedAt);
    }

    /**
     * @return Iterator<string, array{mixed}>
     */
    public static function emptyPublishedAtProvider(): Iterator
    {
        yield 'empty string' => [''];
        yield 'null' => [null];
    }

    #[DataProvider('emptyPublishedAtProvider')]
    public function testApplyFrontmatterMapsEmptyPublishedAtToNull(mixed $publishedAt): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->slug = 'about';

        $this->mapper->applyFrontmatter($page, ['publishedAt' => $publishedAt]);

        self::assertNull($page->publishedAt);
    }

    public function testExtendedPageResolvesAndRoundTrips(): void
    {
        $host = 'extended-test-'.uniqid().'.example.com';

        $base = new Page();
        $base->host = $host;
        $base->slug = 'base-layout';
        $base->mainContent = '# Base';

        $this->em->persist($base);
        $this->em->flush();
        $this->createdPageIds[] = $base->id ?? 0;

        $page = new Page();
        $page->host = $host;
        $page->slug = 'extending-page';

        $this->mapper->applyFrontmatter($page, ['extendedPage' => 'base-layout']);
        self::assertSame($base, $page->extendedPage);
        self::assertSame('base-layout', $this->mapper->toArray($page)['frontmatter']['extendedPage']);

        $this->mapper->applyFrontmatter($page, ['extendedPage' => null]);
        self::assertNull($page->extendedPage);
    }

    public function testHoldPublicationAtAppliesAndRejectsGarbage(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->slug = 'held';

        $this->mapper->applyFrontmatter($page, ['holdPublicationAt' => '2025-12-01 08:00']);
        self::assertSame('2025-12-01 08:00', $page->holdPublicationAt?->format('Y-m-d H:i'));
        self::assertSame(
            $page->holdPublicationAt->format(DateTimeInterface::ATOM),
            $this->mapper->toArray($page)['frontmatter']['holdPublicationAt'],
        );

        try {
            $this->mapper->applyFrontmatter($page, ['holdPublicationAt' => 'not-a-date']);
            self::fail('Expected InvalidFrontmatterException');
        } catch (InvalidFrontmatterException $invalidFrontmatterException) {
            self::assertSame('holdPublicationAt', $invalidFrontmatterException->key);
        }

        self::assertNotNull($page->holdPublicationAt, 'a rejected date must leave the hold untouched');
    }

    /**
     * A typo'd date must be rejected, not silently mapped to null: for
     * publishedAt a swallowed parse error would unpublish the page with a 200.
     */
    public function testApplyFrontmatterRejectsUnparsablePublishedAt(): void
    {
        $page = new Page();
        $page->host = 'example.com';
        $page->slug = 'about';
        $page->publishedAt = new DateTime('2025-06-01 10:30:00');

        try {
            $this->mapper->applyFrontmatter($page, ['publishedAt' => 'not-a-date']);
            self::fail('Expected InvalidFrontmatterException');
        } catch (InvalidFrontmatterException $invalidFrontmatterException) {
            self::assertSame('publishedAt', $invalidFrontmatterException->key);
        }

        self::assertNotNull($page->publishedAt, 'a rejected date must leave the column untouched');
    }

    private function persistPage(string $host, string $slug, string $locale): Page
    {
        $page = new Page();
        $page->host = $host;
        $page->slug = $slug;
        $page->locale = $locale;
        $page->mainContent = '# '.$slug;

        $this->em->persist($page);
        $this->em->flush();
        $this->createdPageIds[] = $page->id ?? 0;

        return $page;
    }
}
