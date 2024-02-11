<?php

namespace Pushword\Core\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Intervention\Image\Image;
use Intervention\Image\Interfaces\ImageInterface;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Service\ImageManager;
use Pushword\Core\Utils\MediaRenamer;

use function Safe\preg_replace;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Event\Event;

#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'prePersist'])]
#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'preUpdate'])]
#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'preRemove'])]
#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'postLoad'])]
#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'postPersist'])]
#[AutoconfigureTag('kernel.event_listener', ['event' => 'vich_uploader.post_upload'])]
#[AutoconfigureTag('kernel.event_listener', ['event' => 'vich_uploader.pre_upload'])]
final class MediaListener
{
    private ?FlashBagInterface $flashBag = null;

    private readonly MediaRenamer $renamer;

    /** @psalm-suppress  UndefinedInterfaceMethod */
    public function __construct(
        private readonly string $projectDir,
        private readonly EntityManagerInterface $em,
        private readonly Filesystem $filesystem,
        private readonly ImageManager $imageManager,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator
    ) {
        $this->renamer = new MediaRenamer();
    }

    private function getMediaFromEvent(Event $event): MediaInterface
    {
        $media = $event->getObject();
        if (! $media instanceof MediaInterface) {
            throw new \LogicException();
        }

        return $media;
    }

    /**
     * @psalm-suppress InternalMethod
     *
     * - warned if file ever exist
     * - Set Name if not setted (from filename)
     * - Check if name exist.
     */
    public function onVichUploaderPreUpload(Event $event): void
    {
        $media = $this->getMediaFromEvent($event);
        $media->resetHash();
        $media->setHash();

        $propertyMapping = $event->getMapping();

        $absoluteDir = $propertyMapping->getUploadDestination().'/'.($propertyMapping->getUploadDir($media) ?? '');
        $media->setProjectDir($this->projectDir)->setStoreIn($absoluteDir);

        $this->setNameIfEmpty($media);
        $this->renameIfIdentifiersAreToken($media);

        if ('' === $media->getMedia()) {
            $media->setMedia($media->getMediaFromFilename($media->getSlug()));
        }
    }

    /**
     * @psalm-suppress InternalMethod
     *
     * - Update storeIn
     * - generate image cache
     * - updateMainColor
     */
    public function onVichUploaderPostUpload(Event $event): void
    {
        $media = $this->getMediaFromEvent($event);

        if ($this->imageManager->isImage($media)) {
            $this->imageManager->remove($media);
            $this->imageManager->generateCache($media);
            $image = $this->imageManager->getLastThumb();
            if (null === $image) {
                return;
            }

            $this->updateMainColor($media, $image);
            // exec('cd ../ && php bin/console pushword:image:cache '.$media->getMedia().' > /dev/null 2>/dev/null &');
        }
    }

    public function postLoad(MediaInterface $media): void
    {
        $media->setProjectDir($this->projectDir);
    }

    public function prePersist(MediaInterface $media): void
    {
        $media->setHash();
    }

    public function postPersist(MediaInterface $media): void
    {
        $duplicate = $this->em->getRepository($media::class) // @phpstan-ignore-line
            ->findDuplicate($media);

        if (null !== $duplicate) {
            $this->alert('warning', 'media.duplicate_warning', [
                '%deleteMediaUrl%' => $this->router->generate('admin_app_media_delete', ['id' => $media->getId()]),
                '%sameMediaEditUrl%' => $this->router->generate('admin_app_media_edit', ['id' => $duplicate->getId()]),
                '%name%' => $duplicate->getName(),
            ]);
        }
    }

