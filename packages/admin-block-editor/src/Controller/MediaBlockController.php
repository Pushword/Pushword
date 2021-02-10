<?php

namespace Pushword\AdminBlockEditor\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use LogicException;
use Pushword\Core\Component\EntityFilter\Filter\RequiredMediaClass;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Repository\Repository;
use Pushword\Core\Service\ImageManager;
use Pushword\Core\Utils\Entity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @IsGranted("ROLE_EDITOR")
 */
final class MediaBlockController extends AbstractController
{
    use RequiredMediaClass;
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function manage(Request $request, ImageManager $imageManager): Response
    {
        /** @param File $mediaFile */
        $mediaFile = $request->getContent() ? $this->getMediaFileFromUrl($request->getContent())
            : $request->files->get('image');

        if (null === $mediaFile) {
            throw new Exception('...');
        }

        if (false === strpos($mediaFile->getMimeType(), 'image/')) {
            return new Response(json_encode(['error' => 'media sent is not an image']));
        }

        if ($mediaFile instanceof MediaInterface) {
            $media = $mediaFile;
        } else {
            $mediaClass = $this->mediaClass;
            /** @param MediaInterface $media */
            $media = new $mediaClass();
            $media->setMediaFile($mediaFile);
            $this->em->persist($media);
            $this->em->flush();
        }

        return new Response(json_encode([
            'success' => 1,
            'file' => $this->exportMedia($media, $imageManager->getBrowserPath($media->getMedia())),
        ]));
    }

    private function exportMedia(MediaInterface $media, string $url): array
    {
        $properties = Entity::getProperties($media);

        $data = [];
        foreach ($properties as $property) {
            if (\in_array($property, ['id'])) {
                continue;
            }
            $getter = 'get'.ucfirst($property);
            $data[$property] = $media->$getter();
        }

        $data['url'] = $url;

        return $data;
    }

    /**
     * Store in tmp system dir a cache from dist URL.
     *
     * @return UploadedFile|null
     */
    private function getMediaFileFromUrl(string $content)
    {
        $content = json_decode($content, true);

        if (! isset($content['url'])) {
            throw new LogicException('URL not sent by editor.js ?!');
        }

        if (! preg_match('#/([^/]*)$#', $content['url'], $matches)) {
            throw new LogicException("URL doesn't contain file name");
        }

        if (0 === strpos($content['url'], '/media/default/')) {
            if (! $media = Repository::getMediaRepository($this->em, $this->mediaClass)
                ->findOneBy(['media' => substr($content['url'], \strlen('/media/default/'))])) {
                throw new LogicException('Media not found');
            }

            return $media;
        }

        if (! $fileContent = file_get_contents($content['url'])) {
            throw new LogicException('URL unreacheable');
        }

        $originalName = $matches[1];
        $filename = md5($matches[1]);
        $filePath = sys_get_temp_dir().'/'.$filename;
        if (file_put_contents($filePath, $fileContent)) {
            $mimeType = mime_content_type($filePath);

            return new UploadedFile($filePath, $originalName, $mimeType, null, true);
        }
    }
}
