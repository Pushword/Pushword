<?php

namespace Pushword\Core\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class MediaController extends AbstractController
{
    private string $publicMediaDir;

    public function __construct(string $publicMediaDir)
    {
        $this->publicMediaDir = $publicMediaDir;
    }

    public function download(string $media)
    {
        $projectDir = $this->get('kernel')->getProjectDir();
        $pathToFile = $projectDir.'/'.$this->publicMediaDir.'/'.str_replace('..', '', $media);

        if (! file_exists($pathToFile)) {
            throw $this->createNotFoundException('The media does not exist...');
        }

        $response = new BinaryFileResponse($pathToFile);
        //$response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT); ResponseHeaderBag::DISPOSITION_INLINE);

        // temporary hack until I dig why svg+xml file are return with this mimeType...
        if ('image/svg' == $response->getFile()->getMimeType()) {
            $response->headers->set('content-type', 'image/svg+xml');
        }

        return $response;
    }
}
