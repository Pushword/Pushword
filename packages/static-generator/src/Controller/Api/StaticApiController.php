<?php

namespace Pushword\StaticGenerator\Controller\Api;

use DateTimeInterface;
use Pushword\Api\Controller\AbstractApiController;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\StaticAppGenerator;
use Pushword\StaticGenerator\StaticGenerationCoordinator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

/**
 * JSON mirror of the admin static-generation screen, plus the per-page entry the
 * admin has no use for.
 *
 * The two scopes have opposite cost, so they get opposite shapes. Regenerating a
 * whole site takes minutes: `POST /api/static/{host}` dispatches it in the
 * background and returns a `statusUrl` to poll, exactly like the admin. A single
 * page is one render: `POST /api/static/{host}/{slug}` does it in-process and
 * answers with the result, so a remote agent that just edited a page learns the
 * static output is live from the response itself — no polling loop to write.
 *
 * The background half shares {@see StaticGenerationCoordinator} with the admin,
 * so a generation started here behaves identically to one started there.
 */
#[IsGranted('ROLE_EDITOR')]
final class StaticApiController extends AbstractApiController
{
    public function __construct(
        private readonly StaticGenerationCoordinator $coordinator,
        private readonly StaticAppGenerator $staticAppGenerator,
        private readonly SiteRegistry $siteRegistry,
        private readonly PageRepository $pageRepository,
    ) {
    }

