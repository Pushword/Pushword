<?php

namespace Pushword\PageScanner\Controller\Api;

use DateTimeInterface;
use Pushword\Api\Controller\AbstractApiController;
use Pushword\Core\Site\SiteRegistry;
use Pushword\PageScanner\Service\PageScanCoordinator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * JSON mirror of the admin page-scan screen.
 *
 * A scan can run for minutes, so the API never blocks on it: `POST` dispatches
 * a background scan (or returns the still-fresh cached result), and `GET` polls
 * its status, live console output, and — once finished — the dead-link/404/301
 * findings. The heavy lifting (per-host locking, interval gating, background
 * dispatch, ignore-filtering) is shared with the admin through
 * {@see PageScanCoordinator}, so both behave identically.
 */
#[IsGranted('ROLE_EDITOR')]
final class PageScanApiController extends AbstractApiController
{
    public function __construct(
        private readonly PageScanCoordinator $coordinator,
        private readonly SiteRegistry $siteRegistry,
    ) {
    }

    #[Route('/api/page-scan', name: 'pushword_api_page_scan_status', methods: ['GET'])]
    public function status(Request $request): JsonResponse
    {
        $host = $this->resolveHost($request);
        if ($host instanceof JsonResponse) {
            return $host;
        }

        return $this->respond($this->statusPayload($host));
    }

    #[Route('/api/page-scan', name: 'pushword_api_page_scan_trigger', methods: ['POST'])]
    public function trigger(Request $request): JsonResponse
    {
        $host = $this->resolveHost($request);
        if ($host instanceof JsonResponse) {
            return $host;
        }

        $force = $request->query->getBoolean('force') || true === ($this->decodeJson($request)['force'] ?? null);

        // Already scanning — never start a second one for the same scope.
        $blocking = $this->coordinator->findBlockingProcess($host);
        if (null !== $blocking) {
            return $this->accepted($host, 'running', started: false);
        }

        $processType = $this->coordinator->getProcessType($host);
        if ($this->coordinator->getProcessInfo($processType)['isRunning']) {
            return $this->accepted($host, 'running', started: false);
        }

        if ($this->coordinator->shouldScan($host, $force)) {
            $this->coordinator->startScan($host);

            return $this->accepted($host, $this->coordinator->readOutput($processType)['status'], started: true);
        }

        // Cached result still within the min-interval window: return it as-is.
        return $this->respond(['started' => false] + $this->statusPayload($host));
    }

