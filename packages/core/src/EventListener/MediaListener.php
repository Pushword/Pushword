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
use Pushword\Core\Utils\F;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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

    private RequestStack $requestStack;

    private TranslatorInterface $translator;

    /** @psalm-suppress  UndefinedInterfaceMethod */
    public function __construct(
        string $projectDir,
        EntityManagerInterface $entityManager,
        FileSystem $filesystem,
        ImageManager $imageManager,
        RequestStack $requestStack,
        TranslatorInterface $translator
    ) {
        $this->projectDir = $projectDir;
        $this->em = $entityManager;
        $this->filesystem = $filesystem;
        $this->imageManager = $imageManager;
        $this->requestStack = $requestStack;

        $this->translator = $translator;
    }

    /**
     * Check if name exist.
     */
    public function onVichUploaderPreUpload(Event $event): void
    {
        $media = $event->getObject();
        if (! $media instanceof MediaInterface) {
            throw new LogicException();
        }

        $this->beforeToImportAndStore($media);
    }

    public function beforeToImportAndStore(MediaInterface $media): void
    {
        $this->setNameIfEmpty($media);
        $this->renameIfMediaExists($media);
    }

    public function postLoad(MediaInterface $media): void
    {
        $media->setProjectDir($this->projectDir);
    }

    /**
     * renameMediaOnMediaNameUpdate.
     */
    public function preUpdate(MediaInterface $media, PreUpdateEventArgs $preUpdateEventArgs): void
    {
        if ($preUpdateEventArgs->hasChangedField('media')) {
            $this->renameIfMediaExists($media);

            if (file_exists($media->getPath())) {
                $media->setMedia($media->getMediaBeforeUpdate());

                throw new Exception('Impossible to rename '.$media->getMediaBeforeUpdate().' in '.$media->getMedia().'. File ever exist');
            }

            if (! \is_string($media->getMediaBeforeUpdate())) {
                throw new LogicException();
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

    public function preRemove(MediaInterface $media): void
    {
        if (str_starts_with((string) $media->getStoreIn(), $this->projectDir)) {
            $this->filesystem->remove($media->getStoreIn().'/'.$media->getMedia());
        }

        $this->imageManager->remove($media);
    }

    private function setNameIfEmpty(MediaInterface $media): void
    {
        if ('' !== $media->getName(true)) {
            return;
        }

        $media->setName(\strval(\Safe\preg_replace('/\\.[^.\\s]{3,4}$/', '', $media->getMediaFileName())));
    }

    private function getMediaString(MediaInterface $media): string
    {
        if (($return = $media->getMedia()) !== null) {
            return $return;
        }

        $extension = $media->getMediaFile() instanceof UploadedFile
            ? (string) $media->getMediaFile()->guessExtension() : '';

        return $media->getName().('' !== $extension ? '.'.$extension : '');
    }

    private function renameIfMediaExists(MediaInterface $media): void
    {
        $mediaString = $this->getMediaString($media);

        $sameName = $this->em->getRepository(\get_class($media))->findOneBy(['name' => $media->getName()]);
        $sameMedia = $this->em->getRepository(\get_class($media))->findOneBy(['media' => $mediaString]);

        if (! (null !== $sameName && $media->getId() !== $sameName->getId())
            && ! (null !== $sameMedia && $media->getId() !== $sameMedia->getId())
        ) {
            return;
        }

        $newName = (1 === $this->iterate ? $media->getName() : F::preg_replace_str('/ \([0-9]+\)$/', '', $media->getName()))
            .' ('.$this->iterate.')';
        $media->setName($newName);
        $media->setMedia(null);
        $media->setSlug($media->getName());

        if (1 === $this->iterate && null !== ($flashBag = $this->getFlashBag())) {
            $flashBag->add('success', $this->translator->trans('media.name_was_changed')); // todo translate
        }

        ++$this->iterate;
        $this->renameIfMediaExists($media);
    }

    private function getFlashBag(): ?FlashBagInterface
    {
        if (null !== $this->flashBag) {
            return $this->flashBag;
        }

        return null !== ($request = $this->requestStack->getCurrentRequest()) && method_exists($request->getSession(), 'getFlashBag') ?
                $this->flashBag = $request->getSession()->getFlashBag() : null;
    }

    /**
     * Update storeIn.
     */
    public function onVichUploaderPostUpload(Event $event): void
    {
        /** @var MediaInterface */
        $media = $event->getObject();
        $propertyMapping = $event->getMapping();

        $absoluteDir = $propertyMapping->getUploadDestination().'/'.$propertyMapping->getUploadDir($media);
        $media->setProjectDir($this->projectDir)->setStoreIn($absoluteDir);

        if ($this->imageManager->isImage($media)) {
            $this->imageManager->remove($media);
            $this->imageManager->generateCache($media);
            $image = $this->imageManager->getLastThumb();
            $this->updateMainColor($media, $image);
            //exec('cd ../ && php bin/console pushword:image:cache '.$media->getMedia().' > /dev/null 2>/dev/null &');
        }
    }

    private function updateMainColor(MediaInterface $media, ?Image $image = null): void
    {
        if (! $image instanceof \Intervention\Image\Image) {
            return;
        }

        $imageForPalette = clone $image;
        $color = $imageForPalette->limitColors(1)->pickColor(0, 0, 'hex');
        $imageForPalette->destroy();

        $media->setMainColor(\strval($color));
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
