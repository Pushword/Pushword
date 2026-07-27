/**
 * Detects, before upload, whether an image carries rights metadata in its bytes.
 *
 * Both upload paths re-encode images through a canvas to scale them down, and a
 * canvas keeps no metadata. That is a fine trade for a plain photo and a bad one for
 * a file whose XMP, IPTC or EXIF block claims somebody's rights: the server reads those
 * to decide whether it may apply the site's own license, so stripping them silently
 * turns a third party's photo into a site-licensed one.
 *
 * A heuristic on purpose — it only decides whether to hand the server the original
 * bytes. EmbeddedRightsReader stays the authority on what those bytes mean. It walks the
 * same three containers ImageContainer does, because a source it cannot see is a claim
 * it destroys.
 *
 * XMP, IPTC and EXIF are read for what they say, since every camera writes some of all
 * three and only a few of those bytes are a rights claim. A C2PA manifest is taken at
 * its mere presence: nothing writes one by accident, and looking inside would mean a
 * JUMBF and CBOR reader in the browser to answer a question the server re-asks anyway.
 */

const LATIN1 = new TextDecoder('latin1')

// Segment markers are walked, never string-matched against the whole payload:
// compressed scan data, an EXIF thumbnail (itself a JPEG) or a COM segment can all
// contain the literals we look for.
const SOI = 0xffd8
const SOS = 0xffda
const APP1 = 0xffe1
const APP11 = 0xffeb
const APP13 = 0xffed

const XMP_SIGNATURE = 'http://ns.adobe.com/xap/1.0/\0'
const EXIF_SIGNATURE = 'Exif\0\0'
const PHOTOSHOP_SIGNATURE = 'Photoshop 3.0\0'
// C2PA rides in an APP11 fragment, a PNG caBX chunk or a WebP C2PA chunk. The JPEG one
// opens with `JP`, without which an APP11 belongs to some other JUMBF user.
const JUMBF_SIGNATURE = 'JP'
const PNG_C2PA_CHUNK = 'caBX'
const WEBP_C2PA_CHUNK = 'C2PA'

// Local names, each kept with its colon so `dc:creator` does not also match
// `xmp:CreatorTool`, and `photoshop:Credit` does not match a word in a caption.
const XMP_RIGHTS_MARKERS = [':creator', ':rights', ':Credit', ':WebStatement', ':LicensorURL', ':UsageTerms']

// IPTC-IIM record 2 datasets: By-line (80), Credit (110), Copyright notice (116).
const IIM_RIGHTS_DATASETS = [80, 110, 116]

// The two EXIF IFD0 tags EmbeddedRightsReader reads, and the ASCII type they carry.
const EXIF_ARTIST = 0x013b
const EXIF_COPYRIGHT = 0x8298
const EXIF_ASCII = 2

// PNG carries XMP under the specified keyword and under ImageMagick's own, which wraps
// the packet in hex. Both are read server-side, so both are looked for here.
const PNG_RAW_PROFILE_KEYWORD = 'Raw profile type xmp'
const PNG_XMP_KEYWORDS = ['XML:com.adobe.xmp', PNG_RAW_PROFILE_KEYWORD]

const HEAD_BYTES = 1024 * 1024

function readAscii(view, offset, length) {
  return LATIN1.decode(new Uint8Array(view.buffer, offset, length))
}

function claimsRights(packet) {
  return XMP_RIGHTS_MARKERS.some((marker) => packet.includes(marker))
}

function xmpClaimsRights(view, offset, length) {
  return claimsRights(readAscii(view, offset, length))
}

/**
 * Walk the 8BIM blocks of an APP13 payload and look for rights datasets inside the
 * IPTC-IIM resource (0x0404).
 */