    /**
     * The scan is under way, but say which way: under `background_task_handler:
     * messenger` it may only be queued, and a client told `running` would poll for
     * a console output that stays empty until a consumer gets to it.
     */
    private function accepted(?string $host, string $status, bool $started): JsonResponse
    {
        return $this->respond([
            'host' => $host,
            'status' => $status,
            'started' => $started,
            'statusUrl' => $this->generateUrl(
                'pushword_api_page_scan_status',
                null !== $host ? ['host' => $host] : [],
            ),
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Status + findings snapshot for a scope, shared by the GET and POST responses.
     *
     * @return array<string, mixed>
     */
    private function statusPayload(?string $host): array
    {
        $state = $this->coordinator->readOutput($this->coordinator->getProcessType($host));
        $results = $this->buildResults($host);

        // "completed" with nothing on disk means the host was never scanned.
        $status = $state['status'];
        if ('completed' === $status && null === $results['lastScannedAt'] && '' === $state['output']) {
            $status = 'idle';
        }

        $summary = [
            'host' => $host,
            'status' => $status,
            'running' => $state['isRunning'],
            'lastScannedAt' => $results['lastScannedAt'],
            'errorCount' => $results['errorCount'],
            'errors' => $results['errors'],
        ];

        if ($state['isRunning'] || 'error' === $status) {
            $summary['output'] = $state['output'];
        }

        return $summary;
    }

    /**
     * Flatten the cached per-page errors into a plain, agent-friendly list.
     *
     * @return array{lastScannedAt: string|null, errorCount: int, errors: list<array<string, string>>}
     */
    private function buildResults(?string $host): array
    {
        $results = $this->coordinator->readResults($host);

        $errors = [];
        foreach ($results['errorsByPages'] as $pageErrors) {
            foreach ($pageErrors as $error) {
                $errors[] = [
                    'host' => $error['page']['host'],
                    'slug' => $error['page']['slug'],
                    'code' => $error['code'],
                    'message' => trim(strip_tags($error['message'])),
                ];
            }
        }

        return [
            'lastScannedAt' => $results['lastEdit'] > 0 ? date(DateTimeInterface::ATOM, $results['lastEdit']) : null,
            'errorCount' => \count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Resolve the optional `host` query param, rejecting unknown hosts. Returns
     * null for an all-hosts scan, or a 400 JSON response when invalid.
     */
    private function resolveHost(Request $request): string|JsonResponse|null
    {
        $host = $request->query->getString('host', '') ?: null;
        if (null !== $host && ! $this->siteRegistry->isKnownHost($host)) {
            return $this->badRequest('Unknown host');
        }

        return $host;
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(): array
    {
        $params = [[
            'name' => 'host',
            'in' => 'query',
            'required' => false,
            'schema' => ['type' => 'string'],
            'description' => 'A configured host; omit to scan/poll every site at once.',
        ]];

        return [
            'paths' => [
                '/api/page-scan' => [
                    'get' => [
                        'summary' => 'Page-scan status, live output and findings',
                        'description' => 'Returns the current scan status (idle|queued|running|completed|error), the live console output while running, and the cached dead-link/404/301 findings of the last completed scan. `queued` means the scan is waiting on a messenger consumer and has not started — keep polling.',
                        'parameters' => $params,
                        'responses' => [
                            '200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/PageScan']]]],
                            '400' => ['description' => 'Unknown host'],
                            '401' => ['description' => 'Missing or invalid Bearer token'],
                        ],
                    ],
                    'post' => [
                        'summary' => 'Start a page scan (background)',
                        'description' => 'Dispatches a background scan and returns 202 with a statusUrl to poll, plus the `status` it starts from — `queued` when it is waiting on a messenger consumer rather than running. If a scan is already running, or a cached result is still within the configured min-interval, no new scan starts. Pass `?force=1` to bypass the interval.',
                        'parameters' => [...$params, [
                            'name' => 'force',
                            'in' => 'query',
                            'required' => false,
                            'schema' => ['type' => 'boolean'],
                            'description' => 'Bypass the min-interval and force a fresh scan.',
                        ]],
                        'responses' => [
                            '200' => ['description' => 'Fresh cached result returned without starting a new scan'],
                            '202' => ['description' => 'Scan running (started or already in progress)'],
                            '400' => ['description' => 'Unknown host'],
                            '401' => ['description' => 'Missing or invalid Bearer token'],
                        ],
                    ],
                ],
            ],
            'components' => ['schemas' => ['PageScan' => [
                'type' => 'object',
                'properties' => [
                    'host' => ['type' => ['string', 'null']],
                    'status' => ['type' => 'string', 'enum' => ['idle', 'queued', 'running', 'completed', 'error']],
                    'running' => ['type' => 'boolean'],
                    'lastScannedAt' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                    'errorCount' => ['type' => 'integer'],
                    'errors' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'host' => ['type' => 'string'],
                            'slug' => ['type' => 'string'],
                            'code' => ['type' => 'string', 'description' => 'Stable identity of the finding, e.g. `link-not-found`; what `errors_to_ignore` matches.'],
                            'message' => ['type' => 'string'],
                        ],
                    ]],
                    'output' => ['type' => 'string', 'description' => 'Live console output, present while running or on error.'],
                ],
            ]]],
        ];
    }
}
