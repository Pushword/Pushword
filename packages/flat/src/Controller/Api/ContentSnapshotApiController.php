<?php

namespace Pushword\Flat\Controller\Api;

use FilesystemIterator;
use Pushword\Api\Controller\AbstractApiController;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\FlatFileContentDirFinder;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Streams the flat content directory of a host — or every host at once — as a
 * gzipped tarball, so marketing power users can pull a fresh, grep-able
 * `content/` snapshot to edit locally with their AI agent.
 *
 * Strictly read-only: it serves whatever the existing DB → flat mirror task
 * already wrote on disk. It never writes, locks, or triggers an export. The
 * flat directory path is resolved through {@see FlatFileContentDirFinder}, the
 * same source of truth used by the exporters and `pw:flat:sync`.
 */
#[IsGranted('ROLE_EDITOR')]
final class ContentSnapshotApiController extends AbstractApiController
{
    public function __construct(
        private readonly SiteRegistry $siteRegistry,
        private readonly FlatFileContentDirFinder $contentDirFinder,
    ) {
    }

    #[Route('/api/content/snapshot.tar.gz', name: 'pushword_api_content_snapshot', methods: ['GET'])]
    public function snapshot(Request $request): Response
    {
        $host = $request->query->getString('host');

        // Validate the host against the configured sites before touching the
        // filesystem. An unknown value (including path-traversal attempts like
        // `../../etc`) never resolves to a known host, so it is rejected here
        // and the resolved path always comes from FlatFileContentDirFinder.
        if ('' !== $host && ! $this->siteRegistry->isKnownHost($host)) {
            return $this->badRequest('Unknown host');
        }

        $dir = '' !== $host
            ? $this->contentDirFinder->get($host)
            : $this->contentDirFinder->getBaseDir();

        if (! $this->hasMarkdown($dir)) {
            return $this->notFound('No content snapshot available');
        }

        $filename = \sprintf('snapshot-%s-%s.tar.gz', $host ?: 'all', date('Y-m-d'));

        $response = new StreamedResponse(fn () => $this->streamTarball($dir));
        $response->headers->set('Content-Type', 'application/gzip');
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
        );

        return $response;
    }

    private function hasMarkdown(string $dir): bool
    {
        if (! is_dir($dir)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && 'md' === $file->getExtension()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pipe a gzipped tarball of the directory straight to the client. Using the
     * native `tar` via proc_open (array form, no shell) keeps memory flat for
     * multi-MB directories. `--exclude-vcs` drops `.git`; `--exclude=./.*`
     * drops root-level dotfiles.
     */
    private function streamTarball(string $dir): void
    {
        $process = proc_open(
            ['tar', '-czf', '-', '--exclude-vcs', '--exclude=./.*', '-C', $dir, '.'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (! \is_resource($process)) {
            return;
        }

        while (! feof($pipes[1])) {
            $chunk = fread($pipes[1], 8192);
            if (false === $chunk) {
                break;
            }

            echo $chunk;
            flush();
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(): array
    {
        return [
            'paths' => [
                '/api/content/snapshot.tar.gz' => [
                    'get' => [
                        'summary' => 'Download the flat content directory as a gzipped tarball',
                        'description' => 'Streams the DB → flat mirror of a host (or every host when `host` is omitted) as application/gzip. Read-only.',
                        'parameters' => [
                            [
                                'name' => 'host',
                                'in' => 'query',
                                'required' => false,
                                'schema' => ['type' => 'string'],
                                'description' => 'A configured host; omit to snapshot every site at once.',
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'gzip tarball',
                                'content' => ['application/gzip' => ['schema' => ['type' => 'string', 'format' => 'binary']]],
                            ],
                            '400' => ['description' => 'Unknown host'],
                            '401' => ['description' => 'Missing or invalid Bearer token'],
                            '404' => ['description' => 'No content available'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
