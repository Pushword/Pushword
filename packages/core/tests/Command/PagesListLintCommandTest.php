<?php

namespace Pushword\Core\Tests\Command;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The lint exists because the parser cannot help: an unrecognised prefix is a
 * valid tag search and has to stay one. Running the search settles what parsing
 * cannot.
 */
#[Group('integration')]
final class PagesListLintCommandTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    private const string PREFIX = 'lint-fixture-';

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->entityManager->getRepository(Page::class)->findBy(['host' => self::HOST]) as $page) {
            if (str_starts_with($page->getSlug(), self::PREFIX)) {
                $this->entityManager->remove($page);
            }
        }

        $this->entityManager->flush();

        parent::tearDown();
    }

    private function createPage(string $slug, string $mainContent, string $tags = ''): void
    {
        $page = new Page();
        $page->host = self::HOST;
        $page->locale = 'en';
        $page->setSlug(self::PREFIX.$slug);
        $page->setH1('Lint fixture '.$slug);
        $page->setMainContent($mainContent);
        $page->setTags('' === $tags ? [] : [$tags]);
        $page->setPublishedAt(new DateTime('-1 day'));

        $this->entityManager->persist($page);
        $this->entityManager->flush();
    }

    /** @return array<string, mixed> */
    private function lint(): array
    {
        $application = new Application(self::$kernel ?? throw new LogicException());
        $tester = new CommandTester($application->find('pw:pages-list:lint'));
        $tester->execute(['--format' => 'agent', '--host' => self::HOST]);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(trim($tester->getDisplay()), true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param array<string, mixed> $report
     *
     * @return list<string>
     */
    private function deadSearches(array $report): array
    {
        /** @var list<array{search: string, page: string}> $dead */
        $dead = $report['dead'] ?? [];

        return array_values(array_map(
            static fn (array $finding): string => $finding['search'],
            array_filter($dead, static fn (array $finding): bool => str_starts_with($finding['page'], self::PREFIX)),
        ));
    }

    /**
     * The live bug this is written for: `tags:` is not a prefix, so the search
     * looks for a tag literally named `tags:travel`, matches nothing, and renders
     * an empty list with no error anywhere.
     */
    public function testAMistypedPrefixIsReportedBecauseItMatchesNothing(): void
    {
        $this->createPage('target', 'Nothing here.', 'travel');
        $this->createPage('lister', "{{ pages_list('tags:travel') }}");

        self::assertSame(['tags:travel'], $this->deadSearches($this->lint()));
    }

    /** The same search, spelled correctly, is not reported. */
    public function testASearchThatMatchesIsNotReported(): void
    {
        $this->createPage('target', 'Nothing here.', 'travel');
        $this->createPage('lister', "{{ pages_list('travel') }}");

        self::assertSame([], $this->deadSearches($this->lint()));
    }

    /**
     * A namespaced tag is indistinguishable from a mistyped prefix at parse
     * time, and must stay usable. Only running it tells them apart.
     */
    public function testANamespacedTagThatMatchesIsNotReported(): void
    {
        $this->createPage('target', 'Nothing here.', 'type:product');
        $this->createPage('lister', "{{ pages_list('type:product') }}");

        self::assertSame([], $this->deadSearches($this->lint()));
    }

    /** It catches more than typos: a slug naming a page that no longer exists. */
    public function testASlugPointingNowhereIsReported(): void
    {
        $this->createPage('lister', "{{ pages_list('slug:".self::PREFIX."deleted') }}");

        self::assertSame(['slug:'.self::PREFIX.'deleted'], $this->deadSearches($this->lint()));
    }

    /** A search that cannot be parsed at all is reported with the parser's own words. */
    public function testAStructuralMistakeIsReportedWithItsReason(): void
    {
        $this->createPage('lister', "{{ pages_list('(travel OR hiking') }}");

        $report = $this->lint();
        self::assertSame(['(travel OR hiking'], $this->deadSearches($report));

        /** @var list<array{reason: string, page: string}> $dead */
        $dead = $report['dead'];
        $mine = array_values(array_filter($dead, static fn (array $f): bool => str_starts_with($f['page'], self::PREFIX)));
        self::assertStringContainsString('left open', $mine[0]['reason']);
    }

    /** A dead search whose term reads like a prefix says so — but only once it is already dead. */
    public function testADeadPrefixedTermCarriesAHint(): void
    {
        $this->createPage('lister', "{{ pages_list('tags:travel') }}");

        /** @var list<array{hint: string, page: string}> $dead */
        $dead = $this->lint()['dead'];
        $mine = array_values(array_filter($dead, static fn (array $f): bool => str_starts_with($f['page'], self::PREFIX)));

        self::assertStringContainsString('read as a tag name', $mine[0]['hint']);
    }

    public function testHumanOutputNamesTheOffendingSearch(): void
    {
        $this->createPage('lister', "{{ pages_list('tags:travel') }}");

        $application = new Application(self::$kernel ?? throw new LogicException());
        $tester = new CommandTester($application->find('pw:pages-list:lint'));
        $status = $tester->execute(['--format' => 'text', '--host' => self::HOST]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('tags:travel', $tester->getDisplay());
    }
}
