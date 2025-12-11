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

            // temporary hack until I dig why svg+xml file are return with this mimeType...
            if ('image/svg' === $binaryFileResponse->getFile()->getMimeType()) {
                $binaryFileResponse->headers->set('content-type', 'image/svg+xml');
            }

            return $binaryFileResponse;
        }

        // For remote storage, use StreamedResponse
        try {
            $mimeType = $this->mediaStorage->mimeType($mediaPath);
        } catch (FilesystemException) {
            $mimeType = 'application/octet-stream';
        }

        // Fix for SVG mime type
        if ('image/svg' === $mimeType) {
            $mimeType = 'image/svg+xml';
        }

        $storage = $this->mediaStorage;

        return new StreamedResponse(
            static function () use ($storage, $mediaPath): void {
                $stream = $storage->readStream($mediaPath);
                fpassthru($stream);
                fclose($stream);
            },
            Response::HTTP_OK,
            ['Content-Type' => $mimeType]
        );
    }
}
