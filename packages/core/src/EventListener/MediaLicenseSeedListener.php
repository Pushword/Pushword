<?php

declare(strict_types=1);

namespace Pushword\Core\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\License\EmbeddedRights;
use Pushword\Core\Image\License\MediaLicense;
use Pushword\Core\Image\License\UploadRightsReader;
use Pushword\Core\Site\SiteRegistry;

use function Safe\sha1_file;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Event\Event;

/**
 * Decides, once per uploaded file, what the media claims about its own licensing.
 *
 * The app config is a *seed* written into the media's own properties here, never a
 * fallback consulted at render time: rendering reads the media and nothing else, so
 * changing the config later cannot silently re-license a thousand existing images.
 * The backfill command (pw:media:license) is what propagates a config change.
 *
 * Runs on vich_uploader.pre_upload, which fires on insert and on update, so an admin
 * swapping the file on an existing media re-runs the whole decision. Rotation writes
 * through MediaStorageAdapter and bypasses Vich, so it never reaches here.
 *
 * A value submitted with the upload — typed in the admin form, posted with an API
 * upload — is a decision, not a guess: it outranks the file and the seed, key by key.
 */
#[AutoconfigureTag('kernel.event_listener', ['event' => 'vich_uploader.pre_upload'])]
final readonly class MediaLicenseSeedListener
{
    use MediaAlertTrait;

    public function __construct(
        private UploadRightsReader $uploadRightsReader,
        private EntityManagerInterface $em,
        private SiteRegistry $apps,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
    ) {
    }

    public function onVichUploaderPreUpload(Event $event): void
    {
        $media = $event->getObject();
        if (! $media instanceof Media) {
            return;
        }

        $file = $media->getMediaFile();

        // Same definition as everywhere else — which excludes SVG, the one image kind
        // ImageObjectBuilder never emits for.
        if (! $file instanceof File || ! $media->imageData->isImage((string) $file->getMimeType())) {
            return;
        }

        // Re-uploading byte-identical content is an accident, not a new licensing
        // situation: wiping a hand-written credit over it would be pure loss.
        if ($this->isSameFile($media, $file)) {
            return;
        }

        $previousState = $media->licenseState;

        $imported = $this->uploadRightsReader->read($file);
        $submitted = $this->submittedValues($media);

        // A replacement never merges: the previous values described the previous
        // bytes. Keeping the empty ones would either leave a stale photographer on a
        // photo the site now owns, or leave the site's acquireLicensePage on somebody
        // else's photo — the exact false claim, through the back door.
        foreach (array_diff(MediaLicense::KEYS, array_keys($submitted)) as $key) {
            $media->removeCustomProperty($key);
        }

        foreach (array_diff_key($imported->toCustomProperties(), $submitted) as $key => $value) {
            $media->setCustomProperty($key, $value);
        }

        if (MediaLicense::hasRightsValue($submitted)) {
            // Somebody asserted these with the file in hand, so it is theirs to answer
            // for, as with the admin button — and out of the backfill command's reach.
            $media->licenseState = MediaLicense::STATE_OVERRIDDEN;
        } elseif ($imported->hasRightsValue()) {
            // Nothing in the bytes tells "commissioned, we hold the rights" apart from
            // "someone else's photo", so the site does not license over a rights claim
            // on its own. A human asserts it, with the admin button or --force.
            $media->licenseState = MediaLicense::STATE_THIRD_PARTY;
            $this->disclose('warning', 'mediaLicenseThirdParty', ['%rights%' => $this->rightsSummary($imported)]);
        } elseif ($this->seed($media, $submitted)) {
            $media->licenseState = MediaLicense::STATE_SEEDED;
            $this->disclose('info', 'mediaLicenseSeeded');
        } else {
            $media->licenseState = MediaLicense::STATE_NONE;
        }

        if (MediaLicense::STATE_OVERRIDDEN === $previousState && MediaLicense::STATE_OVERRIDDEN !== $media->licenseState) {
            $this->alert('warning', 'mediaLicenseWasReset');
        }
    }

    /**
     * The license values this write sets on purpose. A new media holds nothing else.
     * A replaced file comes with the stored values posted back by the edit form, which
     * describe the previous bytes: only the keys changed alongside it count.
     *
     * The changeset, not getOriginalEntityData(): on an update this runs in preUpdate,
     * where Doctrine has already replaced the original data with the edited values.
     *
     * @return array<string, mixed>
     */
    private function submittedValues(Media $media): array
    {
        $values = MediaLicense::extract($media);

        if (null === $media->id) {
            return $values;
        }

        $changeSet = $this->em->getUnitOfWork()->getEntityChangeSet($media);
        if (! isset($changeSet['customProperties'])) {
            return [];
        }

        $old = $changeSet['customProperties'][0];
        // Normalized on both sides: a creator stored in the legacy list form comes
        // back from the form as rows, which is the same value, not a new one.
        $previous = MediaLicense::extractFrom(\is_array($old) ? $old : []);

        return array_filter(
            $values,
            static fn (mixed $value, string $key): bool => ($previous[$key] ?? null) !== $value,
            \ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * The decision is taken without asking, and the form redirects to the index, so it
     * has to be said out loud — otherwise a credit line lands on a media with nothing
     * telling the editor it happened.
     *
     * Silent for an XHR upload — the multi-uploader discloses on each row as it lands,
     * so one flash per file would only queue up until the editor's next page load, by
     * which time it names nothing — and silent with no request at all, which is the
     * backfill command, whose own report is the disclosure.
     *
     * @param array<string, string> $parameters
     */
    private function disclose(string $type, string $message, array $parameters = []): void
    {
        if ($this->requestStack->getCurrentRequest()?->isXmlHttpRequest() ?? true) {
            return;
        }

        $this->alert($type, $message, $parameters);
    }

    /**
     * Who the file says it belongs to, so the warning names something recognizable.
     *
     * Escaped here, not by the template: flashes are rendered raw in this codebase
     * (mediaDuplicateWarning ships a link), and this value comes out of an uploaded
     * file's metadata. A copyright notice can also be a page of legal boilerplate.
     */
    private function rightsSummary(EmbeddedRights $rights): string
    {
        $summary = implode(', ', $rights->creator)
            ?: ($rights->creditText ?: $rights->copyrightNotice ?: $rights->license);

        if (mb_strlen($summary) > 80) {
            $summary = mb_substr($summary, 0, 80).'…';
        }

        return htmlspecialchars($summary, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @param array<string, mixed> $submitted */
    private function seed(Media $media, array $submitted): bool
    {
        $seed = MediaLicense::normalizeSeed($this->apps->get()->getArray('media_default_license_seed'));

        foreach (array_diff_key($seed, $submitted) as $key => $value) {
            $media->setCustomProperty($key, $value);
        }

        return [] !== $seed;
    }

    /**
     * Compares against the *persisted* hash rather than Media::getHash(): the naming
     * listener runs on this same event and may already have recomputed it.
     */
    private function isSameFile(Media $media, File $file): bool
    {
        if (null === $media->id || ! file_exists($file->getPathname())) {
            return false;
        }

        $original = $this->em->getUnitOfWork()->getOriginalEntityData($media);
        $previousHash = $this->toBinaryString($original['hash'] ?? null);

        return '' !== $previousHash && $previousHash === sha1_file($file->getPathname(), true);
    }

    private function toBinaryString(mixed $hash): string
    {
        if (\is_resource($hash)) {
            rewind($hash);
            $hash = stream_get_contents($hash);
        }

        return \is_string($hash) ? $hash : '';
    }
}
