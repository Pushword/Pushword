<?php

namespace Pushword\Core\Tests\Image\License;

use Pushword\Core\Image\License\Bytes;

/**
 * Builds JPEG, PNG and WebP files carrying handcrafted XMP / IPTC-IIM / EXIF / C2PA.
 *
 * Generated rather than committed so every shape under test is visible in the test
 * itself — including the ones a normal editor cannot produce (empty rdf:Seq,
 * whitespace-only dc:rights, an aliased namespace prefix).
 */
final class ImageMetadataFixture
{
    /** The keyword the XMP specification defines for PNG. */
    public const string PNG_XMP_KEYWORD = 'XML:com.adobe.xmp';

    /** What ImageMagick writes instead — a hex-wrapped profile under its own keyword. */
    public const string PNG_RAW_PROFILE_KEYWORD = 'Raw profile type xmp';

    private const string XMP_SIGNATURE = "http://ns.adobe.com/xap/1.0/\0";

    private const string SUPERBOX = 'jumb';

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
        string $c2pa = '',
        int $c2paFragments = 1,
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

        if ('' !== $c2pa) {
            $segments .= self::app11($c2pa, $c2paFragments);
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

    /**
     * A PNG carrying XMP, in one of the four shapes found in the wild.
     *
     * @param string $keyword    PNG_XMP_KEYWORD or PNG_RAW_PROFILE_KEYWORD
     * @param string $chunkType  iTXt, zTXt or tEXt
     * @param bool   $compressed deflate the payload (mandatory for zTXt)
     */
    public static function writePng(
        string $path,
        string $xmpPacket = '',
        string $keyword = self::PNG_XMP_KEYWORD,
        string $chunkType = 'iTXt',
        bool $compressed = false,
        string $c2pa = '',
    ): string {
        $chunks = [];

        if ('' !== $xmpPacket) {
            $chunks[] = [$chunkType, self::pngTextChunk($keyword, $chunkType, $xmpPacket, $compressed)];
        }

        if ('' !== $c2pa) {
            $chunks[] = ['caBX', $c2pa];
        }

        file_put_contents($path, self::injectPngChunks(self::basePng(), $chunks));

        return $path;
    }

    /**
     * A WebP carrying XMP and/or C2PA. Both are plain RIFF chunks, payload as-is.
     */
    public static function writeWebp(string $path, string $xmpPacket = '', string $c2pa = ''): string
    {
        $body = self::baseWebp();
        // Strip the 'RIFF' + size + 'WEBP' header; it is rebuilt around the new chunks.
        $chunks = substr($body, 12);

        if ('' !== $xmpPacket) {
            $chunks .= self::riffChunk('XMP ', $xmpPacket);
        }

        if ('' !== $c2pa) {
            $chunks .= self::riffChunk('C2PA', $c2pa);
        }

        file_put_contents($path, 'RIFF'.pack('V', \strlen($chunks) + 4).'WEBP'.$chunks);

        return $path;
    }

    /**
     * A JUMBF manifest store holding one `c2pa.actions` assertion, as OpenAI writes it.
     *
     * Hand-built rather than captured so the box nesting is readable here: a `jumb`
     * superbox, its `jumd` description carrying the label, then the CBOR payload.
     */
    public static function c2paActions(string $digitalSourceType, string $label = 'c2pa.actions.v2'): string
    {
        $cbor = self::cborActions($digitalSourceType);

        $assertion = self::jumbfBox(self::SUPERBOX, self::jumbfDescription($label, 'cbor').self::jumbfBox('cbor', $cbor));

        // The store nests: manifest -> assertions -> the assertion itself.
        $assertions = self::jumbfBox(self::SUPERBOX, self::jumbfDescription('c2pa.assertions', 'c2as').$assertion);
        $manifest = self::jumbfBox(self::SUPERBOX, self::jumbfDescription('urn:c2pa:test', 'c2ma').$assertions);

        return self::jumbfBox(self::SUPERBOX, self::jumbfDescription('c2pa', 'c2pa').$manifest);
    }

    /**
     * `{"actions": [{"action": "c2pa.created", "digitalSourceType": "…"}]}` in CBOR.
     */
    private static function cborActions(string $digitalSourceType): string
    {
        return "\xA1".self::cborText('actions')
            ."\x81"
            ."\xA2".self::cborText('action').self::cborText('c2pa.created')
            .self::cborText('digitalSourceType').self::cborText($digitalSourceType);
    }

    public static function cborText(string $value): string
    {
        $length = \strlen($value);

        return match (true) {
            $length < 24 => \chr(0x60 | $length),
            $length < 256 => "\x78".\chr($length),
            default => "\x79".pack('n', $length),
        }.$value;
    }

    /**
     * ISO 19566-5 embedding: one JUMBF superbox split across APP11 segments, each
     * prefixed with `JP`, a box instance number and a packet sequence number, and each
     * repeating the superbox LBox/TBox.
     *
     * Written from the specification — no C2PA-carrying JPEG was available to capture,
     * and ImageMagick drops the manifest when transcoding one out of a PNG.
     */
    private static function app11(string $manifest, int $fragments): string
    {
        // The superbox header the spec repeats in every packet.
        $header = substr($manifest, 0, 8);
        $body = substr($manifest, 8);

        $perFragment = (int) ceil(\strlen($body) / max(1, $fragments));
        $segments = '';
        $sequence = 1;

        foreach (str_split($body, max(1, $perFragment)) as $fragment) {
            $segments .= self::segment(
                0xFFEB,
                'JP'.pack('n', 1).pack('N', $sequence).$header.$fragment,
            );
            ++$sequence;
        }

        return $segments;
    }

    private static function jumbfBox(string $type, string $payload): string
    {
        return pack('N', \strlen($payload) + 8).$type.$payload;
    }

    /**
     * A `jumd`: a 16-byte content-type UUID, a toggle byte, then the label. Toggle 0x03
     * is "requestable, label present", which is what every C2PA box uses.
     */
    private static function jumbfDescription(string $label, string $contentType): string
    {
        $uuid = str_pad($contentType, 16, "\x00");

        return self::jumbfBox('jumd', $uuid."\x03".$label."\x00");
    }

    private static function riffChunk(string $fourCc, string $payload): string
    {
        // RIFF pads odd-sized chunks to an even boundary.
        return $fourCc.pack('V', \strlen($payload)).$payload.(1 === \strlen($payload) % 2 ? "\x00" : '');
    }

    private static function pngTextChunk(string $keyword, string $chunkType, string $text, bool $compressed): string
    {
        // The keyword decides how the text is wrapped, the chunk type how it is encoded —
        // the same split the reader makes, so either can appear with either.
        $payload = self::PNG_RAW_PROFILE_KEYWORD === $keyword ? self::rawProfile($text) : $text;

        if ('tEXt' === $chunkType) {
            return $keyword."\x00".$payload;
        }

        if ('zTXt' === $chunkType) {
            return $keyword."\x00\x00".gzcompress($payload);
        }

        // iTXt: compression flag, compression method, language tag, translated keyword.
        return $keyword."\x00".($compressed ? "\x01" : "\x00")."\x00\x00\x00"
            .($compressed ? gzcompress($payload) : $payload);
    }

    /** ImageMagick's wrapper: name, byte length in eight columns, then wrapped hex. */
    private static function rawProfile(string $payload): string
    {
        return "\nxmp\n".str_pad((string) \strlen($payload), 8, ' ', \STR_PAD_LEFT)."\n"
            .chunk_split(bin2hex($payload), 78, "\n");
    }

    /**
     * @param list<array{0: string, 1: string}> $extra
     */
    private static function injectPngChunks(string $png, array $extra): string
    {
        $output = substr($png, 0, 8);
        $offset = 8;

        while ($offset < \strlen($png)) {
            $length = Bytes::uint32($png, $offset);
            \assert(null !== $length);
            $chunk = substr($png, $offset, 12 + $length);

            // Anywhere after IHDR is valid; before IDAT keeps it readable without
            // walking the pixel data.
            if ('IDAT' === substr($png, $offset + 4, 4)) {
                foreach ($extra as [$type, $payload]) {
                    $output .= pack('N', \strlen($payload)).$type.$payload.pack('N', crc32($type.$payload));
                }

                $extra = [];
            }

            $output .= $chunk;
            $offset += 12 + $length;
        }

        return $output;
    }

    private static function basePng(): string
    {
        $image = imagecreatetruecolor(8, 8);
        \assert(false !== $image);

        ob_start();
        imagepng($image);

        return ob_get_clean();
    }

    private static function baseWebp(): string
    {
        $image = imagecreatetruecolor(8, 8);
        \assert(false !== $image);

        ob_start();
        imagewebp($image);

        return ob_get_clean();
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
