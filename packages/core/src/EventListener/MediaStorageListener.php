<?php

namespace Pushword\Core\EventListener;

use Doctrine\ORM\Event\PreUpdateEventArgs;
use Exception;
use LogicException;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ThumbnailGenerator;
use Pushword\Core\Service\MediaConflictResolver;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Service\PdfOptimizer;

use function Safe\sha1_file;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Vich\UploaderBundle\Event\Event;

#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'postLoad'])]
#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'preUpdate'])]
#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'preRemove'])]
#[AutoconfigureTag('kernel.event_listener', ['event' => 'vich_uploader.post_upload'])]
final readonly class MediaStorageListener
{
    public function __construct(
        private string $projectDir,
        private MediaStorageAdapter $mediaStorage,
        private ThumbnailGenerator $thumbnailGenerator,
        private ImageCacheManager $imageCacheManager,
        private PdfOptimizer $pdfOptimizer,
        private MediaConflictResolver $conflictResolver,
    ) {
    }

    public function postLoad(Media $media): void
    {
        $media->setProjectDir($this->projectDir);
    }

    public function preUpdate(Media $media, PreUpdateEventArgs $preUpdateEventArgs): void
    {
        if ($preUpdateEventArgs->hasChangedField('fileName')) {
            $this->conflictResolver->resolveConflicts($media);

            if ('' === $media->getFileNameBeforeUpdate()) {
                throw new LogicException();
            }

            $oldFileName = $media->getFileNameBeforeUpdate();
            $newFileName = $media->getFileName();
            $sourceExists = $this->mediaStorage->fileExists($oldFileName);
            $destExists = $this->mediaStorage->fileExists($newFileName);

            if ($destExists && $sourceExists) {
                throw new Exception('Impossible to rename '.$oldFileName.' to '.$newFileName.'. File already exists');
            }

            if (! $sourceExists && ! $destExists) {
                throw new Exception('Cannot rename '.$oldFileName.': file not found on disk');
            }

            if ($sourceExists) {
                $this->mediaStorage->move($oldFileName, $newFileName);
            }

            // !$sourceExists && $destExists: file was already renamed on disk
            // by a previous run that crashed before updating the DB — skip move

            $this->imageCacheManager->remove($oldFileName);
            $media->setFileNameBeforeUpdate('');

            $this->thumbnailGenerator->generateQuickThumb($media);
            $this->thumbnailGenerator->runBackgroundCacheGeneration($newFileName);
        }

        if ($preUpdateEventArgs->hasChangedField('hash')) {
            $localPath = $this->mediaStorage->getLocalPath($media->getFileName());
            $newHash = sha1_file($localPath, true);
            $media->setHash($newHash);
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

    public function onVichUploaderPostUpload(Event $event): void
    {
        $media = $this->getMediaFromEvent($event);

        if ($media->isImage()) {
            $this->imageCacheManager->remove($media);
            $this->thumbnailGenerator->generateQuickThumb($media);
            $this->thumbnailGenerator->runBackgroundCacheGeneration($media->getFileName());

            return;
        }

        $this->imageCacheManager->ensurePublicSymlink($media);

        if ('application/pdf' === $media->getMimeType()) {
            $this->pdfOptimizer->runBackgroundOptimization($media->getFileName());
        }
    }

    private function getMediaFromEvent(Event $event): Media
    {
        $media = $event->getObject();
        if (! $media instanceof Media) {
            throw new LogicException();
        }

        return $media;
    }
}
