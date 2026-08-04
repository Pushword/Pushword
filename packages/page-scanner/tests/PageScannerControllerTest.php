<?php

namespace Pushword\PageScanner\Tests;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Pushword\PageScanner\Service\PageScanCoordinator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

#[Group('integration')]
final class PageScannerControllerTest extends AbstractAdminTestClass
{
    /**
     * Declared before testAdmin on purpose: at this point no test of the class
     * has dispatched a background scan, so nothing can rewrite the seeded
     * results file mid-request — and shouldScan() reading it as fresh keeps
     * this request from starting one.
     *
     * The scan date goes through format_datetime — an int mtime in, a
     * locale-formatted string out — and each error row carries its message
     * and its code badge.
     */
    public function testResultsRenderTheScanDateAndTheErrorRows(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        /** @var PageScanCoordinator $pageScanCoordinator */
        $pageScanCoordinator = self::getContainer()->get(PageScanCoordinator::class);
        new Filesystem()->dumpFile($pageScanCoordinator->getFileCache(null), serialize([
            1 => [[
                'code' => 'link-not-found',
                'message' => 'this link points nowhere',
                'page' => ['id' => 1, 'slug' => 'index', 'h1' => 'Home', 'metaRobots' => '', 'host' => 'localhost.dev'],
            ]],
        ]));

        $crawler = $client->request(Request::METHOD_GET, '/admin/scan');

        self::assertResponseIsSuccessful();
        $alert = $crawler->filter('.alert-info')->text();
        self::assertStringNotContainsString('%date%', $alert);
        self::assertMatchesRegularExpression('#\d{1,2}/\d{1,2}/\d{2}#', $alert);
        $table = $crawler->filter('tbody')->text();
        self::assertStringContainsString('this link points nowhere', $table);
        self::assertStringContainsString('link-not-found', $table);
    }

    public function testAdmin(): void
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $client->request(Request::METHOD_GET, '/admin/scan');
        self::assertResponseIsSuccessful();
    }

    public function testAdminWithHost(): void
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $client->request(Request::METHOD_GET, '/admin/scan?host=localhost.dev');
        self::assertResponseIsSuccessful();
    }

    /**
     * The live output polls itself: each fragment re-carries the trigger that
     * fetches the next one. Losing it stalls the console mid-scan.
     */
    public function testRunningOutputFragmentCarriesThePollTrigger(): void
    {
        $this->loginUser();

        $fragment = $this->renderOutputFragment('running');

        self::assertStringContainsString('hx-get=', $fragment);
        self::assertStringContainsString('hx-trigger="load delay:500ms"', $fragment);
        self::assertStringContainsString('hx-swap="outerHTML"', $fragment);
        self::assertStringContainsString('hx-target="#scanner-output-content"', $fragment);
    }

    /**
     * The other half of the same contract, and the one that bites: the server
     * ends the loop by omitting the trigger. A fragment that keeps it once the
     * scan is over polls the endpoint forever.
     */
    public function testOutputFragmentStopsPollingWhenTheScanIsOver(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $this->markProcessFinished('completed');

        $client->request(Request::METHOD_GET, '/admin/scan-output');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('id="scanner-output-content"', $content);
        self::assertStringNotContainsString('hx-trigger', $content);
        self::assertStringNotContainsString('hx-get', $content);
    }

    /**
     * A fragment that was waiting on someone else's scan hands the browser off
     * with HX-Redirect once that scan ends — htmx 4 still honours the header.
     */
    public function testPendingOutputHandsOffWithHxRedirectWhenTheScanIsOver(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $this->markProcessFinished('completed');

        $client->request(Request::METHOD_GET, '/admin/scan-output?pending=1');

        self::assertResponseIsSuccessful();
        self::assertSame('', (string) $client->getResponse()->getContent());
        self::assertResponseHasHeader('HX-Redirect');
        self::assertStringContainsString('/admin/scan', (string) $client->getResponse()->headers->get('HX-Redirect'));
    }

    private function renderOutputFragment(string $status): string
    {
        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        return $twig->render('@pwPageScanner/output_fragment.html.twig', [
            'status' => $status,
            'output' => '',
            'host' => null,
            'pending' => false,
            'outputProcessType' => PageScanCoordinator::PROCESS_TYPE,
        ]);
    }

    /**
     * No pid file means no running process, so readOutput() falls back to the
     * stored status — the state the controller sees once a scan is over.
     */
    private function markProcessFinished(string $status): void
    {
        /** @var BackgroundProcessManager $processManager */
        $processManager = self::getContainer()->get(BackgroundProcessManager::class);
        /** @var ProcessOutputStorage $outputStorage */
        $outputStorage = self::getContainer()->get(ProcessOutputStorage::class);

        new Filesystem()->remove($processManager->getPidFilePath(PageScanCoordinator::PROCESS_TYPE));
        $outputStorage->setStatus(PageScanCoordinator::PROCESS_TYPE, $status);
    }
}
