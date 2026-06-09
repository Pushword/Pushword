<?php

namespace Pushword\Core\Tests\Perf;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Controller\PageController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opt-in benchmark quantifying what worker mode (FrankenPHP `worker` / Symfony
 * Runtime) actually buys over classic per-request PHP-FPM: amortizing the kernel
 * boot across requests. Both modes do the same request work (render the homepage);
 * the only difference is FPM boots a fresh kernel per request while the worker
 * boots once and resets services between requests.
 *
 * It also tracks memory growth across the worker loop — the real risk of a
 * long-running process — so a leak in the repositories' in-memory caches would
 * show up as a rising delta.
 *
 * Excluded from the default suite via the `benchmark` group. Run with:
 *   vendor/bin/phpunit --group benchmark \
 *     packages/core/tests/Perf/WorkerVsFpmBenchmarkTest.php
 */
#[Group('benchmark')]
final class WorkerVsFpmBenchmarkTest extends KernelTestCase
{
    private const int REQUESTS = 30;

    public function testWorkerVsFpmThroughput(): void
    {
        // --- FPM: a fresh kernel boot for every request. ---
        $fpmStart = microtime(true);
        for ($i = 0; $i < self::REQUESTS; ++$i) {
            self::ensureKernelShutdown();
            self::bootKernel();
            $this->handleHomepage();
        }

        $fpmMs = (microtime(true) - $fpmStart) * 1000.0;
        $fpmPeak = memory_get_peak_usage(true);

        // --- Worker: one boot, reused across requests, reset in between. ---
        self::ensureKernelShutdown();
        self::bootKernel();

        // Handle one request first to warm caches, then snapshot memory so the
        // delta below reflects per-request growth, not one-time warmup.
        $this->handleHomepage();
        $this->resetServices();
        $memAfterWarm = memory_get_usage(true);

        $workerStart = microtime(true);
        for ($i = 0; $i < self::REQUESTS; ++$i) {
            $this->handleHomepage();
            $this->resetServices();
        }

        $workerMs = (microtime(true) - $workerStart) * 1000.0;
        $workerMemDelta = memory_get_usage(true) - $memAfterWarm;

        $fpmPerReq = $fpmMs / self::REQUESTS;
        $workerPerReq = $workerMs / self::REQUESTS;

        fwrite(\STDERR, \sprintf(
            "\n[BENCHMARK] worker vs FPM over %d homepage renders\n"
            ."[BENCHMARK]   FPM    (boot per request): %.1f ms total, %.2f ms/req, %.0f req/s\n"
            ."[BENCHMARK]   worker (boot once)       : %.1f ms total, %.2f ms/req, %.0f req/s\n"
            ."[BENCHMARK]   speedup: %.2fx | worker memory growth: %.2f MB over %d reqs | FPM peak: %.1f MB\n",
            self::REQUESTS,
            $fpmMs,
            $fpmPerReq,
            1000.0 / $fpmPerReq,
            $workerMs,
            $workerPerReq,
            1000.0 / $workerPerReq,
            $workerPerReq > 0.0 ? $fpmPerReq / $workerPerReq : 0.0,
            $workerMemDelta / 1024 / 1024,
            self::REQUESTS,
            $fpmPeak / 1024 / 1024,
        ));

        // Loose sanity caps — the point is the printed numbers, not gating.
        self::assertGreaterThan(0.0, $fpmPerReq);
        self::assertGreaterThan(0.0, $workerPerReq);
        // A long-running worker must not leak unbounded memory per request.
        self::assertLessThan(
            20 * 1024 * 1024,
            $workerMemDelta,
            'worker memory grew >20MB across the run — likely a cache leaking across requests',
        );
    }

    private function handleHomepage(): void
    {
        $controller = self::getContainer()->get(PageController::class);
        $response = $controller->show(Request::create('/'), '');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    private function resetServices(): void
    {
        self::getContainer()->get('services_resetter')->reset();
    }
}
