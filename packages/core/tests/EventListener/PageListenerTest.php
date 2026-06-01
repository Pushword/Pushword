<?php

namespace Pushword\Core\Tests\EventListener;

use DateTime;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class PageListenerTest extends KernelTestCase
{
    private EntityManager $em;

    /** @var string[] */
    private array $testSlugs = [];

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

        foreach ($this->testSlugs as $slug) {
            $page = $this->em->getRepository(Page::class)->findOneBy(['slug' => $slug, 'host' => 'localhost.dev']);
            if ($page instanceof Page) {
                $this->em->remove($page);
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

        $page->setSlug('slug-redirect-new');
        $this->em->flush();
        $this->em->clear();

        // No phantom page at the old slug — the old path lives on the destination page's redirectFrom.
        $phantom = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'slug-redirect-old', 'host' => 'localhost.dev']);
        self::assertNull($phantom, 'No phantom redirect page should be created for the old slug');

        $destination = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'slug-redirect-new', 'host' => 'localhost.dev']);
        self::assertNotNull($destination);
        self::assertSame(['slug-redirect-old' => 301], $destination->getRedirectFromMap());
    }

    public function testSlugChangeUpdatesExistingRedirectChain(): void
    {
        $page = $this->createPage('chain-test-a');
        $this->testSlugs[] = 'chain-test-a';
        $this->testSlugs[] = 'chain-test-b';
        $this->testSlugs[] = 'chain-test-c';

        // a→b
        $page->setSlug('chain-test-b');
        $this->em->flush();

        // b→c: both old paths now redirect straight to c (no chain), stored on the destination page.
        $page->setSlug('chain-test-c');
        $this->em->flush();
        $this->em->clear();

        self::assertNull($this->em->getRepository(Page::class)->findOneBy(['slug' => 'chain-test-a', 'host' => 'localhost.dev']));
        self::assertNull($this->em->getRepository(Page::class)->findOneBy(['slug' => 'chain-test-b', 'host' => 'localhost.dev']));

        $destination = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'chain-test-c', 'host' => 'localhost.dev']);
        self::assertNotNull($destination);
        self::assertSame(
            ['chain-test-a' => 301, 'chain-test-b' => 301],
            $destination->getRedirectFromMap(),
        );
    }

    public function testNoRedirectWhenSlugUnchanged(): void
    {
        $page = $this->createPage('no-redirect-test');
        $this->testSlugs[] = 'no-redirect-test';

        $page->setMainContent('Updated content');
        $this->em->flush();

        $count = $this->em->getRepository(Page::class)->count(['host' => 'localhost.dev', 'slug' => 'no-redirect-test']);
        self::assertSame(1, $count, 'No duplicate page should be created when slug is unchanged');
    }

    public function testNoRedirectAcrossHosts(): void
    {
        $page = $this->createPage('host-test-slug');
        $this->testSlugs[] = 'host-test-slug';
        $this->testSlugs[] = 'host-test-new';

        $page->setSlug('host-test-new');
        $this->em->flush();
        $this->em->clear();

        // The old path is recorded on the destination page (same host), not as a cross-host entry.
        $destination = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'host-test-new', 'host' => 'localhost.dev']);
        self::assertNotNull($destination);
        self::assertArrayHasKey('host-test-slug', $destination->getRedirectFromMap());

        $otherHost = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'host-test-slug', 'host' => 'other.dev']);
        self::assertNull($otherHost, 'Redirect should only exist on the same host');
    }

    private function createPage(string $slug): Page
    {
        $page = new Page();
        $page->setSlug($slug);
        $page->setH1('Test '.$slug);
        $page->host = 'localhost.dev';
        $page->locale = 'en';
        $page->createdAt = new DateTime();
        $page->updatedAt = new DateTime();
        $page->setMainContent('Test content');

        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }
}
