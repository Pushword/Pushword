<?php

namespace Pushword\Flat\Controller\Api;

use Pushword\Api\Controller\AbstractApiController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Serves the Node sync client editor repos run as a Claude Code PostToolUse
 * hook. Shipping it from the instance (instead of copy-pasting it per editor
 * repo) keeps every client byte-identical to the server it talks to — the
 * client is a dumb byte pipe against /api/content/page, so a protocol change
 * ships atomically with the endpoint that defines it.
 */
#[IsGranted('ROLE_EDITOR')]
final class EditorSyncApiController extends AbstractApiController
{
    #[Route('/api/editor/sync.js', name: 'pushword_api_editor_sync_script', methods: ['GET'])]
    public function script(): Response
    {
        return new Response((string) file_get_contents(\dirname(__DIR__, 2).'/Resources/editor/sync.js'), Response::HTTP_OK, [
            'Content-Type' => 'text/javascript; charset=UTF-8',
            // Editors refresh this on every snapshot pull; a CDN-cached stale
            // copy would defeat the version-matching that is its whole point.
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(): array
    {
        return [
            'paths' => [
                '/api/editor/sync.js' => [
                    'get' => [
                        'summary' => 'Download the Node sync client (Claude Code PostToolUse hook)',
                        'description' => 'The YAML-free client that PUTs raw .md files to /api/content/page/{host}/{slug} and writes the canonical response bytes back. Refresh your local copy on every snapshot pull. Configure via PUSHWORD_API_BASE and PUSHWORD_TOKEN_FILE.',
                        'responses' => [
                            '200' => [
                                'description' => 'The client script',
                                'content' => ['text/javascript' => ['schema' => ['type' => 'string']]],
                            ],
                            '401' => ['description' => 'Missing or invalid Bearer token'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
