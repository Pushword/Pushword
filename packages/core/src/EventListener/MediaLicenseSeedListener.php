<?php

namespace Pushword\Core\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\License\EmbeddedRights;
use Pushword\Core\Image\License\EmbeddedRightsReader;
use Pushword\Core\Image\License\MediaLicense;
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
 */
#[AutoconfigureTag('kernel.event_listener', ['event' => 'vich_uploader.pre_upload'])]
final readonly class MediaLicenseSeedListener
{
    use MediaAlertTrait;

    public function __construct(
        private EmbeddedRightsReader $rightsReader,
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
        if (null === $file || ! $media->imageData->isImage((string) $file->getMimeType())) {
            return;
        }

        // Re-uploading byte-identical content is an accident, not a new licensing
        // situation: wiping a hand-written credit over it would be pure loss.
        if ($this->isSameFile($media, $file)) {
            return;
        }

        $previousState = $media->getLicenseState();

        $imported = $this->rightsReader->read($file->getPathname())->stripGeneratorMarkers();

        // A replacement never merges: the previous values described the previous
        // bytes. Keeping the empty ones would either leave a stale photographer on a
        // photo the site now owns, or leave the site's acquireLicensePage on somebody
        // else's photo — the exact false claim, through the back door.
        foreach (MediaLicense::KEYS as $key) {
            $media->removeCustomProperty($key);
        }

        foreach ($imported->toCustomProperties() as $key => $value) {
            $media->setCustomProperty($key, $value);
        }

        if ($imported->hasRightsValue()) {
            // Nothing in the bytes tells "commissioned, we hold the rights" apart from
            // "someone else's photo", so the site does not license over a rights claim
            // on its own. A human asserts it, with the admin button or --force.
            $media->setLicenseState(MediaLicense::STATE_THIRD_PARTY);
            $this->disclose('warning', 'mediaLicenseThirdParty', ['%rights%' => $this->rightsSummary($imported)]);
        } elseif ($this->seed($media)) {
            $media->setLicenseState(MediaLicense::STATE_SEEDED);
            $this->disclose('info', 'mediaLicenseSeeded');
        } else {
            $media->setLicenseState(MediaLicense::STATE_NONE);
        }

        if (MediaLicense::STATE_OVERRIDDEN === $previousState) {
            $this->alert('warning', 'mediaLicenseWasReset');
        }
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

    private function seed(Media $media): bool
    {
        $seed = MediaLicense::normalizeSeed($this->apps->get()->getArray('media_default_license_seed'));

        foreach ($seed as $key => $value) {
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
