<?php

namespace Pushword\Core\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Exception;
use Intervention\Image\Gd\Driver as GdDriver;
use Intervention\Image\Image;
use League\ColorExtractor\Color;
use League\ColorExtractor\ColorExtractor;
use League\ColorExtractor\Palette;
use LogicException;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Service\ImageManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Event\Event;

class MediaListener
{
    protected string $projectDir;
    protected int $iterate = 1;
    protected EntityManagerInterface $em;
    protected EventDispatcherInterface $eventDispatcher;
    protected FileSystem $filesystem;
    protected ImageManager $imageManager;

    public function __construct(
        string $projectDir,
        EntityManagerInterface $em,
        EventDispatcherInterface $eventDispatcher,
        FileSystem $filesystem,
        ImageManager $imageManager
    ) {
        $this->projectDir = $projectDir;
        $this->em = $em;
        $this->eventDispatcher = $eventDispatcher;
        $this->filesystem = $filesystem;
        $this->imageManager = $imageManager;
    }

    /**
     * Check if name exist.
     */
    public function onVichUploaderPreUpload(Event $event)
    {
        $media = $event->getObject();

        $this->checkIfThereIsAName($media);
        $this->checkIfNameEverExistInDatabase($media);
    }

    /**
     * renameMediaOnMediaNameUpdate.
     */
    public function preUpdate(MediaInterface $media, PreUpdateEventArgs $event)
    {
        if ($event->hasChangedField('media')) {
            $this->checkIfNameEverExistInDatabase($media);

            if (file_exists($media->getPath())) {
                $media->setMedia($media->getMediaBeforeUpdate());

                throw new Exception('Impossible to rename '.$media->getMediaBeforeUpdate().' in '.$media->getMedia().'. File ever exist');
            }

            $this->filesystem->rename(
                $media->getStoreIn().'/'.$media->getMediaBeforeUpdate(),
                $media->getStoreIn().'/'.$media->getMedia()
            );

            $this->imageManager->remove($media->getMediaBeforeUpdate());

            $this->imageManager->generateCache($media);
            //exec('cd ../ && php bin/console pushword:image:cache '.$media->getMedia().' > /dev/null 2>/dev/null &');
        }
    }

    public function preRemove(MediaInterface $media)
    {
        if (0 === strpos($media->getStoreIn(), $this->projectDir)) {
            $this->filesystem->remove($media->getStoreIn().'/'.$media->getMedia());
        }

        $this->imageManager->remove($media->getMediaBeforeUpdate());
    }

    /**
     * Si l'utilisateur ne propose pas de nom pour l'image,
     * on récupère celui d'origine duquel on enlève son extension.
     *
     * @psalm-suppress  UndefinedMethod
     */
    protected function checkIfThereIsAName(MediaInterface $media): void
    {
        if (empty($media->getName())) {
            if (! $media->getMediaFile() instanceof UploadedFile) {
                throw new LogicException('You must set a name if you are not using UploadedFile');
            }

            $name = $media->getMediaFile()->getClientOriginalName();

            $media->setName(preg_replace('/\\.[^.\\s]{3,4}$/', '', $name));
        }
    }

    protected function checkIfNameEverExistInDatabase(MediaInterface $media): void
    {
        $same = $this->em->getRepository(\get_class($media))->findOneBy(['name' => $media->getName()]);
        $sameMedia = $this->em->getRepository(\get_class($media))->findOneBy(['media' => $media->getName()]);
        if ($same && (null == $media->getId() || $media->getId() != $same->getId())) {
            $media->setName(preg_replace('/\([0-9]+\)$/', '', $media->getName()).' ('.$this->iterate.')');
            ++$this->iterate;
            $this->checkIfNameEverExistInDatabase($media);
        } elseif ($sameMedia && (null == $media->getId() || $media->getId() != $sameMedia->getId())) {
            $media->setSlug(preg_replace('/-[0-9]+\\.[^.\\s]{3,4}$/', '', $media->getSlug()).'-'.$this->iterate);
            ++$this->iterate;
            $this->checkIfNameEverExistInDatabase($media);
        }
    }

    /**
     * Update storeIn.
     */
    public function onVichUploaderPostUpload(Event $event)
    {
        $media = $event->getObject();
        $mapping = $event->getMapping();

        $absoluteDir = $mapping->getUploadDestination().'/'.$mapping->getUploadDir($media);
        $media->setStoreIn($absoluteDir);

        if (false !== strpos($media->getMimeType(), 'image/')) {
            $this->imageManager->remove($media);
            $this->imageManager->generateCache($media);
            $thumb = $this->imageManager->getLastThumb();
            $this->updatePaletteColor($media, $thumb ?: null);
            //exec('cd ../ && php bin/console pushword:image:cache '.$media->getMedia().' > /dev/null 2>/dev/null &');
        }
    }

    private function updatePaletteColor(MediaInterface $media, ?Image $image = null): void
    {
        $palette = $image->getDriver() instanceof GdDriver
            ? Palette::fromGD($image->getCore(), Color::fromHexToInt('#FFFFFF'))
            : Palette::fromFilename($this->imageManager->getFilterPath($media, 'xs'), Color::fromHexToInt('#FFFFFF'));
        $extractor = new ColorExtractor($palette);
        $colors = $extractor->extract();
        $media->setMainColor(Color::fromIntToHex($colors[0]));
    }
}
