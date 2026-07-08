<?php

namespace Pushword\Api\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Controller\RoutePatterns;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageRotator;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Yaml\Yaml;

#[IsGranted('ROLE_EDITOR')]
final class MediaApiController extends AbstractApiController
{
    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageRotator $imageRotator,
    ) {
    }

    #[Route('/api/media', name: 'pushword_api_media_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $pagination = $this->paginationParams($request);
        $qb = $this->mediaRepository->createQueryBuilder('m');

        $keyword = $request->query->get('q') ?? $request->query->get('search');
        if (null !== $keyword) {
            $qb->andWhere('m.fileName LIKE :q OR m.alt LIKE :q')
                ->setParameter('q', '%'.$keyword.'%');
        }

        if (null !== $request->query->get('mimeType')) {
            $qb->andWhere('m.mimeType = :mime')
                ->setParameter('mime', $request->query->getString('mimeType'));
        }

        if (null !== $request->query->get('tag')) {
            $qb->andWhere('m.tags LIKE :tag')
                ->setParameter('tag', '%'.$request->query->getString('tag').'%');
        }

        $totalQb = clone $qb;
        $total = (int) $totalQb->select('COUNT(m.id)')->getQuery()->getSingleScalarResult();

        $qb->orderBy('m.createdAt', 'DESC')
            ->setFirstResult($pagination['offset'])
            ->setMaxResults($pagination['perPage']);

        /** @var list<Media> $items */
        $items = $qb->getQuery()->getResult();
        $payload = array_map($this->mediaToArray(...), $items);

        return $this->respond($this->paginated($payload, $total, $pagination['page'], $pagination['perPage']));
    }

    #[Route('/api/media/{filename}', name: 'pushword_api_media', requirements: ['filename' => RoutePatterns::MEDIA], methods: ['GET', 'POST', 'PATCH', 'DELETE'])]
    public function item(string $filename, Request $request): JsonResponse
    {
        if ('POST' === $request->getMethod() && $request->files->has('file')) {
            return $this->handleUpload($filename, $request);
        }

        $media = $this->mediaRepository->findOneByFileNameOrHistory($filename);
        if (null === $media) {
            return $this->notFound('Media not found');
        }

        if ('DELETE' === $request->getMethod()) {
            $this->entityManager->remove($media);
            $this->entityManager->flush();

            return $this->noContent();
        }

        if (\in_array($request->getMethod(), ['POST', 'PATCH'], true)) {
            $raw = $request->getContent();
            if ('' === $raw) {
                return $this->badRequest('Empty body');
            }

            $data = json_decode($raw, true);
            if (! \is_array($data)) {
                return $this->badRequest('Invalid JSON');
            }

            if (\array_key_exists('rotate', $data)) {
                $rotateError = $this->applyRotation($media, $data['rotate']);
                if (null !== $rotateError) {
                    return $rotateError;
                }
            }

            $this->applyMetadata($media, $data);
            $this->entityManager->flush();
        }

        return $this->respond($this->mediaToArray($media));
    }

    private function handleUpload(string $filename, Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (! $file instanceof UploadedFile) {
            return $this->badRequest('No file received');
        }

        if (! $file->isValid()) {
            return $this->badRequest($file->getErrorMessage());
        }

        $hash = sha1_file($file->getPathname(), true);
        if (false !== $hash) {
            $existing = $this->mediaRepository->findOneBy(['hash' => $hash]);
            if ($existing instanceof Media) {
                return $this->respond(
                    ['duplicate' => true] + $this->mediaToArray($existing),
                    Response::HTTP_OK,
                );
            }
        }

        $media = new Media();
        $media->setFileName($filename);
        $media->setMediaFile($file);

        $this->applyMetadata($media, $this->extractMultipartMetadata($request));

        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $this->respond($this->mediaToArray($media), Response::HTTP_CREATED);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function applyMetadata(Media $media, array $data): void
    {
        if (\array_key_exists('alt', $data) && \is_string($data['alt'])) {
            $media->setAlt($data['alt']);
        }

        if (\array_key_exists('alts', $data) && \is_array($data['alts'])) {
            $media->setAlts(Yaml::dump($data['alts']));
        }

        if (\array_key_exists('tags', $data) && \is_array($data['tags'])) {
            /** @var string[] $tags */
            $tags = $data['tags'];
            $media->setTags($tags);
        }

        // Rename log: lets the importer replay the harvester's rename chain so a
        // body reference to an old/original filename still resolves via
        // MediaRepository::findOneByFileNameOrHistory(). Without this the API
        // silently drops it and every stale reference 404s / degrades.
        if (\array_key_exists('fileNameHistory', $data) && \is_array($data['fileNameHistory'])) {
            /** @var string[] $history */
            $history = array_values(array_filter($data['fileNameHistory'], is_string(...)));
            $media->setFileNameHistory($history);
        }

        if (\array_key_exists('filename', $data) && \is_string($data['filename'])) {
            $media->setFileName($data['filename']);
        }
    }

    /**
     * Rotate the master image clockwise by a multiple of 90 degrees. A no-op
     * rotation (0 / 360) is silently ignored. Returns a 400 response on invalid
     * input, or null on success.
     */
    private function applyRotation(Media $media, mixed $degrees): ?JsonResponse
    {
        if (! $media->isImage()) {
            return $this->badRequest('Only images can be rotated');
        }

        if (! \is_int($degrees) || 0 !== $degrees % 90) {
            return $this->badRequest('rotate must be an integer multiple of 90 degrees');
        }

        // A full turn (0 / ±360) is a no-op; only touch the file for a real rotation.
        if (0 !== $degrees % 360) {
            $this->imageRotator->rotate($media, $degrees);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractMultipartMetadata(Request $request): array
    {
        $data = [];

        $alt = $request->request->get('alt');
        if (\is_string($alt)) {
            $data['alt'] = $alt;
        }

        $alts = $this->decodeJsonArray($request->request->get('alts'));
        if (null !== $alts) {
            $data['alts'] = $alts;
        }

        $tags = $this->decodeJsonArray($request->request->get('tags'));
        if (null !== $tags) {
            $data['tags'] = $tags;
        }

        $fileNameHistory = $this->decodeJsonArray($request->request->get('fileNameHistory'));
        if (null !== $fileNameHistory) {
            $data['fileNameHistory'] = $fileNameHistory;
        }

        return $data;
    }

    /**
     * @return array<mixed>|null
     */
    private function decodeJsonArray(mixed $raw): ?array
    {
        if (! \is_string($raw) || '' === $raw) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaToArray(Media $media): array
    {
        return [
            'filename' => $media->getFileName(),
            'mimeType' => $media->getMimeType(),
            'size' => $media->getSize(),
            'hash' => $this->hashToHex($media->getHash()),
            'fileNameHistory' => $media->getFileNameHistory(),
            'alt' => $media->getAlt(true),
            'alts' => $media->getAltsParsed(),
            'tags' => $media->getTagList(),
            'customProperties' => $media->getCustomProperties(),
            'image' => $media->isImage() ? [
                'width' => $media->getWidth(),
                'height' => $media->getHeight(),
                'ratio' => $media->getRatio(),
                'ratioLabel' => $media->getRatioLabel(),
                'mainColor' => $media->getMainColor(),
            ] : null,
        ];
    }

    private function hashToHex(mixed $hash): ?string
    {
        if (\is_resource($hash)) {
            $hash = stream_get_contents($hash);
        }

        return \is_string($hash) && '' !== $hash ? bin2hex($hash) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(): array
    {
        $mediaSchema = [
            'type' => 'object',
            'properties' => [
                'filename' => ['type' => 'string'],
                'mimeType' => ['type' => 'string', 'nullable' => true],
                'size' => ['type' => 'integer'],
                'hash' => ['type' => 'string', 'nullable' => true],
                'fileNameHistory' => ['type' => 'array', 'items' => ['type' => 'string']],
                'alt' => ['type' => 'string', 'nullable' => true],
                'alts' => ['type' => 'object', 'additionalProperties' => ['type' => 'string']],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                'customProperties' => ['type' => 'object', 'additionalProperties' => true],
                'image' => [
                    'type' => 'object',
                    'nullable' => true,
                    'properties' => [
                        'width' => ['type' => 'integer'],
                        'height' => ['type' => 'integer'],
                        'ratio' => ['type' => 'number'],
                        'ratioLabel' => ['type' => 'string'],
                        'mainColor' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        return [
            'paths' => [
                '/api/media' => [
                    'get' => [
                        'summary' => 'List media',
                        'parameters' => [
                            ['name' => 'q', 'in' => 'query', 'description' => 'Keyword filter on filename and alt (alias: search)', 'schema' => ['type' => 'string']],
                            ['name' => 'search', 'in' => 'query', 'description' => 'Alias of q', 'schema' => ['type' => 'string']],
                            ['name' => 'mimeType', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'tag', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Paginated list', 'content' => ['application/json' => ['schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'items' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Media']],
                                    'total' => ['type' => 'integer'],
                                    'page' => ['type' => 'integer'],
                                    'per_page' => ['type' => 'integer'],
                                ],
                            ]]]],
                        ],
                    ],
                ],
                '/api/media/{filename}' => [
                    'parameters' => [['name' => 'filename', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                    'get' => [
                        'summary' => 'Get media metadata',
                        'responses' => [
                            '200' => ['description' => 'Media', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Media']]]],
                            '404' => ['description' => 'Not found'],
                        ],
                    ],
                    'post' => [
                        'summary' => 'Upload media (multipart) OR update metadata (JSON)',
                        'requestBody' => [
                            'content' => [
                                'multipart/form-data' => ['schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'file' => ['type' => 'string', 'format' => 'binary'],
                                        'alt' => ['type' => 'string'],
                                        'alts' => ['type' => 'string', 'description' => 'JSON-encoded localized alts'],
                                        'tags' => ['type' => 'string', 'description' => 'JSON-encoded tag list'],
                                    ],
                                ]],
                                'application/json' => ['schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'alt' => ['type' => 'string'],
                                        'alts' => ['type' => 'object'],
                                        'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                                        'filename' => ['type' => 'string'],
                                        'rotate' => ['type' => 'integer', 'description' => 'Rotate the master image clockwise by a multiple of 90 (90, 180, 270; negative rotates counter-clockwise). Regenerates the cache.'],
                                    ],
                                ]],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Updated or duplicate'],
                            '201' => ['description' => 'Created'],
                            '400' => ['description' => 'Bad request'],
                        ],
                    ],
                    'patch' => [
                        'summary' => 'Update media metadata or rotate the image',
                        'requestBody' => ['content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => [
                                'alt' => ['type' => 'string'],
                                'alts' => ['type' => 'object'],
                                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                                'filename' => ['type' => 'string'],
                                'rotate' => ['type' => 'integer', 'description' => 'Rotate the master image clockwise by a multiple of 90 (90, 180, 270; negative rotates counter-clockwise). Regenerates the cache.'],
                            ],
                        ]]]],
                        'responses' => ['200' => ['description' => 'Updated']],
                    ],
                    'delete' => [
                        'summary' => 'Delete media',
                        'responses' => ['204' => ['description' => 'Deleted'], '404' => ['description' => 'Not found']],
                    ],
                ],
            ],
            'components' => [
                'schemas' => ['Media' => $mediaSchema],
            ],
        ];
    }
}
