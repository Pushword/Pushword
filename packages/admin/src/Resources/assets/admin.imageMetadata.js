/**
 * Lifts the metadata segments out of an image before the browser re-encodes it.
 *
 * Both upload paths scale images down through a canvas, and a canvas keeps no metadata.
 * Rather than give up the scaling for the files that carry rights — which are also the
 * ones most likely to exceed `upload_max_filesize` once kept whole — the segments are
 * taken out here and posted alongside the compressed bytes. The server parses them with
 * the same readers it runs on a file it received intact.
 *
 * Nothing here decides what the bytes mean: no marker matching, no "does this claim
 * rights". Whatever a container holds is handed over and EmbeddedRightsReader stays the
 * single authority, so the two paths cannot drift. What the stored file itself says
 * still wins on the server, so this can only add to a decision, never overrule it.
 */

const LATIN1 = new TextDecoder('latin1')

// Segments are walked, never string-matched against the whole payload: compressed scan
// data, an EXIF thumbnail (itself a JPEG) or a COM segment can all contain the literals
// we look for.
const SOI = 0xffd8
const SOS = 0xffda
const APP1 = 0xffe1
const APP11 = 0xffeb
const APP13 = 0xffed

const XMP_SIGNATURE = 'http://ns.adobe.com/xap/1.0/\0'
const EXIF_SIGNATURE = 'Exif\0\0'
// C2PA rides in an APP11 fragment, a PNG caBX chunk or a WebP C2PA chunk. The JPEG one
// opens with `JP`, without which an APP11 belongs to some other JUMBF user.
const JUMBF_SIGNATURE = 'JP'
const PNG_C2PA_CHUNK = 'caBX'
const WEBP_C2PA_CHUNK = 'C2PA'

// The two EXIF IFD0 tags EmbeddedRightsReader reads, and the ASCII type they carry.
const EXIF_ARTIST = 0x013b
const EXIF_COPYRIGHT = 0x8298
const EXIF_ASCII = 2

// PNG carries XMP under the specified keyword and under ImageMagick's own, which wraps
// the packet in hex. Both are read server-side, so both are looked for here.
const PNG_RAW_PROFILE_KEYWORD = 'Raw profile type xmp'
const PNG_XMP_KEYWORDS = ['XML:com.adobe.xmp', PNG_RAW_PROFILE_KEYWORD]

/**
 * A manifest embedding its ingredients' thumbnails has no natural size limit, and past
 * this one the sidecar costs more than the compression saves. Such a segment is dropped
 * rather than sent: the rest of the metadata still travels.
 */
const MAX_SEGMENT = 1024 * 1024

function ascii(view, offset, length) {
  return LATIN1.decode(new Uint8Array(view.buffer, offset, length))
}

function slice(view, offset, length) {
  return new Uint8Array(view.buffer, offset, length)
}

function startsWith(view, offset, available, signature) {
  return available > signature.length && ascii(view, offset, signature.length) === signature
}

function concat(chunks) {
  const total = chunks.reduce((sum, chunk) => sum + chunk.length, 0)
  const out = new Uint8Array(total)
  let at = 0
  for (const chunk of chunks) {
    out.set(chunk, at)
    at += chunk.length
  }
  return out
}

/**
 * btoa() takes a string, and spreading a whole segment into fromCharCode overflows the
 * argument stack, so it goes a window at a time.
 */
function base64(bytes) {
  const WINDOW = 0x8000
  let binary = ''
  for (let i = 0; i < bytes.length; i += WINDOW) {
    binary += String.fromCharCode(...bytes.subarray(i, i + WINDOW))
  }
  return btoa(binary)
}

// --- JPEG ---

/**
 * Artist and Copyright out of IFD0, as their own bytes. Everything else EXIF carries is
 * left behind — those two are the only tags EmbeddedRightsReader looks at.
 */
