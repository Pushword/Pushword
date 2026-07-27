<?php

namespace Pushword\Core\Tests\Image\License;

/**
 * Builds JPEGs carrying handcrafted XMP / IPTC-IIM / EXIF segments.
 *
 * Generated rather than committed so every shape under test is visible in the test
 * itself — including the ones a normal editor cannot produce (empty rdf:Seq,
 * whitespace-only dc:rights, an aliased namespace prefix).
 */
final class JpegMetadataFixture
{
    private const string XMP_SIGNATURE = "http://ns.adobe.com/xap/1.0/\0";

    private const string PHOTOSHOP_SIGNATURE = "Photoshop 3.0\0";

    private const string EXIF_SIGNATURE = "Exif\0\0";

    /**
     * @param array<string, string> $exif    tag name => ASCII value (Artist, Copyright)
     * @param array<string, string> $iptcIim IIM tag ("2#080") => value
     */
    public static function write(
        string $path,
        string $xmpPacket = '',
        array $iptcIim = [],
        array $exif = [],
    ): string {
        $jpeg = self::baseJpeg();
        $segments = '';

        // EXIF must come first: it is the APP1 a naive reader finds, which is exactly
        // the trap EmbeddedRightsReader has to survive.
        if ([] !== $exif) {
            $segments .= self::segment(0xFFE1, self::EXIF_SIGNATURE.self::tiff($exif));
        }

        if ('' !== $xmpPacket) {
            $segments .= self::segment(0xFFE1, self::XMP_SIGNATURE.$xmpPacket);
        }

        if ([] !== $iptcIim) {
            $segments .= self::segment(0xFFED, self::PHOTOSHOP_SIGNATURE.self::photoshopIptcBlock($iptcIim));
        }

        file_put_contents($path, substr($jpeg, 0, 2).$segments.substr($jpeg, 2));

        return $path;
    }

    /**
     * Wraps a body in an XMP packet, the way a real writer does — xpacket processing
     * instructions included, since they sit outside the XML root element.
     */
    public static function packet(string $body): string
    {
        return '<?xpacket begin="'."\u{FEFF}".'" id="W5M0MpCehiHzreSzNTczkc9d"?>'
            .'<x:xmpmeta xmlns:x="adobe:ns:meta/">'
            .'<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            .$body
            .'</rdf:RDF></x:xmpmeta>'
            .str_repeat(' ', 32)
            .'<?xpacket end="w"?>';
    }

    private static function baseJpeg(): string
    {
        $image = imagecreatetruecolor(8, 8);
        \assert(false !== $image);

        ob_start();
        imagejpeg($image, null, 90);

        return ob_get_clean();
    }

    private static function segment(int $marker, string $payload): string
    {
        return pack('n', $marker).pack('n', \strlen($payload) + 2).$payload;
    }

    /**
     * 8BIM container holding the IPTC-IIM resource (0x0404); each dataset is
     * 0x1C, record, tag, then a 2-byte length.
     *
     * @param array<string, string> $iptcIim
     */
    private static function photoshopIptcBlock(array $iptcIim): string
    {
        $datasets = '';
        foreach ($iptcIim as $tag => $value) {
            $parts = explode('#', $tag);
            $record = (int) $parts[0];
            $dataset = (int) ($parts[1] ?? 0);
            if ($record < 0) {
                continue;
            }

            if ($record > 255) {
                continue;
            }

            if ($dataset < 0) {
                continue;
            }

            if ($dataset > 255) {
                continue;
            }

            $datasets .= "\x1C".\chr($record).\chr($dataset).pack('n', \strlen($value)).$value;
        }

        // '8BIM' + resource id + empty Pascal name (padded to even) + size + data
        $block = '8BIM'.pack('n', 0x0404)."\x00\x00".pack('N', \strlen($datasets)).$datasets;

        return 0 === \strlen($datasets) % 2 ? $block : $block."\x00";
    }

    /**
     * Minimal little-endian TIFF: header, one IFD whose entries are sorted by tag,
     * then the ASCII values it points at.
     *
     * @param array<string, string> $exif
     */
    private static function tiff(array $exif): string
    {
        $tags = ['Artist' => 0x013B, 'Copyright' => 0x8298];

        $entries = [];
        foreach ($exif as $name => $value) {
            if (isset($tags[$name])) {
                $entries[$tags[$name]] = $value."\0";
            }
        }

        ksort($entries);

        $header = "II\x2A\x00".pack('V', 8);
        $ifd = pack('v', \count($entries));
        // header (8) + entry count (2) + entries (12 each) + next-IFD offset (4)
        $dataOffset = 8 + 2 + 12 * \count($entries) + 4;
        $data = '';

        foreach ($entries as $tag => $value) {
            $length = \strlen($value);
            $ifd .= pack('v', $tag).pack('v', 2).pack('V', $length);
            // Up to four bytes live inline, padded; anything longer is an offset.
            $ifd .= $length <= 4 ? str_pad($value, 4, "\0") : pack('V', $dataOffset + \strlen($data));

            if ($length > 4) {
                $data .= $value;
            }
        }

        return $header.$ifd.pack('V', 0).$data;
    }
}