function iimClaimsRights(view, offset, end) {
  let cursor = offset
  while (cursor + 12 < end) {
    if (readAscii(view, cursor, 4) !== '8BIM') {
      cursor += 1
      continue
    }
    const resourceId = view.getUint16(cursor + 4)
    const nameLength = view.getUint8(cursor + 6) + 1
    // The Pascal-style name is padded to an even length, header included.
    const padded = nameLength % 2 === 0 ? nameLength : nameLength + 1
    let block = cursor + 6 + padded
    const size = view.getUint32(block)
    block += 4

    if (resourceId === 0x0404) {
      const blockEnd = Math.min(block + size, end)
      for (let i = block; i + 4 < blockEnd; i += 1) {
        if (view.getUint8(i) === 0x1c && view.getUint8(i + 1) === 0x02 && IIM_RIGHTS_DATASETS.includes(view.getUint8(i + 2))) {
          return true
        }
      }
    }

    cursor = block + size + (size % 2)
  }
  return false
}

/**
 * Artist and Copyright out of IFD0. A camera that wrote four spaces into Copyright has
 * written nothing — the server trims the value too, and a blank claim must not be what
 * pins a file to its original bytes.
 */
function exifClaimsRights(view, offset, end) {
  if (offset + 8 > end) return false

  const order = readAscii(view, offset, 2)
  if (order !== 'II' && order !== 'MM') return false

  // The byte order applies to every field from here on, the TIFF header included.
  const little = order === 'II'
  if (view.getUint16(offset + 2, little) !== 42) return false

  const ifd = offset + view.getUint32(offset + 4, little)
  if (ifd + 2 > end) return false

  const entries = view.getUint16(ifd, little)
  for (let i = 0; i < entries; i += 1) {
    const entry = ifd + 2 + i * 12
    if (entry + 12 > end) return false

    const tag = view.getUint16(entry, little)
    if (tag !== EXIF_ARTIST && tag !== EXIF_COPYRIGHT) continue
    if (view.getUint16(entry + 2, little) !== EXIF_ASCII) continue

    const length = view.getUint32(entry + 4, little)
    // Up to four bytes sit in the entry itself; anything longer at an offset counted
    // from the start of the TIFF header.
    const value = length <= 4 ? entry + 8 : offset + view.getUint32(entry + 8, little)
    if (value + length > end) continue

    if (readAscii(view, value, length).replaceAll('\0', '').trim() !== '') return true
  }

  return false
}

function jpegCarriesRights(view) {
  if (view.byteLength < 4 || view.getUint16(0) !== SOI) return false

  let cursor = 2
  while (cursor + 4 <= view.byteLength) {
    const marker = view.getUint16(cursor)
    if (marker === SOS || (marker & 0xff00) !== 0xff00) return false

    const length = view.getUint16(cursor + 2)
    const payload = cursor + 4
    const payloadEnd = cursor + 2 + length

    // Before the bounds check below: a manifest is split over as many fragments as it
    // needs and the first one alone already settles the question.
    if (marker === APP11 && payload + JUMBF_SIGNATURE.length <= view.byteLength && readAscii(view, payload, JUMBF_SIGNATURE.length) === JUMBF_SIGNATURE) {
      return true
    }

    if (payloadEnd > view.byteLength) return false

    if (marker === APP1 && length > XMP_SIGNATURE.length && readAscii(view, payload, XMP_SIGNATURE.length) === XMP_SIGNATURE) {
      if (xmpClaimsRights(view, payload + XMP_SIGNATURE.length, payloadEnd - payload - XMP_SIGNATURE.length)) return true
    }

    if (marker === APP1 && length > EXIF_SIGNATURE.length && readAscii(view, payload, EXIF_SIGNATURE.length) === EXIF_SIGNATURE) {
      if (exifClaimsRights(view, payload + EXIF_SIGNATURE.length, payloadEnd)) return true
    }

    if (marker === APP13 && length > PHOTOSHOP_SIGNATURE.length && readAscii(view, payload, PHOTOSHOP_SIGNATURE.length) === PHOTOSHOP_SIGNATURE) {
      if (iimClaimsRights(view, payload + PHOTOSHOP_SIGNATURE.length, payloadEnd)) return true
    }

    cursor = payloadEnd
  }

  return false
}

/**
 * PNG text chunks are zlib streams often enough that reading only the plain ones misses
 * whatever ImageMagick wrote. A stream we fail to inflate is one the server fails to
 * inflate too, so nothing is lost by treating it as empty.
 */
async function inflate(bytes) {
  try {
    const stream = new Blob([bytes]).stream().pipeThrough(new DecompressionStream('deflate'))

    return LATIN1.decode(await new Response(stream).arrayBuffer())
  } catch {
    return null
  }
}

