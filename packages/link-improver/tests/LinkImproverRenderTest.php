<?php

namespace Pushword\LinkImprover\Tests;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Component\EntityFilter\Filter\Markdown;
use Pushword\Core\Content\ContentPipelineFactory;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\LinkCollectorService;
use Pushword\Core\Site\SiteRegistry;
use Pushword\LinkImprover\AddedLinksRegistry;
use Pushword\LinkImprover\LinkImprover;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class LinkImproverRenderTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    protected function tearDown(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $stalePages = $entityManager->getRepository(Page::class)->createQueryBuilder('p')
            ->where("p.slug LIKE 'linkimp-%'")->getQuery()->getResult();
        foreach ($stalePages as $stalePage) {
            $entityManager->remove($stalePage);
        }

        $this->nameTheHomepage(''); // testAHomepageTargetIsLinkedAtTheRoot borrows a fixture page

        $entityManager->flush();

        parent::tearDown();
    }

    /**
     * The default cap (ratio 0.02, one link per 50 words, existing links
     * included) would refuse everything on short test contents — the absolute
     * form keeps the fixtures readable.
     */
    private function enable(float $maxLinks = 5.0): void
    {
        self::bootKernel();
        $site = self::getContainer()->get(SiteRegistry::class)->get(self::HOST);
        $site->setCustomProperty('link_improver', true);
        $site->setCustomProperty('link_improver_max_links', $maxLinks);
    }

    private function createTarget(string $slug = 'linkimp-kiwano', string $name = "Kiwano Melano\nhorned melon", string $locale = 'en', string $publishedAt = '-1 hour'): Page
    {
        $target = new Page();
        $target->host = self::HOST;
        $target->slug = $slug;
        $target->name = $name;
        $target->h1 = 'Target '.$slug;
        $target->locale = $locale;
        $target->publishedAt = new DateTime($publishedAt);
        $target->mainContent = 'The target page.';

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($target);
        $entityManager->flush();

        return $target;
    }

    /** The dev-app homepage ships without a name — give it one to make it a target. */
    private function nameTheHomepage(string $name): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $homepage = $entityManager->getRepository(Page::class)
            ->findOneBy(['host' => self::HOST, 'slug' => 'homepage']);
        self::assertInstanceOf(Page::class, $homepage);

        $homepage->name = $name;
        $entityManager->flush();
    }

    private function render(string $mainContent, string $slug = 'linkimp-context'): string
    {
        $page = new Page();
        $page->host = self::HOST;
        $page->locale = 'en';
        $page->slug = $slug;
        $page->h1 = 'Context';
        $page->mainContent = $mainContent;

        return self::getContainer()->get(ContentPipelineFactory::class)->get($page)->getMainContent();
    }

    public function testTheFilterSitsAfterMarkdownInEveryAppChain(): void
    {
        self::bootKernel();
        $filters = self::getContainer()->get(SiteRegistry::class)->get(self::HOST)->filters['main_content'] ?? null;
        self::assertIsArray($filters);

        $markdownAt = array_search(Markdown::class, $filters, true);
        self::assertIsInt($markdownAt);
        self::assertSame(LinkImprover::class, $filters[$markdownAt + 1] ?? null);
    }

    public function testANameMentionBecomesALinkAndIsReported(): void
    {
        $this->enable();
        $target = $this->createTarget();

        $rendered = $this->render('A paragraph mentioning Kiwano Melano in passing, nothing else.');

        self::assertStringContainsString('<a href="/linkimp-kiwano" data-auto-link>Kiwano Melano</a>', $rendered);

        // The added link is registered for pages_list's excludeAlreadyLinked…
        self::assertTrue(self::getContainer()->get(LinkCollectorService::class)->isSlugRegistered('linkimp-kiwano'));

        // …and reported for the audit surface.
        $reportedPage = new Page();
        $reportedPage->host = self::HOST;
        $reportedPage->slug = 'linkimp-context';
        self::assertSame(
            [['anchor' => 'Kiwano Melano', 'url' => '/linkimp-kiwano']],
            self::getContainer()->get(AddedLinksRegistry::class)->forPage($reportedPage)
        );

        self::assertNotNull($target->id);
    }

    public function testRenderingIsByteDeterministicAcrossRuns(): void
    {
        $this->enable();
        $this->createTarget();
        $this->createTarget('linkimp-melon-d-or', "melon d'or");

        $content = "Between the Kiwano Melano and the melon d'or, a rich market stall.";

        self::assertSame($this->render($content), $this->render($content, 'linkimp-context2'));
    }

    public function testSecondNameLineLinksWithItsOwnAnchor(): void
    {
        $this->enable();
        $this->createTarget();

        $rendered = $this->render('Some call it a horned melon, some do not.');

        self::assertStringContainsString('<a href="/linkimp-kiwano" data-auto-link>horned melon</a>', $rendered);
    }

    public function testAnAlreadyLinkedTargetGetsNoSecondLink(): void
    {
        $this->enable();
        $this->createTarget();

        $rendered = $this->render("See [the fruit page](/linkimp-kiwano) first.\n\nLater we mention Kiwano Melano again.");

        self::assertSame(1, substr_count($rendered, 'href="/linkimp-kiwano"'));
        self::assertStringNotContainsString('data-auto-link', $rendered);
    }

    public function testAnAbsoluteOrFragmentLinkAlreadyCountsAsLinked(): void
    {
        $this->enable();
        $this->createTarget();

        $rendered = $this->render(
            "See [the fruit page](https://localhost.dev/linkimp-kiwano#varieties) first.\n\n"
            .'Later we mention Kiwano Melano again.'
        );

        self::assertStringNotContainsString('data-auto-link', $rendered);
    }

    public function testAHomepageTargetIsLinkedAtTheRoot(): void
    {
        $this->enable();
        $this->nameTheHomepage('Zorglub Home');

        $rendered = $this->render('Everything starts at the Zorglub Home, they say.');

        self::assertStringContainsString('<a href="/" data-auto-link>Zorglub Home</a>', $rendered);
        // The root carries no slug to register for excludeAlreadyLinked.
        self::assertFalse(self::getContainer()->get(LinkCollectorService::class)->isSlugRegistered(''));
    }

    public function testAWildcardNameMatchesItsVariants(): void
    {
        $this->enable();
        $this->createTarget('linkimp-tmb', "Tour du Mont-Blanc\ntour du Mont*Blanc");

        $rendered = $this->render('They walked the tour du Mont du Blanc, twice.');

        self::assertStringContainsString('<a href="/linkimp-tmb" data-auto-link>tour du Mont du Blanc</a>', $rendered);
    }

    public function testTheCapCountsExistingLinks(): void
    {
        $this->enable(maxLinks: 1.0);
        $this->createTarget();
        $this->createTarget('linkimp-other', 'Zorglub');

        $rendered = $this->render('An [existing link](/linkimp-other) then a Kiwano Melano mention.');

        self::assertStringNotContainsString('data-auto-link', $rendered);
    }

    public function testNoLinkInsideInlineCode(): void
    {
        $this->enable();
        $this->createTarget();

        $rendered = $this->render('Run `Kiwano Melano` in your terminal.');

        self::assertStringNotContainsString('data-auto-link', $rendered);
    }

    public function testAPageNeverLinksItself(): void
    {
        $this->enable();
        $target = $this->createTarget();
        $target->mainContent = 'All about Kiwano Melano, the horned melon.';

        $rendered = self::getContainer()->get(ContentPipelineFactory::class)->get($target)->getMainContent();

        self::assertStringNotContainsString('data-auto-link', $rendered);
    }

    public function testAnUnpublishedTargetIsNotLinked(): void
    {
        $this->enable();
        $this->createTarget('linkimp-draft', 'Zorglub Fruit', publishedAt: '+1 week');

        $rendered = $this->render('The Zorglub Fruit is not out yet.');

        self::assertStringNotContainsString('data-auto-link', $rendered);
    }

    public function testAnotherLocaleIsNotLinked(): void
    {
        $this->enable();
        $this->createTarget('linkimp-fr', 'Wombat Doré', locale: 'fr');

        $rendered = $this->render('The Wombat Doré never shows up in English.');

        self::assertStringNotContainsString('data-auto-link', $rendered);
    }

    public function testDisabledByDefaultLeavesContentUntouched(): void
    {
        self::bootKernel();
        $this->createTarget();

        $rendered = $this->render('A paragraph mentioning Kiwano Melano in passing.');

        self::assertStringNotContainsString('data-auto-link', $rendered);
        self::assertStringNotContainsString('href="/linkimp-kiwano"', $rendered);
    }
}
