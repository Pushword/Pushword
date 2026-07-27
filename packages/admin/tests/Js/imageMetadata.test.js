import { describe, expect, it } from 'vitest'
import { extractEmbeddedMetadata } from '../../src/Resources/assets/admin.imageMetadata.js'
import {
  CREATOR_TOOL_XMP,
  CREATOR_XMP,
  app11,
  app13Iim,
  app1Exif,
  app1Xmp,
  jpeg,
  png,
  pngC2paChunk,
  pngTextChunk,
  riffChunk,
  tiff,
  toFile,
  webp,
} from './imageFixture.js'

const EXIF_ARTIST = 0x013b
const EXIF_COPYRIGHT = 0x8298

function extract(bytes, type) {
  return extractEmbeddedMetadata(toFile(bytes, type))
}

/** What the server will see once it base64-decodes a segment. */
async function decoded(bytes, type, segment) {
  const metadata = await extract(bytes, type)

  return metadata === null || metadata[segment] === undefined ? null : atob(metadata[segment])
}

describe('JPEG', () => {
  it('hands over the XMP packet without its signature', async () => {
    // The server's parser is handed a packet, not a segment: the Adobe signature in
    // front of it is APP1 framing and would make the XML invalid.
    await expect(decoded(jpeg([app1Xmp(CREATOR_XMP)]), 'image/jpeg', 'xmp')).resolves.toBe(CREATOR_XMP)
  })

  it('hands over the APP13 payload whole, Photoshop signature included', async () => {
    // iptcparse() expects the segment as getimagesize() reports it, header and all.
    const iptc = await decoded(jpeg([app13Iim([[80, 'Enrico Romanzi']])]), 'image/jpeg', 'iptc')

    expect(iptc.startsWith('Photoshop 3.0\0')).toBe(true)
    expect(iptc).toContain('Enrico Romanzi')
  })

  it('parses Artist and Copyright out of EXIF itself', async () => {
    // The only source the browser interprets rather than forwards: exif_read_data()
    // reads a file, and by then there is no file left.
    const bytes = jpeg([
      app1Exif(
        tiff([
          { tag: EXIF_ARTIST, value: 'Enrico Romanzi\0' },
          { tag: EXIF_COPYRIGHT, value: '(c) Altimood\0' },
        ]),
      ),
    ])

    await expect(decoded(bytes, 'image/jpeg', 'artist')).resolves.toBe('Enrico Romanzi\0')
    await expect(decoded(bytes, 'image/jpeg', 'copyright')).resolves.toBe('(c) Altimood\0')
  })

  it('reads an EXIF value short enough to sit inside its own entry', async () => {
    await expect(decoded(jpeg([app1Exif(tiff([{ tag: EXIF_ARTIST, value: 'Ann' }]))]), 'image/jpeg', 'artist')).resolves.toBe('Ann')
  })

  it('reads a big-endian EXIF block', async () => {
    const bytes = jpeg([app1Exif(tiff([{ tag: EXIF_ARTIST, value: 'Enrico Romanzi\0' }], false))])

    await expect(decoded(bytes, 'image/jpeg', 'artist')).resolves.toBe('Enrico Romanzi\0')
  })

  it('forwards an XMP packet whatever it says', async () => {
    // No judgement here on purpose: whether xmp:CreatorTool alone is a rights claim is
    // the server's call, and duplicating it would let the two drift.
    await expect(decoded(jpeg([app1Xmp(CREATOR_TOOL_XMP)]), 'image/jpeg', 'xmp')).resolves.toBe(CREATOR_TOOL_XMP)
  })

  it('collects every source present in one file', async () => {
    const bytes = jpeg([
      app1Xmp(CREATOR_XMP),
      app1Exif(tiff([{ tag: EXIF_COPYRIGHT, value: '(c) Altimood\0' }])),
      app13Iim([[110, 'Altimood']]),
      app11('jumb bytes'),
    ])

    await expect(extract(bytes, 'image/jpeg')).resolves.toEqual({
      xmp: expect.any(String),
      copyright: expect.any(String),
      iptc: expect.any(String),
      c2pa: expect.any(String),
    })
  })

  it('returns nothing for a file with no metadata at all', async () => {
    await expect(extract(jpeg(), 'image/jpeg')).resolves.toBeNull()
  })
})