function exifValues(view, offset, end) {
  const found = {}
  if (offset + 8 > end) return found

  const order = ascii(view, offset, 2)
  if (order !== 'II' && order !== 'MM') return found

  // The byte order applies to every field from here on, the TIFF header included.
  const little = order === 'II'
  if (view.getUint16(offset + 2, little) !== 42) return found

  const ifd = offset + view.getUint32(offset + 4, little)
  if (ifd + 2 > end) return found

  const entries = view.getUint16(ifd, little)
  for (let i = 0; i < entries; i += 1) {
    const entry = ifd + 2 + i * 12
    if (entry + 12 > end) return found

    const tag = view.getUint16(entry, little)
    if (tag !== EXIF_ARTIST && tag !== EXIF_COPYRIGHT) continue
    if (view.getUint16(entry + 2, little) !== EXIF_ASCII) continue

    const length = view.getUint32(entry + 4, little)
    // Up to four bytes sit in the entry itself; anything longer at an offset counted
    // from the start of the TIFF header.
    const value = length <= 4 ? entry + 8 : offset + view.getUint32(entry + 8, little)
    if (length === 0 || value + length > end) continue

    found[tag === EXIF_ARTIST ? 'artist' : 'copyright'] ??= slice(view, value, length)
  }

  return found
}

/**
 * ISO 19566-5 splits one JUMBF superbox across APP11 segments, each prefixed with `JP`,
 * a box instance and a packet sequence, and each repeating the superbox LBox/TBox. Only
 * the first fragment's header is kept — the same rule ImageContainer applies.
 */
function reassembleApp11(fragments) {
  if (fragments.length === 0) return null

  // The first instance met, not the lowest-numbered: a second box is a second
  // manifest, and ImageContainer keeps the one it reached first too.
  const { instance } = fragments[0]
  const ordered = fragments.filter((fragment) => fragment.instance === instance).sort((a, b) => a.sequence - b.sequence)

  return concat(ordered.map(({ sequence, bytes }) => (sequence === 1 ? bytes : bytes.subarray(8))))
}

function jpegSegments(view) {
  const found = {}
  const fragments = []

  if (view.byteLength < 4 || view.getUint16(0) !== SOI) return found

  let cursor = 2
  while (cursor + 4 <= view.byteLength) {
    const marker = view.getUint16(cursor)
    if (marker === SOS || (marker & 0xff00) !== 0xff00) break

    const length = view.getUint16(cursor + 2)
    const payload = cursor + 4
    const payloadEnd = cursor + 2 + length
    if (payloadEnd > view.byteLength) break

    const size = payloadEnd - payload

    if (marker === APP1 && startsWith(view, payload, size, XMP_SIGNATURE)) {
      found.xmp ??= slice(view, payload + XMP_SIGNATURE.length, size - XMP_SIGNATURE.length)
    } else if (marker === APP1 && startsWith(view, payload, size, EXIF_SIGNATURE)) {
      for (const [name, value] of Object.entries(exifValues(view, payload + EXIF_SIGNATURE.length, payloadEnd))) {
        found[name] ??= value
      }
    } else if (marker === APP13) {
      // iptcparse() takes the APP13 payload whole, Photoshop signature included.
      found.iptc ??= slice(view, payload, size)
    } else if (marker === APP11 && startsWith(view, payload, size, JUMBF_SIGNATURE) && size >= 16) {
      fragments.push({
        instance: view.getUint16(payload + 2),
        sequence: view.getUint32(payload + 4),
        bytes: slice(view, payload + 8, size - 8),
      })
    }

    cursor = payloadEnd
  }

  const c2pa = reassembleApp11(fragments)
  if (c2pa !== null) found.c2pa = c2pa

  return found
}

// --- PNG ---

/**
 * PNG text chunks are zlib streams often enough that reading only the plain ones misses
 * whatever ImageMagick wrote. A stream we fail to inflate is one the server fails to
 * inflate too, so nothing is lost by dropping it.
 */
async function inflate(bytes) {
  try {
    const stream = new Blob([bytes]).stream().pipeThrough(new DecompressionStream('deflate'))

    return new Uint8Array(await new Response(stream).arrayBuffer())
  } catch {
    return null
  }
}

/**
 * ImageMagick's wrapper: a leading newline, the profile name, the byte count in eight
 * columns, then the packet as hex split over lines.
 */
