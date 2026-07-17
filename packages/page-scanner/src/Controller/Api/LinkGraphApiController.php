<?php

namespace Pushword\PageScanner\Controller\Api;

use DateTimeInterface;
use Pushword\Api\Controller\AbstractApiController;
use Pushword\Core\Site\SiteRegistry;
use Pushword\PageScanner\Service\LinkGraphBuilder;
use Pushword\PageScanner\Service\LinkGraphStorage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * JSON mirror of `pw:link:graph`.
 *
 * Read-only on purpose: the graph is collected by the page scan, so there is
 * nothing separate to trigger here. When no snapshot exists — or when the one there
 * is no longer matches the content — this points at `POST /api/page-scan` rather
 * than growing a second way to start the same scan, with its own locking to keep in
 * step with the first. That is the one place it parts ways with the CLI, which
 * rescans a stale graph in-process because a CI gate cannot poll.
 */
#[IsGranted('ROLE_EDITOR')]
final class LinkGraphApiController extends AbstractApiController
{
    public function __construct(
        private readonly LinkGraphStorage $storage,
        private readonly LinkGraphBuilder $builder,
        private readonly SiteRegistry $siteRegistry,
    ) {
    }

    #[Route('/api/link-graph', name: 'pushword_api_link_graph', methods: ['GET'])]
    public function graph(Request $request): JsonResponse
    {
        // Required: a link graph is scoped to one site, never to a mix of them.
        $host = $request->query->getString('host', '');
        if ('' === $host) {
            return $this->badRequest('A host is required');
        }

        if (! $this->siteRegistry->isKnownHost($host)) {
            return $this->badRequest('Unknown host');
        }

        $snapshot = $this->storage->read($host);
        if (null === $snapshot) {
            return $this->respond([
                'host' => $host,
                'status' => 'idle',
                'message' => 'No link graph yet: run a page scan first.',
                'triggerUrl' => $this->generateUrl('pushword_api_page_scan_trigger', ['host' => $host]),
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $graph = $this->builder->build($snapshot['nodes'], $snapshot['edges']);
        $slug = $request->query->getString('page', '');
        $pages = '' === $slug
            ? $graph['pages']
            : array_values(array_filter(
                $graph['pages'],
                static fn (array $page): bool => $page['slug'] === trim($slug, '/'),
            ));

        if ('' !== $slug && [] === $pages) {
            return $this->notFound('Page not in the graph (never scanned, or not indexable: unpublished, noindex, or a redirection).');
        }

        return $this->respond([
            'host' => $host,
            'status' => 'completed',
            'generatedAt' => date(DateTimeInterface::ATOM, $snapshot['generatedAt']),
            // Reported, not repaired: this endpoint cannot render. The CLI re-runs the
            // scan itself when it finds a stale graph; over HTTP the caller decides.
            'stale' => $snapshot['stale'],
            ...$snapshot['stale'] ? ['triggerUrl' => $this->generateUrl('pushword_api_page_scan_trigger', ['host' => $host])] : [],
            'pageCount' => $graph['pageCount'],
            'edgeCount' => $graph['edgeCount'],
            'orphanCount' => $graph['orphanCount'],
            'homepageScanned' => $graph['homepageScanned'],
            'pages' => $pages,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(): array
    {
        return [
            'paths' => [
                '/api/link-graph' => [
                    'get' => [
                        'summary' => 'Internal link graph of the last page scan',
                        'description' => 'Per page: inbound/outbound link counts, the inbound sources, and depth from the homepage. '
                            .'The indexable graph only: noindex pages are neither nodes nor sources, like pages_list() by default. '
                            .'Only crawlable <a href> links count — link() obfuscates to a <span data-rot> and unpublished links render as a <span>, so neither is in the graph. '
                            .'The graph is what a crawler reaches WITHOUT paginating: rendered offline, pages_list() only emits its first pager page, '
                            .'so inboundCount is a lower bound and depth an upper bound for pages listed only on pager 2+. Orphans are exact under that rule.',
                        'parameters' => [[
                            'name' => 'host',
                            'in' => 'query',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                            'description' => 'A configured host. Required: a link graph is scoped to one site.',
                        ], [
                            'name' => 'page',
                            'in' => 'query',
                            'required' => false,
                            'schema' => ['type' => 'string'],
                            'description' => 'Restrict to one slug.',
                        ]],
                        'responses' => [
                            '200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/LinkGraph']]]],
                            '400' => ['description' => 'Missing or unknown host'],
                            '401' => ['description' => 'Missing or invalid Bearer token'],
                            '404' => ['description' => 'No graph yet (body carries triggerUrl), or unknown page'],
                        ],
                    ],
                ],
            ],
            'components' => ['schemas' => ['LinkGraph' => [
                'type' => 'object',
                'properties' => [
                    'host' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => ['idle', 'completed']],
                    'generatedAt' => ['type' => 'string', 'format' => 'date-time', 'description' => 'When the scan behind this graph ran.'],
                    'stale' => ['type' => 'boolean', 'description' => 'True when pages were added, edited or removed since the scan: the numbers below describe content that no longer exists. '
                        .'POST the triggerUrl (present only when stale) to refresh. A page inbound count changes when OTHER pages are edited, which is why this cannot be judged per page.'],
                    'triggerUrl' => ['type' => 'string', 'description' => 'Present only when stale: where to POST to rebuild the graph.'],
                    'pageCount' => ['type' => 'integer', 'description' => 'Indexable pages in the graph, not pages scanned.'],
                    'edgeCount' => ['type' => 'integer'],
                    'orphanCount' => ['type' => 'integer', 'description' => 'Pages with at most 1 inbound link.'],
                    'homepageScanned' => ['type' => 'boolean', 'description' => 'False when the host has no scanned homepage: depth is then unknown, not infinite.'],
                    'pages' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'host' => ['type' => 'string'],
                            'slug' => ['type' => 'string'],
                            'inboundCount' => ['type' => 'integer'],
                            'outboundCount' => ['type' => 'integer'],
                            'inbound' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Source pages, as host/slug.'],
                            'depth' => ['type' => ['integer', 'null'], 'description' => 'Clicks from the homepage; null when unreachable without a pager.'],
                        ],
                    ]],
                ],
            ]]],
        ];
    }
}
