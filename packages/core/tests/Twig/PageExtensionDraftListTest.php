<?php

namespace Pushword\Core\Tests\Twig;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\PageExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * draft_list() must return the strict complement of pages_list(): every page the
 * published query hides (never scheduled + scheduled for later) and nothing it shows.
 */
#[Group('integration')]
final class PageExtensionDraftListTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    private const string OTHER_HOST = 'pushword.piedweb.com';

    private const string DRAFT_SLUG = 'draft-list-test-never-scheduled';

    private const string SCHEDULED_SLUG = 'draft-list-test-scheduled-later';

    private const string PUBLISHED_SLUG = 'draft-list-test-online';

    private const string NOINDEX_DRAFT_SLUG = 'draft-list-test-noindex';

    private const string OTHER_HOST_DRAFT_SLUG = 'draft-list-test-other-host';

    /** Matches every fixture at once, so both functions always run the very same search. */
    private const string SEARCH = 'slug:'.self::DRAFT_SLUG
        .' OR slug:'.self::SCHEDULED_SLUG
        .' OR slug:'.self::PUBLISHED_SLUG
        .' OR slug:'.self::NOINDEX_DRAFT_SLUG
        .' OR slug:'.self::OTHER_HOST_DRAFT_SLUG;

    private const array ALL_SLUGS = [
        self::DRAFT_SLUG,
        self::SCHEDULED_SLUG,
        self::PUBLISHED_SLUG,
        self::NOINDEX_DRAFT_SLUG,
        self::OTHER_HOST_DRAFT_SLUG,
    ];

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        self::getContainer()->get(SiteRegistry::class)->switchSite(self::HOST);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $this->createPage(self::DRAFT_SLUG, null);
        $this->createPage(self::SCHEDULED_SLUG, new DateTime('+1 month'));
        $this->createPage(self::PUBLISHED_SLUG, new DateTime('-1 day'));
        $this->createPage(self::NOINDEX_DRAFT_SLUG, null, metaRobots: 'noindex');
        $this->createPage(self::OTHER_HOST_DRAFT_SLUG, null, host: self::OTHER_HOST);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        foreach (self::ALL_SLUGS as $slug) {
            foreach ($this->entityManager->getRepository(Page::class)->findBy(['slug' => $slug]) as $page) {
                $this->entityManager->remove($page);
            }
        }

        $this->entityManager->flush();

        parent::tearDown();
    }

    private function createPage(string $slug, ?DateTime $publishedAt, string $host = self::HOST, string $metaRobots = ''): void
    {
        $page = new Page();
        $page->host = $host;
        $page->locale = 'en';
        $page->metaRobots = $metaRobots;
        $page->setSlug($slug);
        $page->setH1('Draft list fixture '.$slug);
        $page->setMainContent('Draft list fixture content.');
        $page->setPublishedAt($publishedAt);

        $this->entityManager->persist($page);
    }

    private function ext(): PageExtension
    {
        return self::getContainer()->get(PageExtension::class);
    }

    private function login(string $role): void
    {
        $user = new InMemoryUser('someone@example.tld', null, [$role]);
        self::getContainer()->get(TokenStorageInterface::class)
            ->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    public function testDraftListShowsUnpublishedPagesAndHidesOnlineOnes(): void
    {
        $this->login('ROLE_EDITOR');

        $rendered = $this->ext()->renderDraftList(self::SEARCH);

        self::assertStringContainsString(self::DRAFT_SLUG, $rendered);
        self::assertStringContainsString(self::SCHEDULED_SLUG, $rendered);
        self::assertStringNotContainsString(self::PUBLISHED_SLUG, $rendered);
    }

    /** The mirror assertion: pages_list on the same search shows exactly what draft_list hides. */
    public function testPagesListShowsOnlineOnlyOnTheSameSearch(): void
    {
        $rendered = $this->ext()->renderPagesList(self::SEARCH);

        self::assertStringContainsString(self::PUBLISHED_SLUG, $rendered);
        self::assertStringNotContainsString(self::DRAFT_SLUG, $rendered);
        self::assertStringNotContainsString(self::SCHEDULED_SLUG, $rendered);
    }

    /**
     * A draft list audits one host: another host's drafts must never surface in it,
     * whatever the search matches.
     */
    public function testDraftListDoesNotLeakDraftsFromAnotherHost(): void
    {
        $this->login('ROLE_EDITOR');

        $rendered = $this->ext()->renderDraftList(self::SEARCH);

        self::assertStringContainsString(self::DRAFT_SLUG, $rendered);
        self::assertStringNotContainsString(self::OTHER_HOST_DRAFT_SLUG, $rendered);

        // Guards the assertion above from passing vacuously: the very same search run
        // against the other host does find that draft, so it is reachable — just not here.
        self::assertStringContainsString(
            self::OTHER_HOST_DRAFT_SLUG,
            $this->ext()->renderDraftList(self::SEARCH, host: self::OTHER_HOST),
        );
    }

    /** Deliberate divergence from pages_list(): a noindex draft is still an editor's draft. */
    public function testDraftListKeepsNoindexPages(): void
    {
        $this->login('ROLE_EDITOR');

        self::assertStringContainsString(self::NOINDEX_DRAFT_SLUG, $this->ext()->renderDraftList(self::SEARCH));
    }

    public function testDraftListRendersNothingForAnonymousVisitor(): void
    {
        self::assertSame('', $this->ext()->renderDraftList(self::SEARCH));
    }

    public function testDraftListRendersNothingForNonEditorUser(): void
    {
        $this->login('ROLE_USER');

        self::assertSame('', $this->ext()->renderDraftList(self::SEARCH));
    }
}
