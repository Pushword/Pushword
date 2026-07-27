<?php

namespace Pushword\Core\Image\License;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Throwable;

/**
 * Reads the rights metadata a file carries in its own bytes.
 *
 * XMP first, IPTC-IIM and EXIF as per-property fallbacks: xmpRights:WebStatement and
 * plus:LicensorURL are XMP-only, IIM and EXIF structurally cannot carry them. The C2PA
 * manifest comes last and only ever contributes a digitalSourceType.
 */
class EmbeddedRightsReader
{
    private const string RDF_NAMESPACE = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

    private const string XML_NAMESPACE = 'http://www.w3.org/XML/1998/namespace';

    /**
     * Registered by URI, never by prefix: an XMP packet aliases its prefixes freely
     * (dc may be bound as purl, Iptc4xmpExt as iptcExt), so matching on the prefix
     * silently misses values.
     *
     * @var array<string, string>
     */
    private const array NAMESPACES = [
        'rdf' => self::RDF_NAMESPACE,
        'dc' => 'http://purl.org/dc/elements/1.1/',
        'xmpRights' => 'http://ns.adobe.com/xap/1.0/rights/',
        'photoshop' => 'http://ns.adobe.com/photoshop/1.0/',
        'plus' => 'http://ns.useplus.org/ldf/xmp/1.0/',
        'iptcExt' => 'http://iptc.org/std/Iptc4xmpExt/2008-02-29/',
    ];

    private const string IIM_CREATOR = '2#080';

    private const string IIM_CREDIT = '2#110';

    private const string IIM_COPYRIGHT = '2#116';

    public function read(string $path): EmbeddedRights
    {
        if (! is_file($path)) {
            return new EmbeddedRights();
        }

        $container = ImageContainer::read($path);

        return EmbeddedRights::merge(
            $this->parseXmp($container->xmp),
            $this->readIim($path),
            $this->readExif($path),
            // Last: a rights claim somebody wrote by hand outranks a generator's own
            // note about how the pixels were made.
            C2paManifest::read($container->c2pa),
        );
    }

    // --- XMP ---

    private function parseXmp(string $packet): EmbeddedRights
    {
        // Guarded here rather than left to the parser: loadXML() raises on an empty
        // string instead of reporting it as malformed like every other bad input.
        if ('' === $packet) {
            return new EmbeddedRights();
        }

        $xpath = $this->buildXpath($packet);

        if (null === $xpath) {
            return new EmbeddedRights();
        }

        $digitalSourceType = $this->firstValue($xpath, 'iptcExt:DigitalSourceType');
        if ('' === $digitalSourceType) {
            $digitalSourceType = $this->firstValue($xpath, 'iptcExt:DigitalSourceFileType');
        }

        return new EmbeddedRights(
            license: MediaLicense::normalizeUrl($this->firstValue($xpath, 'xmpRights:WebStatement')),
            acquireLicensePage: MediaLicense::normalizeUrl($this->firstValue($xpath, 'plus:LicensorURL')),
            creditText: $this->firstValue($xpath, 'photoshop:Credit'),
            creator: $this->values($xpath, 'dc:creator'),
            copyrightNotice: $this->firstValue($xpath, 'dc:rights'),
            digitalSourceType: MediaLicense::normalizeDigitalSourceType($digitalSourceType),
        );
    }

    private function buildXpath(string $packet): ?DOMXPath
    {
        $document = new DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($packet, \LIBXML_NONET | \LIBXML_NOERROR | \LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return null;
        }

        $xpath = new DOMXPath($document);
        foreach (self::NAMESPACES as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        return $xpath;
    }

    private function firstValue(DOMXPath $xpath, string $name): string
    {
        return $this->values($xpath, $name)[0] ?? '';
    }

    /**
     * A property is written either as a child element or as an attribute on
     * rdf:Description, so both forms are queried at once.
     *
     * @return string[]
     */
    private function values(DOMXPath $xpath, string $name): array
    {
        $nodes = $xpath->query('//'.$name.'|//@'.$name);

        if (false === $nodes) {
            return [];
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }

            $values = $this->nodeValues($node);
            if ([] !== $values) {
                return $values;
            }
        }

        return [];
    }

    /**
     * @return string[]
     */
    private function nodeValues(DOMNode $node): array
    {
        if ($node instanceof DOMAttr) {
            return $this->asList($node->value);
        }

        if (! $node instanceof DOMElement) {
            return [];
        }

        $container = $this->rdfContainer($node);

        if (null === $container) {
            return $this->asList($node->textContent);
        }

        $values = [];
        foreach ($container->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ('li' !== $child->localName) {
                continue;
            }

            if (self::RDF_NAMESPACE !== $child->namespaceURI) {
                continue;
            }

            // In an rdf:Alt the languages are alternatives of one value; x-default is
            // the one to publish. Without it, the first item keeps the read deterministic.
            if ('Alt' === $container->localName && 'x-default' === $child->getAttributeNS(self::XML_NAMESPACE, 'lang')) {
                return $this->asList($child->textContent);
            }

            $value = trim($child->textContent);
            if ('' !== $value) {
                $values[] = $value;
            }
        }

        return $values;
    }

    private function rdfContainer(DOMElement $element): ?DOMElement
    {
        foreach ($element->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if (self::RDF_NAMESPACE !== $child->namespaceURI) {
                continue;
            }

            if (\in_array($child->localName, ['Alt', 'Bag', 'Seq'], true)) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function asList(string $value): array
    {
        $value = trim($value);

        return '' === $value ? [] : [$value];
    }

    // --- IPTC-IIM (APP13) ---

    private function readIim(string $path): EmbeddedRights
    {
        $info = [];
        if (false === @getimagesize($path, $info)) {
            return new EmbeddedRights();
        }

        $app13 = \is_array($info) ? ($info['APP13'] ?? null) : null;
        if (! \is_string($app13)) {
            return new EmbeddedRights();
        }

        $parsed = @iptcparse($app13);
        if (! \is_array($parsed)) {
            return new EmbeddedRights();
        }

        return new EmbeddedRights(
            creditText: $this->iimValues($parsed, self::IIM_CREDIT)[0] ?? '',
            creator: $this->iimValues($parsed, self::IIM_CREATOR),
            copyrightNotice: $this->iimValues($parsed, self::IIM_COPYRIGHT)[0] ?? '',
        );
    }

    /**
     * iptcparse() always hands back a list, even for a single-valued tag.
     *
     * @param array<array-key, mixed> $parsed
     *
     * @return string[]
     */
    private function iimValues(array $parsed, string $tag): array
    {
        return MediaLicense::normalizeNameList($parsed[$tag] ?? null);
    }

    // --- EXIF ---

    private function readExif(string $path): EmbeddedRights
    {
        if (! \function_exists('exif_read_data')) {
            return new EmbeddedRights();
        }

        try {
            $exif = @exif_read_data($path);
        } catch (Throwable) {
            return new EmbeddedRights();
        }

        if (! \is_array($exif)) {
            return new EmbeddedRights();
        }

        $artist = \is_string($exif['Artist'] ?? null) ? $exif['Artist'] : '';
        $copyright = \is_string($exif['Copyright'] ?? null) ? $exif['Copyright'] : '';

        return new EmbeddedRights(
            // A camera writing four spaces into Copyright has written nothing.
            creator: $this->asList($artist),
            copyrightNotice: trim($copyright),
        );
    }
}
