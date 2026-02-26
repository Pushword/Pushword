<?php

namespace Pushword\Core\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    #[Route('/api/media/{filename}', name: 'media_api', requirements: ['filename' => RoutePatterns::MEDIA], methods: ['GET', 'POST'])]
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

        $media = $this->mediaRepository->findOneByFileNameOrHistory($filename);
        if (null === $media) {
            return new JsonResponse(['error' => 'Media not found'], Response::HTTP_NOT_FOUND);
        }

        if ('POST' === $request->getMethod()) {
            $data = json_decode($request->getContent(), true);
            if (! \is_array($data)) {
                return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
            }

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

            $this->entityManager->flush();
        }

        return new JsonResponse([
            'filename' => $media->getFileName(),
            'mimeType' => $media->getMimeType(),
            'size' => $media->getSize(),
            'alt' => $media->getAlt(true),
            'alts' => $media->getAltsParsed(),
            'tags' => $media->getTagList(),
            'image' => $media->isImage() ? [
                'width' => $media->getWidth(),
                'height' => $media->getHeight(),
                'ratio' => $media->getRatio(),
                'ratioLabel' => $media->getRatioLabel(),
                'mainColor' => $media->getMainColor(),
            ] : null,
        ]);
    }
}