describe('PNG', () => {
  // The chunk type decides the encoding, the keyword the wrapping, and the server reads
  // all six combinations — so all six must arrive as the same packet.
  const shapes = [
    ['XML:com.adobe.xmp', 'tEXt', false],
    ['XML:com.adobe.xmp', 'zTXt', false],
    ['XML:com.adobe.xmp', 'iTXt', false],
    ['XML:com.adobe.xmp', 'iTXt', true],
    ['Raw profile type xmp', 'tEXt', false],
    ['Raw profile type xmp', 'zTXt', false],
  ]

  it.each(shapes)('unwraps %s in %s (compressed: %s) back to the packet', async (keyword, chunkType, compressed) => {
    const bytes = png([pngTextChunk(keyword, chunkType, CREATOR_XMP, compressed)])

    await expect(decoded(bytes, 'image/png', 'xmp')).resolves.toBe(CREATOR_XMP)
  })

  it('ignores text chunks under an unrelated keyword', async () => {
    // Only the keyword makes it invisible, which is what the server does too.
    await expect(extract(png([pngTextChunk('Description', 'tEXt', CREATOR_XMP)]), 'image/png')).resolves.toBeNull()
  })

  it('survives a corrupt deflate stream', async () => {
    const bytes = png([pngTextChunk('XML:com.adobe.xmp', 'tEXt', 'x').replace('tEXt', 'zTXt')])

    await expect(extract(bytes, 'image/png')).resolves.toBeNull()
  })

  it('returns nothing for a file with no metadata at all', async () => {
    await expect(extract(png(), 'image/png')).resolves.toBeNull()
  })
})

describe('WebP', () => {
  it('hands over the XMP chunk', async () => {
    await expect(decoded(webp([riffChunk('XMP ', CREATOR_XMP)]), 'image/webp', 'xmp')).resolves.toBe(CREATOR_XMP)
  })

  it('walks past an odd-sized chunk to reach the XMP one', async () => {
    // The pad byte is not counted in the size, so mishandling it shifts every chunk
    // after it and the walk silently finds nothing.
    const bytes = webp([riffChunk('EXIF', 'odd'), riffChunk('XMP ', CREATOR_XMP)])

    await expect(decoded(bytes, 'image/webp', 'xmp')).resolves.toBe(CREATOR_XMP)
  })

  it('returns nothing for a file with no metadata at all', async () => {
    await expect(extract(webp(), 'image/webp')).resolves.toBeNull()
  })
})

describe('C2PA', () => {
  // A gpt-image PNG carries no XMP, no IPTC and no EXIF — the manifest is all there is.
  it('hands over the manifest from each container', async () => {
    await expect(decoded(png([pngC2paChunk('jumb bytes')]), 'image/png', 'c2pa')).resolves.toBe('jumb bytes')
    await expect(decoded(webp([riffChunk('C2PA', 'jumb bytes')]), 'image/webp', 'c2pa')).resolves.toBe('jumb bytes')
    await expect(decoded(jpeg([app11('a manifest')]), 'image/jpeg', 'c2pa')).resolves.toBe('a manifest')
  })

  it('reassembles a manifest split across APP11 fragments', async () => {
    // Each fragment past the first repeats the superbox LBox/TBox, which belongs to
    // the stream once. Out of order on purpose: the sequence number is what orders it.
    const bytes = jpeg([app11('LBOXTBOXsecond', 1, 2), app11('LBOXTBOXfirst', 1, 1), app11('LBOXTBOXthird', 1, 3)])

    await expect(decoded(bytes, 'image/jpeg', 'c2pa')).resolves.toBe('LBOXTBOXfirstsecondthird')
  })

  it('does not mistake another APP11 user for a manifest', async () => {
    await expect(extract(jpeg(['\xff\xeb\x00\x08notJP']), 'image/jpeg')).resolves.toBeNull()
  })
})

describe('hostile and out-of-scope input', () => {
  it('extracts nothing from a type the compressors leave alone', async () => {
    await expect(extract(png([pngTextChunk('XML:com.adobe.xmp', 'tEXt', CREATOR_XMP)]), 'image/gif')).resolves.toBeNull()
    await expect(extract('%PDF-1.7', 'application/pdf')).resolves.toBeNull()
  })

  it('drops a segment too large to be worth carrying', async () => {
    // Past the cap the sidecar costs more than the compression saves. The rest of the
    // metadata still travels, which is why this is a drop and not a bail-out.
    const huge = 'x'.repeat(1024 * 1024 + 1)
    const bytes = png([pngC2paChunk(huge), pngTextChunk('XML:com.adobe.xmp', 'tEXt', CREATOR_XMP)])

    await expect(extract(bytes, 'image/png')).resolves.toEqual({ xmp: expect.any(String) })
  })

  it('returns null rather than throwing on truncated or wrong bytes', async () => {
    const cases = [
      ['', 'image/jpeg'],
      ['\xff\xd8', 'image/jpeg'],
      ['\x89PNG\r\n\x1a\n', 'image/png'],
      ['RIFF', 'image/webp'],
      ['not an image at all', 'image/png'],
      // A chunk claiming far more bytes than the file holds.
      ['\x89PNG\r\n\x1a\n\x7f\xff\xff\xfftEXtwhatever', 'image/png'],
    ]

    for (const [bytes, type] of cases) {
      await expect(extract(bytes, type)).resolves.toBeNull()
    }
  })
})
