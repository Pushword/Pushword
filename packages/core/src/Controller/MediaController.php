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
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

        return $response;
    }
}
