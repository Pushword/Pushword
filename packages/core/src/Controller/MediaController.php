<?php

namespace Pushword\Core\Controller;

use League\Flysystem\FilesystemException;
use Pushword\Core\Service\MediaStorageAdapter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class MediaController extends AbstractController
{
    public function __construct(
        private readonly MediaStorageAdapter $mediaStorage,
    ) {
    }

    #[Route('/%pw.public_media_dir%/{media}', name: 'pushword_media_download', requirements: ['media' => RoutePatterns::MEDIA], methods: ['GET', 'HEAD'])]
    public function download(string $media): BinaryFileResponse|StreamedResponse
    {
        $mediaPath = str_replace('..', '', $media);

        try {
            if (! $this->mediaStorage->fileExists($mediaPath)) {
                throw $this->createNotFoundException('The media does not exist...');
            }
        } catch (FilesystemException) {
            throw $this->createNotFoundException('The media does not exist...');
        }

        // For local storage, use BinaryFileResponse for better performance
        if ($this->mediaStorage->isLocal()) {
            $pathToFile = $this->mediaStorage->getLocalPath($mediaPath);
            $binaryFileResponse = new BinaryFileResponse($pathToFile);

            if ($this->isSvgMimeType($binaryFileResponse->getFile()->getMimeType())) {
                $this->secureSvgResponse($binaryFileResponse);
            }

            return $binaryFileResponse;
        }

        // For remote storage, use StreamedResponse
        try {
            $mimeType = $this->mediaStorage->mimeType($mediaPath);
        } catch (FilesystemException) {
            $mimeType = 'application/octet-stream';
        }

        $storage = $this->mediaStorage;

        $response = new StreamedResponse(
            static function () use ($storage, $mediaPath): void {
                $stream = $storage->readStream($mediaPath);
                fpassthru($stream);
                fclose($stream);
            },
            Response::HTTP_OK,
            ['Content-Type' => $mimeType]
        );

        if ($this->isSvgMimeType($mimeType)) {
            $this->secureSvgResponse($response);
        }

        return $response;
    }

    private function isSvgMimeType(?string $mimeType): bool
    {
        return \in_array($mimeType, ['image/svg', 'image/svg+xml'], true);
    }

    private function secureSvgResponse(Response $response): void
    {
        $response->headers->set('Content-Type', 'image/svg+xml');
        $response->headers->set('Content-Security-Policy', "sandbox; default-src 'none'; style-src 'unsafe-inline'; img-src data:");
        $response->headers->set('X-Content-Type-Options', 'nosniff');
    }
}
