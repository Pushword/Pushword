<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\EventListener;

use DateTime;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class PageListenerTest extends KernelTestCase
{
    private const string OTHER_HOST = 'pushword.piedweb.com';

    private EntityManager $em;

    /** @var string[] */
    private array $testSlugs = [];

    /** @var string[] */
    private array $otherHostSlugs = [];

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->em = $em;
    }

    protected function tearDown(): void
    {
        $this->em->clear();

        $slugsByHost = ['localhost.dev' => $this->testSlugs, self::OTHER_HOST => $this->otherHostSlugs];
        foreach ($slugsByHost as $host => $slugs) {
            foreach ($slugs as $slug) {
                $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => $slug, 'host' => $host]);
                if ($page instanceof Page) {
                    $this->em->remove($page);
                }
            }
        }

        $this->em->flush();
        parent::tearDown();
    }

    public function testSlugChangeCreatesRedirect(): void
    {
        $page = $this->createPage('slug-redirect-old');
        $this->testSlugs[] = 'slug-redirect-old';
        $this->testSlugs[] = 'slug-redirect-new';

        $page->slug = 'slug-redirect-new';
        $this->em->flush();
        $this->em->clear();

        // No phantom page at the old slug — the old path lives on the destination page's redirectFrom.
        $phantom = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'slug-redirect-old', 'host' => 'localhost.dev']);
        self::assertNull($phantom, 'No phantom redirect page should be created for the old slug');

        $destination = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'slug-redirect-new', 'host' => 'localhost.dev']);
        self::assertNotNull($destination);
        self::assertSame(['slug-redirect-old' => 301], $destination->redirectFrom);
    }

    public function testSlugChangeUpdatesExistingRedirectChain(): void
    {
        $page = $this->createPage('chain-test-a');
        $this->testSlugs[] = 'chain-test-a';
        $this->testSlugs[] = 'chain-test-b';
        $this->testSlugs[] = 'chain-test-c';

        // a→b
        $page->slug = 'chain-test-b';
        $this->em->flush();

        // b→c: both old paths now redirect straight to c (no chain), stored on the destination page.
        $page->slug = 'chain-test-c';
        $this->em->flush();
        $this->em->clear();

        self::assertNull($this->em->getRepository(Page::class)->findOneBy(['slug' => 'chain-test-a', 'host' => 'localhost.dev']));
        self::assertNull($this->em->getRepository(Page::class)->findOneBy(['slug' => 'chain-test-b', 'host' => 'localhost.dev']));

        $destination = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'chain-test-c', 'host' => 'localhost.dev']);
        self::assertNotNull($destination);
        self::assertSame(
            ['chain-test-a' => 301, 'chain-test-b' => 301],
            $destination->redirectFrom,
        );
    }

    public function testNoRedirectWhenSlugUnchanged(): void
    {
        $page = $this->createPage('no-redirect-test');
        $this->testSlugs[] = 'no-redirect-test';

        $page->mainContent = 'Updated content';
        $this->em->flush();

        $count = $this->em->getRepository(Page::class)->count(['host' => 'localhost.dev', 'slug' => 'no-redirect-test']);
        self::assertSame(1, $count, 'No duplicate page should be created when slug is unchanged');
    }

    public function testNoRedirectAcrossHosts(): void
    {
        $page = $this->createPage('host-test-slug');
        $this->testSlugs[] = 'host-test-slug';
        $this->testSlugs[] = 'host-test-new';

        $page->slug = 'host-test-new';
        $this->em->flush();
        $this->em->clear();

        // The old path is recorded on the destination page (same host), not as a cross-host entry.
        $destination = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'host-test-new', 'host' => 'localhost.dev']);
        self::assertNotNull($destination);
        self::assertArrayHasKey('host-test-slug', $destination->redirectFrom);

        $otherHost = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'host-test-slug', 'host' => 'other.dev']);
        self::assertNull($otherHost, 'Redirect should only exist on the same host');
    }

    public function testAPageSavedWithoutSlugTakesItsH1(): void
    {
        $this->testSlugs[] = 'trois-semaines-en-roumanie';
        $this->testSlugs[] = 'trois-semaines-en-roumanie-2';

        $first = $this->createPage('', h1: 'Trois semaines en Roumanie');
        $second = $this->createPage('', h1: 'Trois semaines en Roumanie');

        self::assertSame('trois-semaines-en-roumanie', $first->slug);
        self::assertSame('trois-semaines-en-roumanie-2', $second->slug, 'A slug already used on the host is suffixed');
    }

    public function testTheSuffixKeepsCountingWhileTheSlugIsTaken(): void
    {
        $this->testSlugs[] = 'gorges-du-tarn';
        $this->testSlugs[] = 'gorges-du-tarn-2';
        $this->testSlugs[] = 'gorges-du-tarn-3';

        $this->createPage('', h1: 'Gorges du Tarn');
        $this->createPage('', h1: 'Gorges du Tarn');
        $third = $this->createPage('', h1: 'Gorges du Tarn');

        self::assertSame('gorges-du-tarn-3', $third->slug);
    }

    public function testASlugSharingTheBaseAsPrefixDoesNotTakeItsPlace(): void
    {
        $this->testSlugs[] = 'gorges-de-l-ardeche-en-canoe';
        $this->testSlugs[] = 'gorges-de-l-ardeche';

        $this->createPage('gorges-de-l-ardeche-en-canoe');
        $page = $this->createPage('', h1: "Gorges de l'Ardèche");

        self::assertSame('gorges-de-l-ardeche', $page->slug);
    }

    public function testASlugUsedOnAnotherHostIsNotSuffixed(): void
    {
        $this->otherHostSlugs[] = 'col-du-galibier';
        $this->testSlugs[] = 'col-du-galibier';

        $this->createPage('col-du-galibier', host: self::OTHER_HOST);
        $page = $this->createPage('', h1: 'Col du Galibier');

        self::assertSame('col-du-galibier', $page->slug, 'Slugs are unique per host, not across hosts');
    }

    public function testPagesSavedInTheSameFlushGetDistinctSlugs(): void
    {
        // An import persists its pages before a single flush: the second one cannot
        // find the first in the database yet.
        $this->testSlugs[] = 'lac-de-sainte-croix';
        $this->testSlugs[] = 'lac-de-sainte-croix-2';

        $first = $this->buildPage('', h1: 'Lac de Sainte-Croix');
        $second = $this->buildPage('', h1: 'Lac de Sainte-Croix');
        $this->em->persist($first);
        $this->em->persist($second);
        $this->em->flush();

        self::assertSame('lac-de-sainte-croix', $first->slug);
        self::assertSame('lac-de-sainte-croix-2', $second->slug);
    }

    public function testASlugPendingInTheSameFlushOnAnotherHostIsNotSuffixed(): void
    {
        $this->otherHostSlugs[] = 'lac-de-serre-poncon';
        $this->testSlugs[] = 'lac-de-serre-poncon';

        $other = $this->buildPage('', h1: 'Lac de Serre-Ponçon', host: self::OTHER_HOST);
        $page = $this->buildPage('', h1: 'Lac de Serre-Ponçon');
        $this->em->persist($other);
        $this->em->persist($page);
        $this->em->flush();

        self::assertSame('lac-de-serre-poncon', $other->slug);
        self::assertSame('lac-de-serre-poncon', $page->slug, 'Only pages of the same host count, stored or pending');
    }

    public function testTheH1WinsOverTheTitle(): void
    {
        $this->testSlugs[] = 'randonnee-dans-les-carpates';

        $page = $this->createPage('', h1: 'Randonnée dans les Carpates', title: 'Carpates : 10 jours de marche');

        self::assertSame('randonnee-dans-les-carpates', $page->slug);
    }

    public function testAPageWithoutSlugNorH1TakesItsTitle(): void
    {
        $this->testSlugs[] = 'velo-en-transylvanie';

        $page = $this->createPage('', h1: '', title: 'Vélo en Transylvanie');

        self::assertSame('velo-en-transylvanie', $page->slug);
    }

    public function testAnH1WithoutLettersFallsBackToTheTitle(): void
    {
        $this->testSlugs[] = 'velo-dans-le-delta';

        $page = $this->createPage('', h1: '🚴 — ?', title: 'Vélo dans le delta');

        self::assertSame('velo-dans-le-delta', $page->slug);
    }

    public function testALongH1IsCutToLeaveRoomForASuffix(): void
    {
        $page = $this->createPage('', h1: str_repeat('Carpathes ', 28));
        $this->testSlugs[] = $page->slug;

        // The 140-char cut lands right after a hyphen, which must not be kept.
        self::assertSame(str_repeat('carpathes-', 13).'carpathes', $page->slug);
    }

    public function testAPageWithNothingToNameItKeepsARandomSlug(): void
    {
        $page = $this->createPage('', h1: '');
        $this->testSlugs[] = $page->slug;

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $page->slug);
    }

    private function createPage(string $slug, ?string $h1 = null, string $title = '', string $host = 'localhost.dev'): Page
    {
        $page = $this->buildPage($slug, $h1, $title, $host);

        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    private function buildPage(string $slug, ?string $h1 = null, string $title = '', string $host = 'localhost.dev'): Page
    {
        $page = new Page();
        $page->slug = $slug;
        $page->h1 = $h1 ?? 'Test '.$slug;
        $page->title = $title;
        $page->host = $host;
        $page->locale = 'en';
        $page->createdAt = new DateTime();
        $page->updatedAt = new DateTime();
        $page->mainContent = 'Test content';

        return $page;
    }
}