    /**
     * renameMediaOnMediaNameUpdate.
     */
    public function preUpdate(MediaInterface $media, PreUpdateEventArgs $preUpdateEventArgs): void
    {
        if ($preUpdateEventArgs->hasChangedField('media')) {
            $this->renameIfIdentifiersAreToken($media);

            if (file_exists($media->getPath())) {
                $media->setMedia($media->getMediaBeforeUpdate());

                throw new \Exception('Impossible to rename '.$media->getMediaBeforeUpdate().' in '.$media->getMedia().'. File ever exist');
            }

            if ('' === $media->getMediaBeforeUpdate()) {
                // dd($media->getMediaBeforeUpdate());
                throw new \LogicException();
            }

            $this->filesystem->rename(
                $media->getStoreIn().'/'.$media->getMediaBeforeUpdate(),
                $media->getStoreIn().'/'.$media->getMedia()
            );
            $this->imageManager->remove($media->getMediaBeforeUpdate());
            $media->setMediaBeforeUpdate('');

            $this->imageManager->generateCache($media);
            // exec('cd ../ && php bin/console pushword:image:cache '.$media->getMedia().' > /dev/null 2>/dev/null &');

            $media->setHash();
        }
    }

    public function preRemove(MediaInterface $media): void
    {
        if (str_starts_with($media->getStoreIn(), $this->projectDir)) {
            $this->filesystem->remove($media->getStoreIn().'/'.$media->getMedia());
        }

        $this->imageManager->remove($media);
    }

    private function setNameIfEmpty(MediaInterface $media): void
    {
        if ('' !== $media->getName(true)) {
            return;
        }

        if ('' !== $media->getSlug()) {
            $media->setName($media->getSlug());
        }

        /** @var string */
        $name = preg_replace('/\\.[^.\\s]{3,4}$/', '', $media->getMediaFileName());
        $media->setName($name);
    }

    private function getMediaString(MediaInterface $media): string
    {
        if (($return = $media->getMedia()) !== '') {
            return $return;
        }

        $extension = ($mediaFile = $media->getMediaFile()) instanceof UploadedFile
            ? (string) $mediaFile->guessExtension() : '';

        return $media->getName().('' !== $extension ? '.'.$extension : '');
    }

    private function identifiersAreToken(MediaInterface $media): bool
    {
        /*
        if (substr($media->getPath(), -1) !== '/' // debug why path is not always
            && file_exists($media->getPath())) {
            //dump('file exist: '.$media->getPath());
            return true;
        }*/

        $sameName = $this->em->getRepository($media::class)->findOneBy(['name' => $media->getName()]);
        if (null !== $sameName && $media->getId() !== $sameName->getId()) {
            // dump('sameName '.$sameName->getId());
            return true;
        }

        $mediaString = $this->getMediaString($media);
        $sameMedia = $this->em->getRepository($media::class)->findOneBy(['media' => $mediaString]);

        // dump('sameMedia '.$sameMedia->getId());
        return null !== $sameMedia && $media->getId() !== $sameMedia->getId();
    }

    private function renameIfIdentifiersAreToken(MediaInterface $media): void
    {
        if (! $this->identifiersAreToken($media)) {
            $this->renamer->reset();

            return;
        }

        $this->renamer->rename($media);

        if (10 === $this->renamer->getIteration()) {
            throw new \Exception('Too much file with similar name `'.$media->getMedia().'`');
        }

        if (1 === $this->renamer->getIteration()) {
            $this->alert('success', 'media.name_was_changed');
        }

        $this->renameIfIdentifiersAreToken($media);
    }

    private function alert(string $type, string $message, array $parameters = []): void // @phpstan-ignore-line
    {
        if (null !== ($flashBag = $this->getFlashBag())) {
            $flashBag->add($type, $this->translator->trans($message, $parameters));
        }

        // else log TODO
    }

    private function getFlashBag(): ?FlashBagInterface
    {
        if (null !== $this->flashBag) {
            return $this->flashBag;
        }

        return null !== ($request = $this->requestStack->getCurrentRequest()) && method_exists($request->getSession(), 'getFlashBag') ?
                $this->flashBag = $request->getSession()->getFlashBag() : null;
    }

    private function updateMainColor(MediaInterface $media, ?ImageInterface $image = null): void
    {
        if (! $image instanceof Image) {
            return;
        }

        $imageForPalette = clone $image;
        $color = $imageForPalette->pickColor(0, 0)->toHex('#'); // ->reduceColors(1)
        // previously doing this $color = $imageForPalette->limitColors(1)->pickColor(0, 0, 'hex');

        $media->setMainColor($color);
    }
}
