<?php

namespace Pushword\Core\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Exception;
use LogicException;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ThumbnailGenerator;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Service\PdfOptimizer;
use Pushword\Core\Utils\FlashBag;
use Pushword\Core\Utils\MediaRenamer;

use function Safe\preg_replace;
use function Safe\sha1_file;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
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
final readonly class MediaListener
{
    private MediaRenamer $renamer;

    public function __construct(
        private string $projectDir,
        private EntityManagerInterface $em,
        private MediaStorageAdapter $mediaStorage,
        private ThumbnailGenerator $thumbnailGenerator,
        private ImageCacheManager $imageCacheManager,
        private PdfOptimizer $pdfOptimizer,
        private RequestStack $requestStack,
        private RouterInterface $router,
        private TranslatorInterface $translator,
        private MediaRepository $mediaRepo,
    ) {
        $this->renamer = new MediaRenamer();
    }

    private function getMediaFromEvent(Event $event): Media
    {
        $media = $event->getObject();
        if (! $media instanceof Media) {
            throw new LogicException();
        }

        return $media;
    }

    /**
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

        if ('' === $media->getFileName()) {
            $media->setFileName($media->getMediaFromFilename($media->getSlug()));
        }
    }

    /**
     * - Update storeIn
     * - generate quick thumb for admin preview
     * - run full cache generation in background
     * - run PDF optimization in background
     * - create symlink for non-images.
     */
    public function onVichUploaderPostUpload(Event $event): void
    {
        $media = $this->getMediaFromEvent($event);

        if ($media->isImage()) {
            $this->imageCacheManager->remove($media);

            // Generate quick thumb for fast upload experience
            $this->thumbnailGenerator->generateQuickThumb($media);

            // Run full cache generation in background
            $this->thumbnailGenerator->runBackgroundCacheGeneration($media->getFileName());

            return;
        }

        // For non-images, create symlink from public/media to media for direct access
        $this->imageCacheManager->ensurePublicSymlink($media);

        if ('application/pdf' === $media->getMimeType()) {
            // Run PDF optimization in background (compress + linearize)
            $this->pdfOptimizer->runBackgroundOptimization($media->getFileName());
        }
    }

    public function postLoad(Media $media): void
    {
        $media->setProjectDir($this->projectDir);
    }

    public function prePersist(Media $media): void
    {
        $media->initTimestampableProperties();

        // If mediaFile is set (upload), setHash() will use it
        // Otherwise, calculate from storage
        if (null === $media->getMediaFile() && '' !== $media->getFileName()) {
            $localPath = $this->mediaStorage->getLocalPath($media->getFileName());
            $media->setHash(sha1_file($localPath, true));
        } else {
            $media->setHash();
        }
    }

    public function postPersist(Media $media): void
    {
        $duplicate = $this->mediaRepo->findDuplicate($media);

        if (null !== $duplicate) {
            $this->alert('warning', 'mediaDuplicateWarning', [
                '%deleteMediaUrl%' => $this->router->generate('admin_media_delete', ['entityId' => $media->id]),
                '%sameMediaEditUrl%' => $this->router->generate('admin_media_edit', ['entityId' => $duplicate->id]),
                '%name%' => $duplicate->getAlt(),
            ]);
        }
    }

    /**
     * renameMediaOnMediaNameUpdate.
     */
    public function preUpdate(Media $media, PreUpdateEventArgs $preUpdateEventArgs): void
    {
        if ($preUpdateEventArgs->hasChangedField('fileName')) {
            $this->renameIfIdentifiersAreToken($media);

            if ($this->mediaStorage->fileExists($media->getFileName())) {
                $media->setFileName($media->getFileNameBeforeUpdate());

                throw new Exception('Impossible to rename '.$media->getFileNameBeforeUpdate().' in '.$media->getFileName().'. File ever exist');
            }

            if ('' === $media->getFileNameBeforeUpdate()) {
                // dd($media->getFileNameBeforeUpdate());
                throw new LogicException();
            }

            $this->mediaStorage->move(
                $media->getFileNameBeforeUpdate(),
                $media->getFileName()
            );
            $this->imageCacheManager->remove($media->getFileNameBeforeUpdate());
            $media->setFileNameBeforeUpdate('');

            // Generate only quick thumb for fast response, full cache in background
            $this->thumbnailGenerator->generateQuickThumb($media);
            $this->thumbnailGenerator->runBackgroundCacheGeneration($media->getFileName());
        }

        // Recalculate hash if it was reset or file content changed
        if ($preUpdateEventArgs->hasChangedField('hash')) {
            $localPath = $this->mediaStorage->getLocalPath($media->getFileName());
            $newHash = sha1_file($localPath, true);
            $media->setHash($newHash);
            // Must use setNewValue for Doctrine to pick up the change in preUpdate
            $preUpdateEventArgs->setNewValue('hash', $newHash);
        }
    }

    public function preRemove(Media $media): void
    {
        if ($this->mediaStorage->fileExists($media->getFileName())) {
            $this->mediaStorage->delete($media->getFileName());
        }

        $this->imageCacheManager->remove($media);
    }

    private function setNameIfEmpty(Media $media): void
    {
        if ('' !== $media->getAlt(true)) {
            return;
        }

        if ('' !== $media->getSlug()) {
            $media->setAlt($media->getSlug());
        }

        /** @var string */
        $name = preg_replace('/\\.[^.\\s]{3,4}$/', '', $media->getMediaFileName());
        $media->setAlt($name);
    }

    private function getMediaString(Media $media): string
    {
        if (($return = $media->getFileName()) !== '') {
            return $return;
        }

        $extension = ($mediaFile = $media->getMediaFile()) instanceof UploadedFile
            ? (string) $mediaFile->guessExtension() : '';

        return $media->getAlt().('' !== $extension ? '.'.$extension : '');
    }

    private function identifiersAreToken(Media $media): bool
    {
        $sameName = $this->em->getRepository($media::class)->findOneBy(['alt' => $media->getAlt()]);
        if (null !== $sameName && $media->id !== $sameName->id) {
            return true;
        }

        $mediaString = $this->getMediaString($media);

        // Check if filename is used by another media (current or in history)
        $conflictingMedia = $this->mediaRepo->isFileNameUsed($mediaString, $media->id);

        return null !== $conflictingMedia;
    }

    private function renameIfIdentifiersAreToken(Media $media): void
    {
        if (! $this->identifiersAreToken($media)) {
            $this->renamer->reset();

            return;
        }

        $this->renamer->rename($media);

        if (10 === $this->renamer->getIteration()) {
            throw new Exception('Too much file with similar name `'.$media->getFileName().'`');
        }

        if (1 === $this->renamer->getIteration()) {
            $this->alert('success', 'mediaNameWasChanged');
        }

        $this->renameIfIdentifiersAreToken($media);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function alert(string $type, string $message, array $parameters = []): void
    {
        if (null !== ($flashBag = FlashBag::get($this->requestStack->getCurrentRequest()))) {
            $flashBag->add($type, $this->translator->trans($message, $parameters));
        }

        // else log TODO
    }
}
