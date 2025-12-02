<?php

namespace Pushword\AdminBlockEditor\Controller;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use LogicException;
use Pushword\Core\Entity\Media;
use Pushword\Core\Service\ImageManager;
use Pushword\Core\Utils\Entity;

use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\mime_content_type;
use function Safe\preg_match;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
final class MediaBlockController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%pw.public_media_dir%')]
        private readonly string $publicMediaDir,
        private readonly ImageManager $imageManager,
    ) {
    }

    #[Route('/admin/media/block', name: 'admin_media_block', methods: ['POST'])]
    public function manage(Request $request, string $publicMediaDir): Response
    {
        /** @var File|Media $mediaFile */
        $mediaFile = '' !== $request->getContent() && '0' !== $request->getContent() ? $this->getMediaFrom($request->getContent())
            : $request->files->get('image');

        // if (false === strpos($mediaFile->getMimeType(), 'image/')) { return new Response(json_encode(['error' => 'media sent is not an image'])); }

        if ($mediaFile instanceof Media) {
            $media = $mediaFile;
        } else {
            $media = new Media();
            $media->setMediaFile($mediaFile);

            $duplicate = $this->em->getRepository(Media::class)->findOneBy(['hash' => $media->getHash()]);
            if (! $duplicate instanceof Media) {
                $this->em->persist($media);
                $this->em->flush();
            } else {
                $media = $duplicate;
            }
        }

        $url = $this->imageManager->isImage($media) ? $this->imageManager->getBrowserPath($media->getFileName())
            : '/'.$publicMediaDir.'/'.$media->getFileName();

        return new Response(json_encode([
            'success' => 1,
            'file' => $this->exportMedia($media, $url),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function exportMedia(Media $media, string $url): array
    {
        $properties = Entity::getProperties($media);

        $data = [];
        foreach ($properties as $property) {
            if (\in_array($property, ['hash', 'storeIn'], true)) { // properties to ignore for export  'id',
                continue;
            }

            $getter = 'get'.ucfirst($property);
            $data[$property] = $media->$getter(); // @phpstan-ignore-line
        }

        $data['url'] = $url;

        return $data;
    }

    private function getMediaFrom(string $content): Media|UploadedFile
    {
        $content = json_decode($content, true);

        if (! \is_array($content) || (! isset($content['url']) && ! isset($content['id']))) {
            throw new LogicException('URL not sent by editor.js ?!');
        }

        if (isset($content['id'])) {
            if (! \is_scalar($content['id'])) {
                throw new InvalidArgumentException('Content ID must be a scalar value');
            }

            return $this->getMediaFileFromId((string) $content['id']);
        }

        if (! \is_string($content['url'])) {
            throw new InvalidArgumentException('Content URL must be a string');
        }

        if (str_starts_with($content['url'], '/'.$this->publicMediaDir.'/default/')) {
            return $this->getMediaFromMedia(substr($content['url'], \strlen('/'.$this->publicMediaDir.'/default/')));
        }

        return $this->getMediaFileFromUrl($content['url']);
    }

    private function getMediaFromMedia(string $media): Media
    {
        if (($media = $this->em->getRepository(Media::class)->findOneBy(['fileName' => $media])) === null) {
            throw new LogicException('Media not found');
        }

        return $media;
    }

    /**
     * Store in tmp system dir a cache from dist URL.
     */
    private function getMediaFileFromUrl(string $url): UploadedFile
    {
        if (0 === preg_match('#/([^/]*)$#', $url, $matches)) {
            throw new LogicException("URL doesn't contain file name");
        }

        /** @var array<int, string> $matches */
        $fileContent = file_get_contents($url);

        $originalName = $matches[1];
        $filename = md5($originalName);
        $filePath = sys_get_temp_dir().'/'.$filename;
        if (0 === file_put_contents($filePath, $fileContent)) {
            throw new LogicException('Storing in tmp folder failed');
        }

        $mimeType = mime_content_type($filePath);

        return new UploadedFile($filePath, $originalName, $mimeType, null, true);
    }

    private function getMediaFileFromId(string $id): Media
    {
        $id = (int) $id;
        if (($media = $this->em->getRepository(Media::class)->findOneBy(['id' => $id])) === null) {
            throw new LogicException('Media not found');
        }

        return $media;
    }
}
