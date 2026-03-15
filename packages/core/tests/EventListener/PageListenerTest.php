<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\EventListener;

use DateTime;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
class PageListenerTest extends KernelTestCase
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

        $redirect = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'slug-redirect-old', 'host' => 'localhost.dev']);
        self::assertNotNull($redirect, 'Redirect page should be created for old slug');
        self::assertTrue($redirect->hasRedirection());
        self::assertSame('Location: /slug-redirect-new 301', $redirect->getMainContent());
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

        // b→c, and a→b should become a→c
        $page->setSlug('chain-test-c');
        $this->em->flush();
        $this->em->clear();

        $redirectA = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'chain-test-a', 'host' => 'localhost.dev']);
        self::assertNotNull($redirectA);
        self::assertSame('Location: /chain-test-c 301', $redirectA->getMainContent());

        $redirectB = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'chain-test-b', 'host' => 'localhost.dev']);
        self::assertNotNull($redirectB);
        self::assertSame('Location: /chain-test-c 301', $redirectB->getMainContent());
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

        $redirect = $this->em->getRepository(Page::class)->findOneBy(['slug' => 'host-test-slug', 'host' => 'localhost.dev']);
        self::assertNotNull($redirect);

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