    /**
     * Regenerate one page, synchronously.
     *
     * Only that page's file is written — sitemap, feeds, listing pages and the
     * redirection map belong to the whole-site pass. After creating or deleting a
     * page, follow up with a host generation.
     */
    #[Route(
        '/api/static/{host}/{slug}',
        name: 'pushword_api_static_page',
        requirements: ['host' => '[^/]+', 'slug' => '.+'],
        methods: ['POST'],
    )]
    public function page(string $host, string $slug): JsonResponse
    {
        if (! $this->siteRegistry->isKnownHost($host)) {
            return $this->badRequest('Unknown host');
        }

        $page = $this->pageRepository->findOneBy(['host' => $host, 'slug' => Page::normalizeSlug($slug)]);
        if (null === $page) {
            return $this->notFound('Page not found');
        }

        $skipReason = $this->skipReason($page);
        if (null !== $skipReason) {
            return $this->respond([
                'error' => $skipReason,
                'host' => $host,
                'slug' => $page->slug,
            ], Response::HTTP_CONFLICT);
        }

        // A whole-site pass rebuilds into a temp dir and swaps it in; a page
        // written meanwhile would vanish with the directory it replaced.
        if ($this->isGenerationRunning($host)) {
            return $this->respond([
                'error' => 'generation_running',
                'host' => $host,
                'slug' => $page->slug,
                'statusUrl' => $this->generateUrl('pushword_api_static_status', ['host' => $host]),
            ], Response::HTTP_CONFLICT);
        }

        $startedAt = hrtime(true);

        try {
            $this->staticAppGenerator->generatePage($host, $page->slug);
            $errors = array_values($this->staticAppGenerator->getErrors());
        } catch (Throwable $throwable) {
            $errors = [$throwable->getMessage()];
        }

        return $this->respond([
            'host' => $host,
            'slug' => $page->slug,
            'generated' => [] === $errors,
            'durationMs' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            'errors' => $errors,
        ], [] === $errors ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route(
        '/api/static/{host}',
        name: 'pushword_api_static_trigger',
        requirements: ['host' => '[^/]+'],
        defaults: ['host' => null],
        methods: ['POST'],
    )]
    public function trigger(Request $request, ?string $host = null): JsonResponse
    {
        if (null !== $host && ! $this->siteRegistry->isKnownHost($host)) {
            return $this->badRequest('Unknown host');
        }

        // Already generating — never start a second pass for the same scope.
        if ($this->isGenerationRunning($host)) {
            return $this->running($host, started: false);
        }

        $incremental = $request->query->getBoolean('incremental')
            || true === ($this->decodeJson($request)['incremental'] ?? null);

        $this->coordinator->startGeneration($host, $incremental);

        return $this->running($host, started: true);
    }

    #[Route(
        '/api/static/{host}',
        name: 'pushword_api_static_status',
        requirements: ['host' => '[^/]+'],
        defaults: ['host' => null],
        methods: ['GET'],
    )]
    public function status(?string $host = null): JsonResponse
    {
        if (null !== $host && ! $this->siteRegistry->isKnownHost($host)) {
            return $this->badRequest('Unknown host');
        }

        $state = $this->coordinator->readOutput($this->coordinator->getProcessType($host));
        $lastGeneratedAt = $this->coordinator->getLastGenerationTime($host);

        // "completed" with nothing behind it means the scope was never generated.
        $status = $state['status'];
        if ('completed' === $status && null === $lastGeneratedAt && '' === $state['output']) {
            $status = 'idle';
        }

        $payload = [
            'host' => $host,
            'status' => $status,
            'running' => $state['isRunning'],
            'lastGeneratedAt' => $lastGeneratedAt?->format(DateTimeInterface::ATOM),
            'errorCount' => \count($state['errors']),
            'errors' => $state['errors'],
        ];

        if ($state['isRunning'] || 'error' === $status) {
            $payload['output'] = $state['output'];
        }

        return $this->respond($payload);
    }

    /**
     * Why a whole-site pass — not this endpoint — has to publish that page. Each
     * of these makes the underlying generator write nothing, which would otherwise
     * be reported as a success.
     */
    private function skipReason(Page $page): ?string
    {
        if (! $page->isPublished()) {
            return 'page_not_published';
        }

        if (null !== $page->holdPublicationAt) {
            return 'publication_on_hold';
        }

        // A redirection is an entry in the host-wide redirect map, not a file.
        if ($page->hasRedirection()) {
            return 'page_is_a_redirection';
        }

        if (false === $page->getCustomProperty('cache')) {
            return 'cache_disabled_for_page';
        }

        return null;
    }

    /**
     * Whether a generation this scope must wait for is under way — its own, or the
     * one it cross-locks with.
     */
    private function isGenerationRunning(?string $host): bool
    {
        if (null !== $this->coordinator->findBlockingProcess($host)) {
            return true;
        }

        return $this->coordinator->getProcessInfo($this->coordinator->getProcessType($host))['isRunning'];
    }

    private function running(?string $host, bool $started): JsonResponse
    {
        return $this->respond([
            'host' => $host,
            'status' => 'running',
            'started' => $started,
            'statusUrl' => $this->generateUrl(
                'pushword_api_static_status',
                null !== $host ? ['host' => $host] : [],
            ),
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(): array
    {
        $hostParam = [
            'name' => 'host',
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'string'],
            'description' => 'A configured host.',
        ];

        return [
            'paths' => [
                '/api/static/{host}' => [
                    'get' => [
                        'summary' => 'Static generation status for a site',
                        'description' => 'Returns the current status (idle|running|completed|error), when the site was last generated in full, the live console output while running, and the errors of the last pass. Call `/api/static` for every site at once.',
                        'parameters' => [$hostParam],
                        'responses' => [
                            '200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/StaticStatus']]]],
                            '400' => ['description' => 'Unknown host'],
                            '401' => ['description' => 'Missing or invalid Bearer token'],
                        ],
                    ],
                    'post' => [
                        'summary' => 'Generate a whole site (background)',
                        'description' => 'Dispatches a background generation and returns 202 with a statusUrl to poll. If one is already running for this scope, no second pass starts. Pass `?incremental=1` to regenerate only the pages that changed. Call `/api/static` to generate every site at once.',
                        'parameters' => [$hostParam, [
                            'name' => 'incremental',
                            'in' => 'query',
                            'required' => false,
                            'schema' => ['type' => 'boolean'],
                            'description' => 'Only regenerate pages changed since the last generation.',
                        ]],
                        'responses' => [
                            '202' => ['description' => 'Generation running (started or already in progress)'],
                            '400' => ['description' => 'Unknown host'],
                            '401' => ['description' => 'Missing or invalid Bearer token'],
                        ],
                    ],
                ],
                '/api/static/{host}/{slug}' => [
                    'post' => [
                        'summary' => 'Regenerate a single page (synchronous)',
                        'description' => 'Rebuilds one page in-process and answers with the outcome — one render, no polling. Only that page\'s file is written: sitemap, feeds, listing pages and the redirection map are rebuilt by the whole-site pass, so follow a page creation or deletion with `POST /api/static/{host}`.',
                        'parameters' => [$hostParam, [
                            'name' => 'slug',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                            'description' => 'The page slug (`homepage` for the home page), as used by `/api/page/{host}/{slug}`.',
                        ]],
                        'responses' => [
                            '200' => ['description' => 'Page regenerated', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/StaticPage']]]],
                            '400' => ['description' => 'Unknown host'],
                            '401' => ['description' => 'Missing or invalid Bearer token'],
                            '404' => ['description' => 'Page not found'],
                            '409' => ['description' => 'Nothing to write for this page (`page_not_published`, `publication_on_hold`, `page_is_a_redirection`, `cache_disabled_for_page`) or a whole-site generation is running (`generation_running`)'],
                            '500' => ['description' => 'The page failed to render; `errors` says why'],
                        ],
                    ],
                ],
            ],
            'components' => ['schemas' => [
                'StaticStatus' => [
                    'type' => 'object',
                    'properties' => [
                        'host' => ['type' => ['string', 'null']],
                        'status' => ['type' => 'string', 'enum' => ['idle', 'running', 'completed', 'error']],
                        'running' => ['type' => 'boolean'],
                        'lastGeneratedAt' => ['type' => ['string', 'null'], 'format' => 'date-time', 'description' => 'Last full generation of this site. Single-page regenerations do not move it.'],
                        'errorCount' => ['type' => 'integer'],
                        'errors' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'output' => ['type' => 'string', 'description' => 'Live console output, present while running or on error.'],
                    ],
                ],
                'StaticPage' => [
                    'type' => 'object',
                    'properties' => [
                        'host' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                        'generated' => ['type' => 'boolean'],
                        'durationMs' => ['type' => 'integer'],
                        'errors' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
            ]],
        ];
    }
}