/**
 * ImageMagick's wrapper: a leading newline, the profile name, the byte count in eight
 * columns, then the packet as hex split over lines.
 */
function rawProfile(profile) {
  const lines = profile.replace(/^\n+|\n+$/g, '').split('\n')
  if (lines.length < 3) return null

  const hex = lines.slice(2).join('').replace(/[\s\r]/g, '')
  if (hex.length === 0 || hex.length % 2 === 1 || /[^0-9a-fA-F]/.test(hex)) return null

  let packet = ''
  for (let i = 0; i < hex.length; i += 2) packet += String.fromCharCode(parseInt(hex.slice(i, i + 2), 16))

  return packet
}

/**
 * A compression flag, a compression method, then the language tag and the translated
 * keyword — the last two null-terminated and both skippable.
 */
async function iTXtText(rest) {
  const compressed = rest[0] === 1

  let body = rest.subarray(2)
  for (let skipped = 0; skipped < 2; skipped += 1) {
    const end = body.indexOf(0)
    if (end === -1) return null
    body = body.subarray(end + 1)
  }

  return compressed ? inflate(body) : LATIN1.decode(body)
}

/**
 * The chunk type decides the encoding and the keyword decides the wrapping — the same
 * split ImageContainer makes, so either can appear with either.
 */
async function pngText(type, chunk) {
  const separator = chunk.indexOf(0)
  if (separator === -1) return null

  const keyword = LATIN1.decode(chunk.subarray(0, separator))
  if (!PNG_XMP_KEYWORDS.includes(keyword)) return null

  const rest = chunk.subarray(separator + 1)
  const text =
    type === 'tEXt'
      ? LATIN1.decode(rest)
      : // zTXt is one compression-method byte, then a zlib stream.
        await (type === 'zTXt' ? inflate(rest.subarray(1)) : iTXtText(rest))

  if (text === null) return null

  return keyword === PNG_RAW_PROFILE_KEYWORD ? rawProfile(text) : text
}

async function pngCarriesRights(view) {
  let cursor = 8 // PNG signature
  while (cursor + 8 <= view.byteLength) {
    const length = view.getUint32(cursor)
    const type = readAscii(view, cursor + 4, 4)
    const payload = cursor + 8

    // Metadata precedes the pixels, and IDAT is also where the head slice runs out.
    if (type === 'IDAT') return false
    // Before the bounds check: a manifest carrying a thumbnail can outrun the slice,
    // and its presence is the whole answer.
    if (type === PNG_C2PA_CHUNK) return true
    if (payload + length > view.byteLength) return false

    if (type === 'iTXt' || type === 'zTXt' || type === 'tEXt') {
      const text = await pngText(type, new Uint8Array(view.buffer, payload, length))
      if (text !== null && claimsRights(text)) return true
    }

    cursor = payload + length + 4 // + CRC
  }
  return false
}

function webpCarriesRights(view) {
  if (view.byteLength < 12) return false
  if (readAscii(view, 0, 4) !== 'RIFF' || readAscii(view, 8, 4) !== 'WEBP') return false

  let cursor = 12
  while (cursor + 8 <= view.byteLength) {
    const type = readAscii(view, cursor, 4)
    // RIFF, and only RIFF, is little-endian.
    const size = view.getUint32(cursor + 4, true)
    const payload = cursor + 8

    if (type === WEBP_C2PA_CHUNK) return true
    if (payload + size > view.byteLength) return false

    if (type === 'XMP ' && xmpClaimsRights(view, payload, size)) return true

    // An odd-sized chunk is followed by a pad byte that is not counted in its size.
    cursor = payload + size + (size % 2)
  }

  return false
}

export async function carriesEmbeddedRights(file) {
  try {
    const view = new DataView(await file.slice(0, HEAD_BYTES).arrayBuffer())

    if (file.type === 'image/jpeg') return jpegCarriesRights(view)
    if (file.type === 'image/png') return await pngCarriesRights(view)
    if (file.type === 'image/webp') return webpCarriesRights(view)

    return false
  } catch {
    return false
  }
}
