<?php

namespace Pushword\PageScanner\Tests;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\PageScanner\Service\LinkGraphStorage;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

#[Group('integration')]
final class LinkGraphCommandTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    private KernelInterface $bootedKernel;

    #[Override]
    protected function setUp(): void
    {
        $this->bootedKernel = self::bootKernel();
        $this->removeSnapshots();
    }

    protected function tearDown(): void
    {
        $this->removeSnapshots();
        parent::tearDown();
    }

    private function varDir(): string
    {
        /** @var string */
        return self::getContainer()->getParameter('pw.var_dir');
    }

    private function removeSnapshots(): void
    {
        new Filesystem()->remove(glob($this->varDir().'/page-scan-graph--*') ?: []);
    }

    /**
     * Seed a snapshot so the report paths are tested without paying for a render.
     * Stamped with the corpus as it is right now, so the command reads it rather
     * than deciding it is stale and rescanning over it.
     *
     * @param list<string>                $nodes
     * @param array<string, list<string>> $edges
     */
    private function seed(array $nodes, array $edges): void
    {
        self::getContainer()->get(LinkGraphStorage::class)->write(
            self::HOST,
            $nodes,
            $edges,
            self::getContainer()->get(PageRepository::class)->getPublishedCorpusState(self::HOST),
        );
    }

    /**
     * @param array<string, bool|string> $input
     */
    private function graph(array $input = []): CommandTester
    {
        $commandTester = new CommandTester(new Application($this->bootedKernel)->find('pw:link:graph'));
        // Named key: a bare value lands under a numeric key and never binds to the argument.
        $commandTester->execute(['host' => self::HOST, '--format' => 'text', ...$input]);

        return $commandTester;
    }

    public function testOrphansGatePassesWhenEveryPageIsLinkedTwice(): void
    {
        $this->seed(
            [self::HOST.'/homepage', self::HOST.'/one', self::HOST.'/two'],
            [
                self::HOST.'/homepage' => [self::HOST.'/one', self::HOST.'/two'],
                self::HOST.'/one' => [self::HOST.'/two'],
                self::HOST.'/two' => [self::HOST.'/one'],
            ],
        );

        $commandTester = $this->graph(['--orphans' => true]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('No orphan', $commandTester->getDisplay());
    }

    public function testOrphansGateFailsWhenAnOrphanRemains(): void
    {
        $this->seed([self::HOST.'/homepage', self::HOST.'/lonely'], []);

        $commandTester = $this->graph(['--orphans' => true]);

        // The whole point of the feature: a CI pipeline must go red here.
        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        self::assertStringContainsString('lonely', $commandTester->getDisplay());
    }

    public function testTheHomepageAloneNeverFailsTheGate(): void
    {
        $this->seed([self::HOST.'/homepage'], []);

        self::assertSame(Command::SUCCESS, $this->graph(['--orphans' => true])->getStatusCode());
    }

    public function testReportListsEveryPageWithItsCounts(): void
    {
        $this->seed(
            [self::HOST.'/homepage', self::HOST.'/one'],
            [self::HOST.'/homepage' => [self::HOST.'/one']],
        );

        $output = $this->graph()->getDisplay();

        self::assertStringContainsString('2 pages, 1 internal links', $output);
        self::assertStringContainsString(self::HOST.'/one  in:1 out:0 depth:1', $output);
    }

    public function testReportAlwaysDatesTheSnapshot(): void
    {
        $this->seed([self::HOST.'/homepage'], []);

        // A page's inbound count changes when OTHER pages are edited, so the age
        // of the graph must never be implicit.
        self::assertStringContainsString('graph generated', $this->graph()->getDisplay());
    }

    public function testPageOptionShowsInboundSources(): void
    {
        $this->seed(
            [self::HOST.'/homepage', self::HOST.'/target', self::HOST.'/other'],
            [self::HOST.'/homepage' => [self::HOST.'/target'], self::HOST.'/other' => [self::HOST.'/target']],
        );

        $output = $this->graph(['--page' => 'target'])->getDisplay();

        self::assertStringContainsString('in:2', $output);
        self::assertStringContainsString('← '.self::HOST.'/homepage', $output);
        self::assertStringContainsString('← '.self::HOST.'/other', $output);
        self::assertStringNotContainsString('/other  in:', $output, 'only the requested page is reported');
    }

    public function testUnknownPageFails(): void
    {
        $this->seed([self::HOST.'/homepage'], []);

        $commandTester = $this->graph(['--page' => 'does-not-exist']);

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        self::assertStringContainsString('not in the graph', $commandTester->getDisplay());
    }

    public function testAHostWithoutHomepageIsNamedRatherThanReadAsUnreachable(): void
    {
        $this->seed([self::HOST.'/one'], []);

        self::assertStringContainsString('No homepage scanned on '.self::HOST, $this->graph()->getDisplay());
    }

    public function testTheHostDefaultsToTheFirstConfiguredSite(): void
    {
        // localhost.dev is the skeleton's first app: omitting the argument must
        // report it, not every site at once.
        $this->seed([self::HOST.'/homepage', self::HOST.'/one'], [self::HOST.'/homepage' => [self::HOST.'/one']]);

        $commandTester = new CommandTester(new Application($this->bootedKernel)->find('pw:link:graph'));
        $commandTester->execute(['--format' => 'agent']);

        $decoded = json_decode(trim($commandTester->getDisplay()), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame(self::HOST, $decoded['host']);
        self::assertSame(2, $decoded['pageCount']);
    }

    public function testAgentOutputIsASingleJsonLine(): void
    {
        $this->seed([self::HOST.'/homepage', self::HOST.'/one'], [self::HOST.'/homepage' => [self::HOST.'/one']]);

        $commandTester = $this->graph(['--format' => 'agent']);
        $output = trim($commandTester->getDisplay());

        self::assertStringNotContainsString('graph generated', $output, 'no human noise leaks into agent output');

        $decoded = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame('pw:link:graph', $decoded['tool']);
        self::assertSame('done', $decoded['result']);
        self::assertSame(2, $decoded['pageCount']);
        self::assertArrayHasKey('generatedAt', $decoded);
    }

    public function testAgentOrphansOutputReportsTheVerdict(): void
    {
        $this->seed([self::HOST.'/homepage', self::HOST.'/lonely'], []);

        $commandTester = $this->graph(['--orphans' => true, '--format' => 'agent']);

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $decoded = json_decode(trim($commandTester->getDisplay()), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame('failed', $decoded['result']);
        self::assertSame(1, $decoded['orphanCount']);
    }

    public function testMissingSnapshotRunsTheScanItself(): void
    {
        // No seed: the command must render the corpus rather than report nothing,
        // synchronously — a CI gate cannot poll a background scan.
        $commandTester = $this->graph(['--skip-external' => true]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('No link graph found, running pw:page-scan first', $commandTester->getDisplay());
        self::assertNotNull(self::getContainer()->get(LinkGraphStorage::class)->read(self::HOST));
    }

    public function testAGraphThatNoLongerMatchesTheContentIsRebuiltRatherThanReported(): void
    {
        // A graph nobody invalidates is a graph that lies: a page's inbound count
        // moves when OTHER pages are edited, so no amount of reading this page tells
        // you the number is stale. Seed a snapshot taken from a corpus that never
        // existed and the command must go and render, not report it.
        self::getContainer()->get(LinkGraphStorage::class)->write(
            self::HOST,
            [self::HOST.'/homepage', self::HOST.'/ghost-of-a-page'],
            [],
            ['pages' => 999, 'lastEditAt' => 1750000000],
        );

        $commandTester = $this->graph(['--skip-external' => true]);
        $output = $commandTester->getDisplay();

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('no longer matches the content, running pw:page-scan first', $output);
        self::assertStringNotContainsString('ghost-of-a-page', $output, 'the stale graph must not reach the report');
    }

    public function testAFreshGraphIsReportedWithoutRescanning(): void
    {
        // The other half of the rule: staleness is measured against the corpus, not
        // the clock, so an untouched site never pays for a render it does not need.
        $this->seed([self::HOST.'/homepage', self::HOST.'/one'], [self::HOST.'/homepage' => [self::HOST.'/one']]);

        $output = $this->graph()->getDisplay();

        self::assertStringNotContainsString('running pw:page-scan first', $output);
        self::assertStringContainsString('2 pages, 1 internal links', $output);
    }

    public function testEditingAPageStalesTheGraph(): void
    {
        $this->seed([self::HOST.'/homepage'], []);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $page = self::getContainer()->get(PageRepository::class)->findOneBy(['host' => self::HOST, 'slug' => 'kitchen-sink']);
        self::assertInstanceOf(Page::class, $page);

        // preUpdate stamps updatedAt: the very thing the corpus state watches.
        $page->setH1($page->getH1().' (edited)');
        $entityManager->flush();

        $snapshot = self::getContainer()->get(LinkGraphStorage::class)->read(self::HOST);

        self::assertNotNull($snapshot);
        self::assertTrue($snapshot['stale'], 'a content edit must invalidate the graph');
    }

    public function testAnAllHostsScanLeavesEveryHostReadableOnItsOwn(): void
    {
        // The point of writeAll(): scanning everything then reporting one site
        // must not re-render. A single combined snapshot would force a rescan.
        new CommandTester(new Application($this->bootedKernel)->find('pw:page-scan'))
            ->execute(['--format' => 'text', '--skip-external' => true]);

        $storage = self::getContainer()->get(LinkGraphStorage::class);
        $scanned = $storage->read(self::HOST);
        $other = $storage->read('admin-block-editor.test');

        self::assertNotNull($scanned);
        self::assertNotNull($other, 'an all-hosts scan must leave a snapshot for every host it rendered');

        foreach ($scanned['nodes'] as $node) {
            self::assertStringStartsWith(self::HOST.'/', $node, 'a snapshot holds only its own host');
        }

        // And reporting one of them reads that snapshot rather than rescanning.
        self::assertStringNotContainsString('running pw:page-scan first', $this->graph()->getDisplay());
    }

    public function testARedirectionIsNotAGraphNode(): void
    {
        // Regression: getPublishedPages() includes redirections, which render no
        // HTML — they used to enter the graph as 0-outbound nodes, i.e. orphans.
        new CommandTester(new Application($this->bootedKernel)->find('pw:page-scan'))
            ->execute(['host' => self::HOST, '--format' => 'text', '--skip-external' => true]);

        $snapshot = self::getContainer()->get(LinkGraphStorage::class)->read(self::HOST);

        self::assertNotNull($snapshot);
        self::assertNotContains(self::HOST.'/pushword', $snapshot['nodes'], 'a 301 is not a page');
        self::assertContains(self::HOST.'/homepage', $snapshot['nodes']);
    }

    public function testANoindexPageIsNeitherANodeNorASource(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $noindex = new Page();
        $noindex->setH1('Search results');
        $noindex->setSlug('noindex-lists-everything');
        $noindex->locale = 'en';
        $noindex->host = self::HOST;
        $noindex->createdAt = new DateTime();
        $noindex->updatedAt = new DateTime();
        $noindex->setMetaRobots('noindex');
        $noindex->setMainContent('A list of [everything](/kitchen-sink).');

        $entityManager->persist($noindex);
        $entityManager->flush();

        try {
            new CommandTester(new Application($this->bootedKernel)->find('pw:page-scan'))
                ->execute(['host' => self::HOST, '--format' => 'text', '--skip-external' => true]);

            $snapshot = self::getContainer()->get(LinkGraphStorage::class)->read(self::HOST);
            self::assertNotNull($snapshot);

            // As a target: it is an orphan by design, and would fail the CI gate forever.
            self::assertNotContains(self::HOST.'/noindex-lists-everything', $snapshot['nodes']);

            // As a source: this is the one that skews the report. A noindex page
            // listing the whole corpus adds +1 to every inbound count, and hides the
            // pages with no editorial link at all at in:1 instead of in:0.
            self::assertArrayNotHasKey(self::HOST.'/noindex-lists-everything', $snapshot['edges']);
        } finally {
            $entityManager->remove($noindex);
            $entityManager->flush();
        }
    }
}
