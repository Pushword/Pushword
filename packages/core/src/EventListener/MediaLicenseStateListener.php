<?php

namespace Pushword\Core\EventListener;

use Doctrine\ORM\Event\PreUpdateEventArgs;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\License\MediaLicense;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Keeps Media::licenseState in step with the license properties when a human writes
 * them — through the admin form, the API merge-patch or the flat import.
 *
 * This cannot live in setCustomProperty(): that setter is a trait shared with Page,
 * and the keys are written from four different paths.
 *
 * An upload is not one of them: while a file is in flight MediaLicenseSeedListener
 * owns the decision, and it has read the file to make it.
 */
#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'prePersist'])]
#[AutoconfigureTag('doctrine.orm.entity_listener', ['entity' => '%pw.entity_media%', 'event' => 'preUpdate'])]
final readonly class MediaLicenseStateListener
{
    public function prePersist(Media $media): void
    {
        if ($this->isUploading($media) || MediaLicense::STATE_NONE !== $media->getLicenseState()) {
            return;
        }

        // A media created with license properties but no upload — a flat import, an
        // API POST — carries values somebody chose deliberately.
        if (MediaLicense::hasRightsValue(MediaLicense::extract($media))) {
            $media->setLicenseState(MediaLicense::STATE_OVERRIDDEN);
        }
    }

    public function preUpdate(Media $media, PreUpdateEventArgs $event): void
    {
        // A caller that set the state itself already knows what it did — the backfill
        // command, or the seed listener on an upload it decided in this same flush.
        if ($event->hasChangedField('licenseState') || $this->isUploading($media)) {
            return;
        }

        if (! $event->hasChangedField('customProperties')) {
            return;
        }

        // The changeset, not getOriginalEntityData(): Doctrine replaces the original
        // data with the new values at the end of computeChangeSet, so by preUpdate the
        // "original" array is already the edited one.
        $old = $event->getOldValue('customProperties');
        $previous = \is_array($old) ? $this->licenseValues($old) : [];

        if ($previous === $this->licenseValues($media->getCustomProperties())) {
            return;
        }

        // Cleared: the media stops emitting, so it has no license state at all.
        // Otherwise somebody asserted these values, whatever they were before.
        $state = MediaLicense::hasRightsValue(MediaLicense::extract($media))
            ? MediaLicense::STATE_OVERRIDDEN
            : MediaLicense::STATE_NONE;

        if ($state === $media->getLicenseState()) {
            return;
        }

        $media->setLicenseState($state);

        // A field assigned during preUpdate is invisible to the already-computed
        // changeset: without this the new state is simply never written.
        $objectManager = $event->getObjectManager();
        $objectManager->getUnitOfWork()->recomputeSingleEntityChangeSet(
            $objectManager->getClassMetadata($media::class),
            $media,
        );
    }

    /**
     * True while a file is waiting to be stored, i.e. this very flush is an upload.
     *
     * Doctrine invokes entity listeners before the EventManager ones, so this runs
     * ahead of Vich — which is what makes the check work: Vich has not moved the
     * temporary file away yet. A media that merely still holds the File object of a
     * past upload points at a path that no longer exists.
     */
    private function isUploading(Media $media): bool
    {
        $file = $media->getMediaFile();

        return null !== $file && file_exists($file->getPathname());
    }

    /**
     * @param array<array-key, mixed> $customProperties
     *
     * @return array<string, mixed>
     */
    private function licenseValues(array $customProperties): array
    {
        $values = [];

        foreach (MediaLicense::KEYS as $key) {
            if (\array_key_exists($key, $customProperties)) {
                $values[$key] = $customProperties[$key];
            }
        }

        return $values;
    }
}
