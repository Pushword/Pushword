<?php

namespace Pushword\Core\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Attribute\Route;

final class MediaController extends AbstractController
{
    public function __construct(
        private readonly string $publicMediaDir,
        private readonly string $projectDir,
    ) {
    }

    #[Route('/%pw.public_media_dir%/{media}', name: 'pushword_media_download', requirements: ['media' => RoutePatterns::MEDIA], methods: ['GET', 'HEAD'])]
    public function download(string $media): BinaryFileResponse
    {
        $pathToFile = $this->projectDir.'/'.$this->publicMediaDir.'/'.str_replace('..', '', $media);

        if (! file_exists($pathToFile)) {
            throw $this->createNotFoundException('The media does not exist...');
        }

        $binaryFileResponse = new BinaryFileResponse($pathToFile);
        // $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT); ResponseHeaderBag::DISPOSITION_INLINE);

        // temporary hack until I dig why svg+xml file are return with this mimeType...
        if ('image/svg' === $binaryFileResponse->getFile()->getMimeType()) {
            $binaryFileResponse->headers->set('content-type', 'image/svg+xml');
        }

        return $binaryFileResponse;
    }
}
