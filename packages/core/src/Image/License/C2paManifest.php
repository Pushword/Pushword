<?php

namespace Pushword\Core\Image\License;

/**
 * Reads how an image was made out of its C2PA manifest.
 *
 * C2PA ("Content Credentials") is what OpenAI, Google, Adobe and the camera makers
 * write, and it is the only place a gpt-image PNG says it is AI-generated: such a file
 * carries no XMP, no IPTC and no EXIF at all, so every other reader here returns empty.
 * The value is the same IPTC NewsCode DigitalSourceType vocabulary XMP uses.
 *
 * Only the provenance is read, never authorship: a manifest also carries the signer's
 * certificate, and treating that as the creator would publish a claim nobody made about
 * who owns the image.
 *
 * The signature is NOT verified — this answers "what does the file say about itself",
 * the same question we ask of XMP, which anybody can equally write. It is not evidence
 * of authenticity, and the resulting digitalSourceType stays editable in the admin.
 */
final class C2paManifest
{
    /**
     * JUMBF (ISO 19566-5) boxes: a 4-byte length, a 4-byte type, then the payload. A
     * `jumb` superbox opens with a `jumd` description box carrying a UUID, a toggle
     * byte and, when the toggles say so, a null-terminated label.
     */
    private const string SUPERBOX = 'jumb';

    private const string DESCRIPTION = 'jumd';

    private const string CBOR = 'cbor';

    /** The assertion label; v2 is the current revision and both are in the wild. */
    private const string ACTIONS_LABEL = 'c2pa.actions';

    private const int MAX_DEPTH = 16;

    public static function read(string $jumbf): EmbeddedRights
    {
        if ('' === $jumbf) {
            return new EmbeddedRights();
        }

        foreach (self::assertions($jumbf, 0) as $assertion) {
            $digitalSourceType = self::digitalSourceType($assertion);

            if ('' !== $digitalSourceType) {
                return new EmbeddedRights(digitalSourceType: $digitalSourceType);
            }
        }

        return new EmbeddedRights();
    }

    /**
     * Every CBOR payload sitting under a `c2pa.actions*` superbox, decoded.
     *
     * A manifest store holds the active manifest plus one per ingredient, so a
     * derivative carries its parents' assertions too. All of them are read: an image
     * assembled from AI-generated parts was still made with AI, and reporting that is
     * the conservative answer.
     *
     * @return list<array<array-key, mixed>>
     */
    private static function assertions(string $boxes, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            return [];
        }

        $found = [];
        $offset = 0;
        $total = \strlen($boxes);

        while ($offset + 8 <= $total) {
            $length = Bytes::uint32($boxes, $offset);
            if (null === $length) {
                break;
            }

            $type = substr($boxes, $offset + 4, 4);

            // A zero length means "runs to the end of the enclosing box".
            if (0 === $length) {
                $length = $total - $offset;
            }

            if ($length < 8 || $offset + $length > $total) {
                break;
            }

            $payload = substr($boxes, $offset + 8, $length - 8);
            $offset += $length;

            if (self::SUPERBOX !== $type) {
                continue;
            }

            if (self::isActions($payload)) {
                $cbor = self::firstCborPayload($payload);
                $decoded = '' === $cbor ? null : Cbor::decode($cbor);

                if (\is_array($decoded)) {
                    $found[] = $decoded;
                }

                continue;
            }

            // Not an actions box, but manifests nest: keep descending.
            foreach (self::assertions($payload, $depth + 1) as $nested) {
                $found[] = $nested;
            }
        }

        return $found;
    }

    /**
     * A superbox's first child is its `jumd`, whose label names the assertion.
     */
    private static function isActions(string $superbox): bool
    {
        if (\strlen($superbox) < 8) {
            return false;
        }

        $length = Bytes::uint32($superbox);
        if (null === $length || self::DESCRIPTION !== substr($superbox, 4, 4)) {
            return false;
        }

        if ($length < 8 || $length > \strlen($superbox)) {
            return false;
        }

        // 16-byte content-type UUID, then the toggle byte, then the label.
        $description = substr($superbox, 8, $length - 8);
        if (\strlen($description) < 17) {
            return false;
        }

        $label = substr($description, 17);
        $end = strpos($label, "\0");

        return str_starts_with(false === $end ? $label : substr($label, 0, $end), self::ACTIONS_LABEL);
    }

    /**
     * The assertion's own bytes live in a sibling `cbor` box, next to the description
     * and, in signed manifests, a `c2sh` hash box.
     */
    private static function firstCborPayload(string $superbox): string
    {
        $offset = 0;
        $total = \strlen($superbox);

        while ($offset + 8 <= $total) {
            $declared = Bytes::uint32($superbox, $offset);
            if (null === $declared) {
                return '';
            }

            $length = 0 === $declared ? $total - $offset : $declared;
            if ($length < 8 || $offset + $length > $total) {
                return '';
            }

            if (self::CBOR === substr($superbox, $offset + 4, 4)) {
                return substr($superbox, $offset + 8, $length - 8);
            }

            $offset += $length;
        }

        return '';
    }

    /**
     * `{"actions": [{"action": "c2pa.created", "digitalSourceType": "…"}]}`.
     *
     * @param array<array-key, mixed> $assertion
     */
    private static function digitalSourceType(array $assertion): string
    {
        $actions = $assertion['actions'] ?? null;
        if (! \is_array($actions)) {
            return '';
        }

        foreach ($actions as $action) {
            if (! \is_array($action)) {
                continue;
            }

            $value = $action['digitalSourceType'] ?? null;
            if (\is_string($value) && '' !== trim($value)) {
                return MediaLicense::normalizeDigitalSourceType($value);
            }
        }

        return '';
    }
}
