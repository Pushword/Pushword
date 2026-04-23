<?php

namespace Pushword\Core\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Yaml\Yaml;

final class MediaApiController extends AbstractController
{
    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/media/{filename}', name: 'media_api', requirements: ['filename' => RoutePatterns::MEDIA], methods: ['GET', 'POST', 'DELETE'])]
    public function __invoke(string $filename, Request $request): JsonResponse
    {
        $token = $request->headers->get('Authorization');
        if (null === $token || ! str_starts_with($token, 'Bearer ')) {
            return new JsonResponse(['error' => 'Missing or invalid Authorization header'], Response::HTTP_UNAUTHORIZED);
        }

        $token = substr($token, 7);
        $user = $this->userRepository->findOneBy(['apiToken' => $token]);
        if (null === $user) {
            return new JsonResponse(['error' => 'Invalid token'], Response::HTTP_UNAUTHORIZED);
        }

        if ('POST' === $request->getMethod() && $request->files->has('file')) {
            return $this->handleUpload($filename, $request);
        }

        $media = $this->mediaRepository->findOneByFileNameOrHistory($filename);
        if (null === $media) {
            return new JsonResponse(['error' => 'Media not found'], Response::HTTP_NOT_FOUND);
        }

        if ('DELETE' === $request->getMethod()) {
            $this->entityManager->remove($media);
            $this->entityManager->flush();

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        if ('POST' === $request->getMethod()) {
            $data = json_decode($request->getContent(), true);
            if (! \is_array($data)) {
                return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
            }

            $this->applyMetadata($media, $data);
            $this->entityManager->flush();
        }

        return new JsonResponse($this->mediaToArray($media));
    }

    private function handleUpload(string $filename, Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (! $file instanceof UploadedFile) {
            return new JsonResponse(['error' => 'No file received'], Response::HTTP_BAD_REQUEST);
        }

        if (! $file->isValid()) {
            return new JsonResponse(['error' => $file->getErrorMessage()], Response::HTTP_BAD_REQUEST);
        }

        $hash = sha1_file($file->getPathname(), true);
        if (false !== $hash) {
            $existing = $this->mediaRepository->findOneBy(['hash' => $hash]);
            if ($existing instanceof Media) {
                return new JsonResponse(
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

        return new JsonResponse($this->mediaToArray($media), Response::HTTP_CREATED);
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

        if (\array_key_exists('filename', $data) && \is_string($data['filename'])) {
            $media->setFileName($data['filename']);
        }
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
}
