<?php

namespace Pushword\AdminBlockEditor\Controller;

use Doctrine\ORM\EntityManagerInterface;
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
        /** @var File $mediaFile */
        $mediaFile = $request->getContent() ? $this->getMediaFrom($request->getContent())
            : $request->files->get('image');

        //if (false === strpos($mediaFile->getMimeType(), 'image/')) { return new Response(json_encode(['error' => 'media sent is not an image'])); }

        if ($mediaFile instanceof MediaInterface) {
            $media = $mediaFile;
        } else {
            $mediaClass = $this->mediaClass;
            /** @var MediaInterface $media */
            $media = new $mediaClass();
            $media->setMediaFile($mediaFile);
            $this->em->persist($media);
            $this->em->flush();
        }

        $url = false === strpos($media->getMimeType(), 'image/') ? '/download/'.$media->getMedia()
             : $imageManager->getBrowserPath($media->getMedia());

        return new Response(json_encode([
            'success' => 1,
            'file' => $this->exportMedia($media, $url),
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
     * @return UploadedFile|MediaInterface
     */
    private function getMediaFrom($content)
    {
        $content = json_decode($content, true);

        if (! isset($content['url']) && ! isset($content['id'])) {
            throw new LogicException('URL not sent by editor.js ?!');
        }

        if (isset($content['id'])) {
            return $this->getMediaFileFromId($content['id']);
        }

        if (0 === strpos($content['url'], '/media/default/')) {
            return $this->getMediaFromMedia(substr($content['url'], \strlen('/media/default/')));
        }

        return $this->getMediaFileFromUrl($content['url']);
    }

    private function getMediaFromMedia(string $media): MediaInterface
    {
        if (! $media = Repository::getMediaRepository($this->em, $this->mediaClass)->findOneBy(['media' => $media])) {
            throw new LogicException('Media not found');
        }

        return $media;
    }

    /**
     * Store in tmp system dir a cache from dist URL.
     */
    private function getMediaFileFromUrl(string $url): UploadedFile
    {
        if (! preg_match('#/([^/]*)$#', $url, $matches)) {
            throw new LogicException("URL doesn't contain file name");
        }

        if (! $fileContent = file_get_contents($url)) {
            throw new LogicException('URL unreacheable');
        }

        $originalName = $matches[1];
        $filename = md5($matches[1]);
        $filePath = sys_get_temp_dir().'/'.$filename;
        if (! file_put_contents($filePath, $fileContent)) {
            throw new LogicException('Storing in tmp folder filed');
        }

        $mimeType = mime_content_type($filePath);

        return new UploadedFile($filePath, $originalName, $mimeType, null, true);
    }

    private function getMediaFileFromId(string $id): MediaInterface
    {
        $id = (int) $id;
        if (! $media = Repository::getMediaRepository($this->em, $this->mediaClass)->findOneBy(['id' => $id])) {
            throw new LogicException('Media not found');
        }

        return $media;
    }
}
