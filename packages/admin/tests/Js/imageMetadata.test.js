import { describe, expect, it } from 'vitest'
import { carriesEmbeddedRights } from '../../src/Resources/assets/admin.imageMetadata.js'
import {
  CREATOR_TOOL_XMP,
  CREATOR_XMP,
  app13Iim,
  app1Exif,
  app1Xmp,
  jpeg,
  png,
  pngTextChunk,
  riffChunk,
  tiff,
  toFile,
  webp,
} from './imageFixture.js'

const EXIF_ARTIST = 0x013b
const EXIF_COPYRIGHT = 0x8298

function carries(bytes, type) {
  return carriesEmbeddedRights(toFile(bytes, type))
}

describe('JPEG', () => {
  it('reads a creator out of an XMP packet', async () => {
    await expect(carries(jpeg([app1Xmp(CREATOR_XMP)]), 'image/jpeg')).resolves.toBe(true)
  })

  it('reads a by-line out of the IPTC-IIM record', async () => {
    await expect(carries(jpeg([app13Iim([[80, 'Enrico Romanzi']])]), 'image/jpeg')).resolves.toBe(true)
  })

  it('reads Artist and Copyright out of EXIF', async () => {
    const artist = jpeg([app1Exif(tiff([{ tag: EXIF_ARTIST, value: 'Enrico Romanzi\0' }]))])
    const copyright = jpeg([app1Exif(tiff([{ tag: EXIF_COPYRIGHT, value: '(c) Altimood\0' }]))])

    await expect(carries(artist, 'image/jpeg')).resolves.toBe(true)
    await expect(carries(copyright, 'image/jpeg')).resolves.toBe(true)
  })

  it('reads an EXIF value short enough to sit inside its own entry', async () => {
    const bytes = jpeg([app1Exif(tiff([{ tag: EXIF_ARTIST, value: 'Ann' }]))])

    await expect(carries(bytes, 'image/jpeg')).resolves.toBe(true)
  })

  it('reads a big-endian EXIF block', async () => {
    const bytes = jpeg([app1Exif(tiff([{ tag: EXIF_ARTIST, value: 'Enrico Romanzi\0' }], false))])

    await expect(carries(bytes, 'image/jpeg')).resolves.toBe(true)
  })

  it('treats a blank EXIF copyright as no claim', async () => {
    // Cameras pad the field rather than omit it; keeping such a file uncompressed
    // would cost every upload from that body.
    const bytes = jpeg([app1Exif(tiff([{ tag: EXIF_COPYRIGHT, value: '    \0' }]))])

    await expect(carries(bytes, 'image/jpeg')).resolves.toBe(false)
  })

  it('ignores an XMP packet that claims nothing', async () => {
    await expect(carries(jpeg([app1Xmp(CREATOR_TOOL_XMP)]), 'image/jpeg')).resolves.toBe(false)
  })

  it('ignores a file with no metadata at all', async () => {
    await expect(carries(jpeg(), 'image/jpeg')).resolves.toBe(false)
  })
})

describe('PNG', () => {
  // The reader must not care which of the six combinations a writer picked: the chunk
  // type decides the encoding, the keyword the wrapping, and the server reads all six.
  const shapes = [
    ['XML:com.adobe.xmp', 'tEXt', false],
    ['XML:com.adobe.xmp', 'zTXt', false],
    ['XML:com.adobe.xmp', 'iTXt', false],
    ['XML:com.adobe.xmp', 'iTXt', true],
    ['Raw profile type xmp', 'tEXt', false],
    ['Raw profile type xmp', 'zTXt', false],
  ]

  it.each(shapes)('reads a creator from %s in %s (compressed: %s)', async (keyword, chunkType, compressed) => {
    const bytes = png([pngTextChunk(keyword, chunkType, CREATOR_XMP, compressed)])

    await expect(carries(bytes, 'image/png')).resolves.toBe(true)
  })

  it('ignores text chunks under an unrelated keyword', async () => {
    // The value is XMP claiming a creator; only the keyword makes it invisible, which
    // is what the server does too.
    const bytes = png([pngTextChunk('Description', 'tEXt', CREATOR_XMP)])

    await expect(carries(bytes, 'image/png')).resolves.toBe(false)
  })

  it('ignores an XMP chunk that claims nothing', async () => {
    const bytes = png([pngTextChunk('XML:com.adobe.xmp', 'zTXt', CREATOR_TOOL_XMP)])

    await expect(carries(bytes, 'image/png')).resolves.toBe(false)
  })

  it('survives a corrupt deflate stream', async () => {
    const bytes = png([pngTextChunk('XML:com.adobe.xmp', 'tEXt', 'x').replace('tEXt', 'zTXt')])

    await expect(carries(bytes, 'image/png')).resolves.toBe(false)
  })

  it('ignores a file with no metadata at all', async () => {
    await expect(carries(png(), 'image/png')).resolves.toBe(false)
  })
})

describe('WebP', () => {
  it('reads a creator out of the XMP chunk', async () => {
    await expect(carries(webp([riffChunk('XMP ', CREATOR_XMP)]), 'image/webp')).resolves.toBe(true)
  })

  it('walks past an odd-sized chunk to reach the XMP one', async () => {
    // The pad byte is not counted in the size, so mishandling it shifts every chunk
    // after it and the walk silently finds nothing.
    const bytes = webp([riffChunk('EXIF', 'odd'), riffChunk('XMP ', CREATOR_XMP)])

    await expect(carries(bytes, 'image/webp')).resolves.toBe(true)
  })

  it('ignores an XMP chunk that claims nothing', async () => {
    await expect(carries(webp([riffChunk('XMP ', CREATOR_TOOL_XMP)]), 'image/webp')).resolves.toBe(false)
  })

  it('ignores a file with no metadata at all', async () => {
    await expect(carries(webp(), 'image/webp')).resolves.toBe(false)
  })
})

describe('hostile and out-of-scope input', () => {
  it('never claims rights for a type the compressors leave alone', async () => {
    await expect(carries(png([pngTextChunk('XML:com.adobe.xmp', 'tEXt', CREATOR_XMP)]), 'image/gif')).resolves.toBe(false)
    await expect(carries('%PDF-1.7', 'application/pdf')).resolves.toBe(false)
  })

  it('returns false rather than throwing on truncated or wrong bytes', async () => {
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
      await expect(carries(bytes, type)).resolves.toBe(false)
    }
  })
})
