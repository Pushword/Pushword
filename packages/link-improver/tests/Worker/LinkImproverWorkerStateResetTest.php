<?php

namespace Pushword\LinkImprover\Tests\Worker;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\LinkImprover\AddedLinksRegistry;
use Pushword\LinkImprover\InternalLinkSources;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The keyword map is built once per host+locale and kept in memory: under a
 * long-running worker the kernel is reused across requests, so a page renamed
 * or published in one request would keep linking under its old name — for the
 * life of the worker — if the map were not flushed between requests.
 *
 * Each test replays two requests around the exact reset a worker performs
 * between them, with a write in between.
 */
#[Group('integration')]
#[Group('worker')]
final class LinkImproverWorkerStateResetTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    private const string PROBE_SLUG = 'linkimp-worker-probe';

    protected function tearDown(): void
    {
        $entityManager = $this->em();
        foreach ($entityManager->getRepository(Page::class)->findBy(['slug' => self::PROBE_SLUG]) as $probe) {
            $entityManager->remove($probe);
        }

        $entityManager->flush();

        parent::tearDown();
    }

    public function testTheKeywordMapReflectsWritesAcrossSimulatedRequests(): void
    {
        self::bootKernel();
        $sources = self::getContainer()->get(InternalLinkSources::class);

        // --- Request A: build the map for this host+locale, then add a page. ---
        $before = $sources->getRows(self::HOST, 'en');
        self::assertNotContains([$this->probeUrl(), 'Worker Probe Fruit'], $before);

        $this->createProbePage();

        // The map is warm but stale: it predates the write. This is what the
        // reset must fix — and it proves the guard below is not vacuous.
        self::assertSame($before, $sources->getRows(self::HOST, 'en'));

        // --- Between requests: exactly what a FrankenPHP/Runtime worker runs. ---
        $this->simulateWorkerRequestBoundary();

        // --- Request B: the new page must be offered as a link target. ---
        self::assertContains([$this->probeUrl(), 'Worker Probe Fruit'], $sources->getRows(self::HOST, 'en'));
    }

    public function testTheReportedLinksDoNotLeakAcrossSimulatedRequests(): void
    {
        self::bootKernel();
        $registry = self::getContainer()->get(AddedLinksRegistry::class);

        $page = new Page();
        $page->host = self::HOST;
        $page->slug = self::PROBE_SLUG;

        $registry->record($page, 'Worker Probe Fruit', $this->probeUrl());
        self::assertCount(1, $registry->forPage($page));

        $this->simulateWorkerRequestBoundary();

        // A worker serves the next request with a clean report: what pw:link-improver
        // shows for a page must be what this render inserted, not the previous one.
        self::assertSame([], $registry->forPage($page));
    }

    private function probeUrl(): string
    {
        return '/'.self::PROBE_SLUG;
    }

    private function createProbePage(): void
    {
        $probe = new Page();
        $probe->host = self::HOST;
        $probe->slug = self::PROBE_SLUG;
        $probe->name = 'Worker Probe Fruit';
        $probe->h1 = 'Worker Probe';
        $probe->locale = 'en';
        $probe->publishedAt = new DateTime('-1 hour');
        $probe->mainContent = 'The probe page.';

        $this->em()->persist($probe);
        $this->em()->flush();
    }

    private function simulateWorkerRequestBoundary(): void
    {
        self::getContainer()->get('services_resetter')->reset();
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
