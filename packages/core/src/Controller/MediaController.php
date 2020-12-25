<?php

namespace Pushword\Core\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class MediaController extends AbstractController
{
    protected $translator;

    public function download(string $path)
    {
        $projectDir = $this->get('kernel')->getProjectDir();
        $pathToFile = $projectDir.'/media/'.substr(str_replace('..', '', $path), \strlen('media/'));

        if (! file_exists($pathToFile)) {
            throw $this->createNotFoundException('The media does not exist...');
        }

        $response = new BinaryFileResponse($pathToFile);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

        return $response;
    }
}
