<?php

namespace Pushword\Core\Tests\Worker;

use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * Worker-mode production-readiness guard. Drives a single booted kernel through a
 * realistic mixed request stream — different hosts and locales — exactly as a
 * FrankenPHP `worker` / Symfony Runtime process would: each request is handled,
 * terminated, then services are reset (`kernel.reset`) before the next one.
 *
 * Pushword is multi-site (host-driven) and multi-locale, so the prod risk is one
 * request's host/locale/site bleeding into the next when the kernel is reused.
 * These tests assert every response stays correct for its own host+locale and
 * that memory does not grow across a long mixed run (no cache accumulating per
 * host across requests).
 *
 * Companion to WorkerModeStateResetTest, which proves the repository caches are
 * flushed by the same reset; this one covers the full HTTP request pipeline.
 */
#[Group('integration')]
#[Group('worker')]
final class WorkerModeCrossRequestTest extends KernelTestCase
{
    public function testCrossHostAndCrossLocaleRequestsStayCorrect(): void
    {
        self::bootKernel();

        // 1) Default host, English.
        $en = $this->workerHandle('https://localhost.dev/');
        self::assertSame(Response::HTTP_OK, $en->getStatusCode());
        $enBody = (string) $en->getContent();
        self::assertSame('en', $this->lang($enBody));
        self::assertStringContainsString('href="https://localhost.dev/"', $this->canonical($enBody));
        self::assertStringContainsString('Welcome to Pushword', $enBody);

        // 2) A different host entirely. Its content and canonical host must be its
        //    own — no bleed from the previous localhost.dev request.
        $other = $this->workerHandle('https://admin-block-editor.test/kitchen-sink');
        self::assertSame(Response::HTTP_OK, $other->getStatusCode());
        $otherBody = (string) $other->getContent();
        self::assertStringContainsString('href="https://admin-block-editor.test/kitchen-sink"', $this->canonical($otherBody));
        self::assertStringContainsString('Kitchen Sink Block', $otherBody);
        self::assertStringNotContainsString('localhost.dev', $this->canonical($otherBody), "previous host must not leak into this request's canonical");

        // 3) Back to the default host, but a French page. Locale must switch.
        $fr = $this->workerHandle('https://localhost.dev/fr/homepage');
        self::assertSame(Response::HTTP_OK, $fr->getStatusCode());
        $frBody = (string) $fr->getContent();
        self::assertSame('fr', $this->lang($frBody));
        self::assertStringContainsString('Bienvenue sur Pushword', $frBody);

        // 4) English homepage again: the locale set to 'fr' in the previous request
        //    must not stick — the lang attribute must be back to 'en'.
        $enAgain = $this->workerHandle('https://localhost.dev/');
        self::assertSame(Response::HTTP_OK, $enAgain->getStatusCode());
        $enAgainBody = (string) $enAgain->getContent();
        self::assertSame('en', $this->lang($enAgainBody), 'locale from a previous request must not leak across the worker boundary');
        self::assertStringContainsString('Welcome to Pushword', $enAgainBody);
    }

    /**
     * A body edit landing between two requests served by the same worker must be
     * visible to the second one.
     *
     * The render path memoizes by page id at three levels — ContentExtension
     * (the split body), ContentPipelineFactory (the filtered properties) and
     * ManagerPool (the legacy manager) — and each memo short-circuits before the
     * content-hash markdown pool, so that pool's self-invalidation never gets a
     * chance to run. Under PHP-FPM the process dies between requests and none of
     * it can go stale; under a worker all three survive, and a page rendered once
     * would keep serving that exact body for its id until the worker restarts —
     * no TTL, no recovery — however the edit arrived (api PUT, admin save,
     * pw:flat:sync).
     */
    public function testBodyEditIsVisibleToTheNextRequestOnTheSameWorker(): void
    {
        self::bootKernel();

        $slug = 'worker-stale-body-probe';
        $url = 'https://localhost.dev/'.$slug;

        $this->removeProbePage($slug);
        $this->writeProbePage($slug, 'AlphaBodyMarker');

        try {
            // --- Request A: warms every per-page-id memo for this id. ---
            $first = $this->workerHandle($url);
            self::assertSame(Response::HTTP_OK, $first->getStatusCode());
            self::assertStringContainsString('AlphaBodyMarker', (string) $first->getContent());

            // --- Between requests: the body is edited, exactly as an api PUT or an
            //     admin save would. The worker is not restarted. ---
            $this->writeProbePage($slug, 'BravoBodyMarker');

            // --- Request B: the same worker serves the page again. ---
            $second = (string) $this->workerHandle($url)->getContent();

            self::assertStringContainsString(
                'BravoBodyMarker',
                $second,
                'worker mode: the edited body must be served by the next request, not the memoized one',
            );
            self::assertStringNotContainsString(
                'AlphaBodyMarker',
                $second,
                'worker mode: a page rendered once must not keep serving its old body after the content changes',
            );
        } finally {
            $this->removeProbePage($slug);
        }
    }

