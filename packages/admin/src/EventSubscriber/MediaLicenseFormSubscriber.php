<?php

namespace Pushword\Admin\EventSubscriber;

use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\License\MediaLicense;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Writes back what MediaLicenseField submitted: the seven keys live in
 * customProperties, so the fields are `mapped => false` and nothing persists them
 * on its own.
 *
 * A submitted-but-empty field removes its key — that is how the "clear" button and a
 * manual blanking both make a media stop emitting its ImageObject.
 */
final readonly class MediaLicenseFormSubscriber implements EventSubscriberInterface
{
    /**
     * A collection with no rows submits nothing at all, so an absent `creator` would
     * be ambiguous: every creator removed, or a form that never showed the field.
     * MediaLicenseField posts this hidden companion whenever the block was rendered.
     */
    public const string CREATOR_MARKER = 'creatorSubmitted';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => 'applyLicense',
            BeforeEntityUpdatedEvent::class => 'applyLicense',
        ];
    }

    public function applyLicense(object $event): void
    {
        if (! $event instanceof BeforeEntityPersistedEvent && ! $event instanceof BeforeEntityUpdatedEvent) {
            return;
        }

        $media = $event->getEntityInstance();
        if (! $media instanceof Media) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $submitted = $this->findLicensePayload($request->request->all());
        if (null === $submitted) {
            return;
        }

        foreach (MediaLicense::KEYS as $key) {
            // Absent means the field was not on this form at all — leave the stored
            // value alone. Present and empty means cleared.
            if (! $this->wasSubmitted($key, $submitted)) {
                continue;
            }

            $value = $this->normalize($key, $submitted[$key] ?? null);

            if (null === $value) {
                $media->removeCustomProperty($key);

                continue;
            }

            $media->setCustomProperty($key, $value);
        }
    }

    /**
     * @param array<array-key, mixed> $submitted
     */
    private function wasSubmitted(string $key, array $submitted): bool
    {
        if (\array_key_exists($key, $submitted)) {
            return true;
        }

        return MediaLicense::CREATOR === $key && \array_key_exists(self::CREATOR_MARKER, $submitted);
    }

    /**
     * @return string|list<array{name: string, type: string}>|null null when the field was submitted empty
     */
    private function normalize(string $key, mixed $raw): string|array|null
    {
        if (MediaLicense::CREATOR === $key) {
            // Rows the collection editor submitted, or a plain string from anything
            // that only has one input to give.
            $creators = MediaLicense::normalizeCreators($raw);

            return [] === $creators ? null : $creators;
        }

        if (! \is_scalar($raw)) {
            return null;
        }

        $value = trim((string) $raw);

        if ('' === $value) {
            return null;
        }

        if (\in_array($key, MediaLicense::URL_KEYS, true)) {
            $value = MediaLicense::normalizeUrl($value);
        }

        return '' === $value ? null : $value;
    }

    /**
     * The fields are nested under EasyAdmin's form name; the block holding them is
     * the one carrying the license keys.
     *
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>|null
     */
    private function findLicensePayload(array $values): ?array
    {
        foreach ([...MediaLicense::KEYS, self::CREATOR_MARKER] as $key) {
            if (\array_key_exists($key, $values)) {
                return $values;
            }
        }

        foreach ($values as $value) {
            if (! \is_array($value)) {
                continue;
            }

            /** @var array<array-key, mixed> $value */
            $found = $this->findLicensePayload($value);
            if (null !== $found) {
                return $found;
            }
        }

        return null;
    }
}
