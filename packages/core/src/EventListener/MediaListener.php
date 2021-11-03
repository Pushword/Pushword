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
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Service\ImageManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Event\Event;

class MediaListener
{
    private string $projectDir;

    private int $iterate = 1;

    private EntityManagerInterface $em;

    private FileSystem $filesystem;

    private ImageManager $imageManager;

    private ?FlashBagInterface $flashBag = null;

    private TranslatorInterface $translator;

    /** @psalm-suppress  UndefinedInterfaceMethod */
    public function __construct(
        string $projectDir,
        EntityManagerInterface $em,
        FileSystem $filesystem,
        ImageManager $imageManager,
        RequestStack $requestStack,
        TranslatorInterface $translator
    ) {
        $this->projectDir = $projectDir;
        $this->em = $em;
        $this->filesystem = $filesystem;
        $this->imageManager = $imageManager;
        if ($requestStack->getCurrentRequest()) {
            $this->flashBag = $requestStack->getSession()->getFlashBag();
        }
        $this->translator = $translator;
    }

    /**
     * Check if name exist.
     */
    public function onVichUploaderPreUpload(Event $event)
    {
        $media = $event->getObject();
        $this->beforeToImportAndStore($media);
    }

    public function beforeToImportAndStore(MediaInterface $media)
    {
        $this->setNameIfEmpty($media);
        $this->renameIfMediaExists($media);
    }

    public function postLoad(MediaInterface $media)
    {
        $media->setProjectDir($this->projectDir);
    }

    /**
     * renameMediaOnMediaNameUpdate.
     */
    public function preUpdate(MediaInterface $media, PreUpdateEventArgs $event)
    {
        if ($event->hasChangedField('media')) {
            $this->renameIfMediaExists($media);

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

        $this->imageManager->remove($media);
    }

    private function setNameIfEmpty(MediaInterface $media): void
    {
        if (! empty($media->getName())) {
            return;
        }

        $media->setName(preg_replace('/\\.[^.\\s]{3,4}$/', '', $media->getMediaFileName()));
    }

    private function getMediaString(MediaInterface $media): string
    {
        if ($media->getMedia()) {
            return $media->getMedia();
        }
        $extension = $media->getMediaFile()->guessExtension();

        return $media->getName().($extension ? '.'.$extension : '');
    }

    private function renameIfMediaExists(MediaInterface $media): void
    {
        $mediaString = $this->getMediaString($media);

        $sameName = $this->em->getRepository(\get_class($media))->findOneBy(['name' => $media->getName()]);
        $sameMedia = $this->em->getRepository(\get_class($media))->findOneBy(['media' => $mediaString]);

        if (! ($sameName && $media->getId() != $sameName->getId())
            && ! ($sameMedia && $media->getId() != $sameMedia->getId())
        ) {
            return;
        }

        $newName = (1 === $this->iterate ? $media->getName() : preg_replace('/ \([0-9]+\)$/', '', $media->getName()))
            .' ('.$this->iterate.')';
        $media->setName($newName);
        $media->setMedia(null);
        $media->setSlug($media->getName());

        if (1 === $this->iterate && null !== $this->flashBag) {
            $this->flashBag->add('success', $this->translator->trans('media.name_was_changed')); // todo translate
        }
        ++$this->iterate;
        $this->renameIfMediaExists($media);
    }

    /**
     * Update storeIn.
     */
    public function onVichUploaderPostUpload(Event $event)
    {
        $media = $event->getObject();
        $mapping = $event->getMapping();

        $absoluteDir = $mapping->getUploadDestination().'/'.$mapping->getUploadDir($media);
        $media->setProjectDir($this->projectDir)->setStoreIn($absoluteDir);

        if ($this->imageManager->isImage($media)) {
            $this->imageManager->remove($media);
            $this->imageManager->generateCache($media);
            $thumb = $this->imageManager->getLastThumb();
            $this->updateMainColor($media, $thumb ?: null);
            //exec('cd ../ && php bin/console pushword:image:cache '.$media->getMedia().' > /dev/null 2>/dev/null &');
        }
    }

    private function updateMainColor(MediaInterface $media, ?Image $image = null): void
    {
        if (null === $image) {
            return;
        }

        $imageForPalette = clone $image;
        $color = $imageForPalette->limitColors(1)->pickColor(0, 0, 'hex');
        $imageForPalette->destroy();

        $media->setMainColor($color);
    }

    /*  Palette was good, but not ready for PHP 8
    private function updatePaletteColor(MediaInterface $media, ?Image $image = null): void
    {
        $palette = $image->getDriver() instanceof GdDriver
            ? Palette::fromGD($image->getCore(), Color::fromHexToInt('#FFFFFF'))
            : Palette::fromFilename($this->imageManager->getFilterPath($media, 'xs'), Color::fromHexToInt('#FFFFFF'));

        $extractor = new ColorExtractor($palette);
        $colors = $extractor->extract();
        $media->setMainColor(Color::fromIntToHex($colors[0]));
    }*/
}