    public function testMemoryStaysStableAcrossManyMixedRequests(): void
    {
        // Boot debug OFF to measure the production runtime. In debug mode the
        // profiler-style collectors and the PHPUnit deprecation accumulator grow
        // ~50KB/request — pure test/debug machinery absent from APP_ENV=prod, which
        // would mask a real leak behind harness noise. With debug off the heap is
        // flat, so a tight bound here is both meaningful and stable.
        self::bootKernel(['debug' => false]);

        $urls = [
            'https://localhost.dev/',
            'https://localhost.dev/fr/homepage',
            'https://localhost.dev/fr-ca/homepage',
            'https://admin-block-editor.test/kitchen-sink',
        ];

        // Warm several full cycles so lazily-initialized services settle, then
        // snapshot memory — the delta reflects steady-state per-request growth.
        for ($i = 0; $i < 3; ++$i) {
            foreach ($urls as $url) {
                self::assertSame(Response::HTTP_OK, $this->workerHandle($url)->getStatusCode());
            }
        }

        gc_collect_cycles();
        $memAfterWarm = memory_get_usage();

        $cycles = 25;
        for ($i = 0; $i < $cycles; ++$i) {
            foreach ($urls as $url) {
                $this->workerHandle($url);
            }
        }

        gc_collect_cycles();
        $memDelta = memory_get_usage() - $memAfterWarm;

        fwrite(\STDERR, \sprintf(
            "\n[WORKER] prod-mode heap growth over %d requests across %d hosts/locales: %.2f MB\n",
            $cycles * \count($urls),
            \count($urls),
            $memDelta / 1024 / 1024,
        ));

        self::assertLessThan(
            4 * 1024 * 1024,
            $memDelta,
            'worker heap grew across a mixed request stream — a per-host/per-request cache is likely accumulating across requests',
        );
    }

    /**
     * Handle one request through the full kernel, then run the exact worker
     * request boundary: terminate the request and reset all kernel.reset services.
     */
    private function workerHandle(string $url): Response
    {
        $kernel = self::$kernel;
        \assert($kernel instanceof KernelInterface);

        $request = Request::create($url);
        $response = $kernel->handle($request);
        if ($kernel instanceof TerminableInterface) {
            $kernel->terminate($request, $response);
        }

        $kernel->getContainer()->get('services_resetter')->reset();

        return $response;
    }

    /**
     * Create the probe page, or rewrite its body if it already exists. Cache
     * suppression keeps the save from triggering the OG-image/static side effects,
     * which are irrelevant here and slow.
     */
    private function writeProbePage(string $slug, string $marker): void
    {
        self::getContainer()->get(PageCacheSuppressor::class)->suppress(function () use ($slug, $marker): void {
            $em = $this->em();
            $page = $em->getRepository(Page::class)->findOneBy(['slug' => $slug, 'host' => 'localhost.dev']);

            if (! $page instanceof Page) {
                $page = new Page();
                $page->setSlug($slug);
                $page->host = 'localhost.dev';
                $page->locale = 'en';
                $page->createdAt = new DateTime();
                $page->publishedAt = new DateTime();
                $em->persist($page);
            }

            $page->setH1('Worker stale body probe');
            $page->setMainContent($marker);
            $page->updatedAt = new DateTime();

            $em->flush();
        });
    }

    private function removeProbePage(string $slug): void
    {
        $em = $this->em();
        $existing = $em->getRepository(Page::class)->findOneBy(['slug' => $slug, 'host' => 'localhost.dev']);
        if ($existing instanceof Page) {
            $em->remove($existing);
            $em->flush();
        }
    }

    private function em(): EntityManagerInterface
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        return $em;
    }

    private function lang(string $body): string
    {
        return 1 === preg_match('/<html[^>]*\blang="([^"]+)"/', $body, $m) ? $m[1] : '';
    }

    private function canonical(string $body): string
    {
        return 1 === preg_match('/<link[^>]*rel="canonical"[^>]*>/', $body, $m) ? $m[0] : '';
    }
}
