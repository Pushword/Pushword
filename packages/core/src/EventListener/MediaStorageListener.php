<?php

namespace Pushword\Core\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Exception;
use LogicException;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheGenerator;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Service\MediaConflictResolver;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Service\PdfOptimizer;
use Pushword\Core\Utils\MediaFileName;

use function Safe\sha1_file;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;
use Vich\UploaderBundle\Event\Event;

#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'postLoad'])]
#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'preUpdate'])]
#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'preRemove'])]
#[AutoconfigureTag('kernel.event_listener', ['event' => 'vich_uploader.post_upload'])]
#[AsDoctrineListener(event: Events::postFlush)]
final class MediaStorageListener implements ResetInterface
{
    /**
     * Renames whose row is written but whose file has not moved yet.
     *
     * @var list<array{media: Media, oldFileName: string, newFileName: string, moveFrom: string|null}>
     */
    private array $pendingRenames = [];

    public function __construct(
        private readonly string $projectDir,
        private readonly MediaStorageAdapter $mediaStorage,
        private readonly ImageCacheGenerator $imageCacheGenerator,
        private readonly ImageCacheManager $imageCacheManager,
        private readonly PdfOptimizer $pdfOptimizer,
        private readonly MediaConflictResolver $conflictResolver,
    ) {
    }

    public function postLoad(Media $media): void
    {
        $media->setProjectDir($this->projectDir);
    }

    /**
     * Only decides here; the filesystem work happens in {@see self::postFlush()}. What a
     * rename cannot survive is being half-done, so the checks that can refuse it — dest
     * taken, source gone — stay on this side of the commit, where throwing still rolls
     * the row back.
     */
    public function preUpdate(Media $media, PreUpdateEventArgs $preUpdateEventArgs): void
    {
        if ($preUpdateEventArgs->hasChangedField('fileName')) {
            $this->planRename($media);
        }

        if ($preUpdateEventArgs->hasChangedField('hash')) {
            $newHash = sha1_file($this->mediaStorage->getLocalPath($this->fileNameOnDisk($media)), true);
            $media->setHash($newHash);
            $preUpdateEventArgs->setNewValue('hash', $newHash);
        }
    }

    private function planRename(Media $media): void
    {
        $this->conflictResolver->resolveConflicts($media);

        if ('' === $media->fileNameBeforeUpdate) {
            throw new LogicException();
        }

        $oldFileName = $media->fileNameBeforeUpdate;

        $this->pendingRenames[] = [
            'media' => $media,
            'oldFileName' => $oldFileName,
            'newFileName' => $media->getFileName(),
            'moveFrom' => $this->resolveMoveSource($oldFileName, $media->getFileName()),
        ];

        $media->setFileNameBeforeUpdate('');
    }

    /**
     * The filesystem side of a rename, run once the row is committed.
     *
     * It used to run in preUpdate, inside the flush: the file was moved, the image work
     * that follows overran the memory limit, and the rolled-back transaction left the DB
     * naming a file that no longer existed — every reference to it 404ing until someone
     * noticed (production, 2026-08-16). Past the commit, nothing here can contradict the
     * row anymore; the one step that still could, the move, puts the old name back.
     *
     * Nothing here flushes — the revert goes to the connection directly — so this cannot
     * re-enter.
     */
    public function postFlush(PostFlushEventArgs $postFlushEventArgs): void
    {
        if ([] === $this->pendingRenames) {
            return;
        }

        $pending = $this->pendingRenames;
        $this->pendingRenames = [];

        foreach ($pending as $rename) {
            $this->applyRename($postFlushEventArgs->getObjectManager(), $rename);
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
            $this->imageCacheGenerator->generateQuickPreview($media);
            $this->imageCacheGenerator->runBackgroundCacheGeneration($media->getFileName());

            return;
        }

        $this->imageCacheManager->ensurePublicSymlink($media);

        if ('application/pdf' === $media->getMimeType()) {
            $this->pdfOptimizer->runBackgroundOptimization($media->getFileName());
        }
    }

    /** A flush that threw between the decision and the move must not leave it to the next one. */
    public function reset(): void
    {
        $this->pendingRenames = [];
    }

    /**
     * Where the bytes are *now*. Deferring the move split the name the row carries from the
     * name on disk for the length of a flush, so anything reading the file from inside one —
     * the hash above — has to ask, not assume.
     */
    private function fileNameOnDisk(Media $media): string
    {
        foreach ($this->pendingRenames as $rename) {
            if ($rename['media'] === $media && null !== $rename['moveFrom']) {
                return $rename['moveFrom'];
            }
        }

        return $media->getFileName();
    }

    /**
     * @return string|null the name to move from, or null when the file already sits at its
     *                     new name — a previous run renamed it and crashed before the DB caught up
     */
    private function resolveMoveSource(string $oldFileName, string $newFileName): ?string
    {
        $sourceExists = $this->mediaStorage->fileExists($oldFileName);
        $destExists = $this->mediaStorage->fileExists($newFileName);

        if ($destExists && $sourceExists) {
            throw new Exception('Impossible to rename '.$oldFileName.' to '.$newFileName.'. File already exists');
        }

        if ($sourceExists) {
            return $oldFileName;
        }

        if ($destExists) {
            return null;
        }

        // A previous run may have renamed the source to its normalized form but crashed
        // before the DB was updated. The conflict resolver may now pick a different dest
        // (e.g. with a -2 suffix for an alt conflict), so check the normalized source too.
        $normalizedOldFileName = MediaFileName::normalizeFromString($oldFileName);
        if ($normalizedOldFileName !== $oldFileName && $this->mediaStorage->fileExists($normalizedOldFileName)) {
            return $normalizedOldFileName;
        }

        throw new Exception('Cannot rename '.$oldFileName.': file not found on disk');
    }

    /** @param array{media: Media, oldFileName: string, newFileName: string, moveFrom: string|null} $rename */
    private function applyRename(EntityManagerInterface $entityManager, array $rename): void
    {
        $media = $rename['media'];

        if (null !== $rename['moveFrom']) {
            try {
                $this->mediaStorage->move($rename['moveFrom'], $rename['newFileName']);
            } catch (Throwable $throwable) {
                $this->revertFileName($entityManager, $media, $rename['oldFileName']);

                throw $throwable;
            }
        }

        $this->imageCacheManager->remove($rename['oldFileName']);
        $this->imageCacheGenerator->generateQuickPreview($media);
        $this->imageCacheGenerator->runBackgroundCacheGeneration($rename['newFileName']);
    }

    /**
     * The row is committed, so keeping it honest means writing the old name straight back.
     * fileNameHistory keeps the name it gained: it is a resolution index, and an entry
     * pointing at the media that already answers to that name resolves to the same row.
     */
    private function revertFileName(EntityManagerInterface $entityManager, Media $media, string $oldFileName): void
    {
        if (null === $media->id) {
            return;
        }

        $classMetadata = $entityManager->getClassMetadata($media::class);

        $entityManager->getConnection()->update(
            $classMetadata->getTableName(),
            [$classMetadata->getColumnName('fileName') => $oldFileName],
            [$classMetadata->getColumnName('id') => $media->id],
        );
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