function rawProfile(profile) {
  const lines = LATIN1.decode(profile)
    .replace(/^\n+|\n+$/g, '')
    .split('\n')
  if (lines.length < 3) return null

  const hex = lines.slice(2).join('').replace(/[\s\r]/g, '')
  if (hex.length === 0 || hex.length % 2 === 1 || /[^0-9a-fA-F]/.test(hex)) return null

  const packet = new Uint8Array(hex.length / 2)
  for (let i = 0; i < packet.length; i += 1) packet[i] = parseInt(hex.slice(i * 2, i * 2 + 2), 16)

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

  return compressed ? inflate(body) : body
}

function decodeChunk(type, rest) {
  if (type === 'tEXt') return rest
  // zTXt is one compression-method byte, then a zlib stream.
  if (type === 'zTXt') return inflate(rest.subarray(1))

  return iTXtText(rest)
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

  const text = await decodeChunk(type, chunk.subarray(separator + 1))
  if (text === null) return null

  return keyword === PNG_RAW_PROFILE_KEYWORD ? rawProfile(text) : text
}

async function pngSegments(view) {
  const found = {}

  let cursor = 8 // PNG signature
  while (cursor + 8 <= view.byteLength) {
    const length = view.getUint32(cursor)
    const type = ascii(view, cursor + 4, 4)
    const payload = cursor + 8

    // Metadata precedes the pixels; past IDAT there is nothing left to find.
    if (type === 'IDAT') break
    if (payload + length > view.byteLength) break

    if (type === PNG_C2PA_CHUNK) {
      found.c2pa ??= slice(view, payload, length)
    } else if (type === 'iTXt' || type === 'zTXt' || type === 'tEXt') {
      const packet = await pngText(type, slice(view, payload, length))
      if (packet !== null) found.xmp ??= packet
    }

    cursor = payload + length + 4 // + CRC
  }

  return found
}

// --- WebP ---

function webpSegments(view) {
  const found = {}
  if (view.byteLength < 12) return found
  if (ascii(view, 0, 4) !== 'RIFF' || ascii(view, 8, 4) !== 'WEBP') return found

  let cursor = 12
  while (cursor + 8 <= view.byteLength) {
    const type = ascii(view, cursor, 4)
    // RIFF, and only RIFF, is little-endian.
    const size = view.getUint32(cursor + 4, true)
    const payload = cursor + 8
    if (payload + size > view.byteLength) break

    if (type === 'XMP ') found.xmp ??= slice(view, payload, size)
    else if (type === WEBP_C2PA_CHUNK) found.c2pa ??= slice(view, payload, size)

    // An odd-sized chunk is followed by a pad byte that its size does not count.
    cursor = payload + size + (size % 2)
  }

  return found
}

/**
 * The three containers that can carry metadata and that the compressors re-encode.
 * GIF is the fourth image kind Pushword accepts and is deliberately absent, the same
 * way ImageContainer leaves it out: nothing writes metadata there.
 */
const WALKERS = {
  'image/jpeg': jpegSegments,
  'image/png': pngSegments,
  'image/webp': webpSegments,
}

/**
 * Every metadata segment the file carries, base64 encoded, or null when it carries
 * none. Base64 throughout rather than text for the XMP: a packet is UTF-8 and must
 * reach the server byte for byte, which a JSON round-trip through a latin1 decode
 * would not guarantee.
 *
 * @returns {Promise<null | {xmp?: string, iptc?: string, c2pa?: string, artist?: string, copyright?: string}>}
 */
export async function extractEmbeddedMetadata(file) {
  const walk = WALKERS[file.type]
  if (walk === undefined) return null

  try {
    const segments = await walk(new DataView(await file.arrayBuffer()))

    const encoded = {}
    for (const [name, bytes] of Object.entries(segments)) {
      if (bytes.length > 0 && bytes.length <= MAX_SEGMENT) encoded[name] = base64(bytes)
    }

    return Object.keys(encoded).length > 0 ? encoded : null
  } catch {
    return null
  }
}
