<?php

namespace Pushword\Flat\Controller\Api;

use Pushword\Api\Controller\AbstractApiController;
use Pushword\Api\Service\InvalidFrontmatterException;
use Pushword\Api\Service\PageWriter;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Service\RevisionCalculator;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\Serializer\PageFileSerializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Raw-markdown intake for pages: the request body is a flat `.md` file, parsed
 * and serialized server-side by {@see PageFileSerializer}, so clients never own
 * the YAML format. Every response body is the canonical file text a fresh flat
 * export would write — including on 409, so a client can overwrite its local
 * copy and re-apply.
 *
 * The write itself goes through the same {@see PageWriter} operation as the
 * JSON endpoints (same If-Match revision, validation, editedBy stamping); only
 * the intake differs. One deliberate difference: a file is a total document,
 * not a patch — a front-matter key present in the current canonical export but
 * missing from the uploaded file is reset to its default instead of being
 * silently kept (deleting a line in the file is an edit).
 */
#[IsGranted('ROLE_EDITOR')]
final class PageFileApiController extends AbstractApiController
{
    /**
     * Reset values for canonical front-matter columns removed from an uploaded
     * file, shaped so they pass through PageFrontmatterMapper's type guards.
     */
    private const array COLUMN_RESETS = [
        'title' => null,
        'h1' => null,
        'name' => null,
        'metaRobots' => null,
        'template' => null,
        'customCanonical' => null,
        'editMessage' => '',
        'weight' => 0,
        'tags' => [],
        'redirectFrom' => [],
        'translations' => [],
        'publishedAt' => null,
        'holdPublication' => false,
        'holdPublicationAt' => null,
        'mainImage' => null,
        'parentPage' => null,
        'variantOf' => null,
        'extendedPage' => null,
    ];

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PageFileSerializer $serializer,
        private readonly PageWriter $pageWriter,
        private readonly RevisionCalculator $revisions,
        private readonly SiteRegistry $apps,
    ) {
    }

    #[Route('/api/content/page/{host}/{slug}', name: 'pushword_api_content_page_file_update', requirements: ['slug' => '.+'], methods: ['PUT'])]
    public function update(string $host, string $slug, Request $request): Response
    {
        $page = $this->findPage($host, $slug);
        if (! $page instanceof Page) {
            return $this->notFound('Page not found');
        }

        $expected = $request->headers->get('If-Match');
        if (null === $expected || '' === $expected) {
            return $this->respond(['error' => 'Missing If-Match header'], Response::HTTP_PRECONDITION_REQUIRED);
        }

        if ($expected !== $this->revisions->compute($page)) {
            // The canonical current text lets the client overwrite its stale
            // local file and re-apply the edit on fresh bytes.
            return $this->markdownResponse($page, Response::HTTP_CONFLICT);
        }

        $parsed = $this->parseFile($request);
        if ($parsed instanceof Response) {
            return $parsed;
        }

        [$frontmatter, $body] = $parsed;
        $frontmatter = $this->withResets($page, $frontmatter);

        return $this->write($page, $frontmatter, $body, Response::HTTP_OK, update: true);
    }

    #[Route('/api/content/page/{host}/{slug}', name: 'pushword_api_content_page_file_create', requirements: ['slug' => '.+'], methods: ['POST'])]
    public function create(string $host, string $slug, Request $request): Response
    {
        $existing = $this->findPage($host, $slug);
        if ($existing instanceof Page) {
            return $this->markdownResponse($existing, Response::HTTP_CONFLICT);
        }

        $parsed = $this->parseFile($request);
        if ($parsed instanceof Response) {
            return $parsed;
        }

        [$frontmatter, $body] = $parsed;

        $page = new Page();
        $page->setSlug($slug);
        // Set before applying: same-host references (parentPage, variantOf,
        // translations) are resolved against $page->host.
        $page->host = $host;

        return $this->write($page, $frontmatter, $body, Response::HTTP_CREATED, update: false);
    }

    /**
     * @param array<string, mixed> $frontmatter
     */
    private function write(Page $page, array $frontmatter, string $body, int $status, bool $update): Response
    {
        try {
            $violations = $update
                ? $this->pageWriter->update($page, $frontmatter, $body, $this->getApiUser())
                : $this->pageWriter->create($page, $frontmatter, $body, $this->getApiUser());
        } catch (InvalidFrontmatterException $invalidFrontmatterException) {
            return $this->invalidFrontmatter($invalidFrontmatterException);
        }

        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        return $this->markdownResponse($page, $status);
    }

    /**
     * Split the uploaded file into (frontmatter, body). Errors respond as JSON:
     * a page file in this format always opens with a front-matter block, so a
     * body-only document is rejected instead of being applied as "reset
     * everything" under the replace semantics.
     *
     * @return array{0: array<string, mixed>, 1: string}|Response
     */
    private function parseFile(Request $request): array|Response
    {
        $content = $request->getContent();
        if ('' === $content) {
            return $this->badRequest('Empty request body: expected a markdown page file');
        }

        try {
            $document = $this->serializer->parse($content);
        } catch (ParseException $parseException) {
            return $this->badRequest('Malformed front matter: '.$parseException->getMessage());
        }

        $frontmatter = $document->matter();
        if (! \is_array($frontmatter) || [] === $frontmatter) {
            return $this->badRequest('Missing front matter: the body must be a flat page file opening with a `---` block');
        }

        /** @var array<string, mixed> $frontmatter */
        unset($frontmatter['revision']); // export-only stamp; If-Match carries the revision

        return [$frontmatter, $document->body()];
    }

    /**
     * Replace semantics for the file intake: a key present in the current
     * canonical export but absent from the upload was deleted by the editor.
     * Columns get an explicit reset value the mapper accepts; a vanished custom
     * property is removed outright; a non-default locale falls back to the
     * site locale (the exporter omits the default one).
     *
     * @param array<string, mixed> $frontmatter
     *
     * @return array<string, mixed>
     */
    private function withResets(Page $page, array $frontmatter): array
    {
        $currentMatter = $this->serializer->parse($this->serializer->serialize($page))->matter();
        if (! \is_array($currentMatter)) {
            return $frontmatter;
        }

        unset($currentMatter['revision']);

        foreach (array_keys($currentMatter) as $key) {
            $key = (string) $key;
            if (\array_key_exists($key, $frontmatter)) {
                continue;
            }

            if (\array_key_exists($key, self::COLUMN_RESETS)) {
                $frontmatter[$key] = self::COLUMN_RESETS[$key];
            } elseif ('locale' === $key) {
                $frontmatter['locale'] = $this->apps->get($page->host)->locale;
            } elseif ($page->hasCustomProperty($key)) {
                $page->removeCustomProperty($key);
            }
        }

        return $frontmatter;
    }

    private function markdownResponse(Page $page, int $status): Response
    {
        return new Response($this->serializer->serialize($page), $status, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'ETag' => $this->revisions->compute($page),
        ]);
    }

    private function findPage(string $host, string $slug): ?Page
    {
        return $this->pageRepository->findOneBy([
            'host' => $host,
            'slug' => Page::normalizeSlug($slug),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(): array
    {
        $markdownBody = [
            'text/markdown' => ['schema' => [
                'type' => 'string',
                'description' => 'A flat page file: `---` YAML front matter, then the markdown body',
            ]],
        ];

        $canonicalResponse = [
            'description' => 'Canonical file text a fresh flat export would write (carries the new `revision:`)',
            'content' => ['text/markdown' => ['schema' => ['type' => 'string']]],
        ];

        return [
            'paths' => [
                '/api/content/page/{host}/{slug}' => [
                    'parameters' => [
                        ['name' => 'host', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                        ['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ],
                    'put' => [
                        'summary' => 'Replace a page from its raw .md file text (optimistic concurrency)',
                        'description' => 'The server parses and re-serializes the file, so clients hold no YAML: send the bytes, write the response bytes back. A front-matter key missing from the upload (vs the current canonical file) is reset — a file is a total document, not a patch.',
                        'parameters' => [
                            ['name' => 'If-Match', 'in' => 'header', 'required' => true, 'schema' => ['type' => 'string'], 'description' => 'The `revision:` value from the file'],
                        ],
                        'requestBody' => ['required' => true, 'content' => $markdownBody],
                        'responses' => [
                            '200' => $canonicalResponse,
                            '400' => ['description' => 'Empty body, malformed YAML, or missing front matter'],
                            '404' => ['description' => 'Page not found'],
                            '409' => ['description' => 'Revision mismatch — the body is the server\'s current canonical file text; overwrite the local file and re-apply', 'content' => ['text/markdown' => ['schema' => ['type' => 'string']]]],
                            '422' => ['description' => 'invalid_frontmatter or validation error (JSON)'],
                            '428' => ['description' => 'Missing If-Match header'],
                        ],
                    ],
                    'post' => [
                        'summary' => 'Create a page from its raw .md file text',
                        'requestBody' => ['required' => true, 'content' => $markdownBody],
                        'responses' => [
                            '201' => $canonicalResponse,
                            '400' => ['description' => 'Empty body, malformed YAML, or missing front matter'],
                            '409' => ['description' => 'Page already exists — the body is its current canonical file text', 'content' => ['text/markdown' => ['schema' => ['type' => 'string']]]],
                            '422' => ['description' => 'invalid_frontmatter or validation error (JSON)'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
