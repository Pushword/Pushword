import { deflateSync } from 'node:zlib'

/**
 * Builds the three containers admin.imageMetadata walks, byte for byte.
 *
 * The JS counterpart of tests/Image/License/ImageMetadataFixture.php in core: same
 * segments, same wrappers, so a case proven on one side can be written on the other.
 * Everything is assembled as a latin1 string, one character per byte.
 */

const XMP_SIGNATURE = 'http://ns.adobe.com/xap/1.0/\0'
const EXIF_SIGNATURE = 'Exif\0\0'
const PHOTOSHOP_SIGNATURE = 'Photoshop 3.0\0'

function u16(value, little = false) {
  const bytes = [(value >> 8) & 0xff, value & 0xff]
  return String.fromCharCode(...(little ? bytes.reverse() : bytes))
}

function u32(value, little = false) {
  const bytes = [(value >>> 24) & 0xff, (value >>> 16) & 0xff, (value >>> 8) & 0xff, value & 0xff]
  return String.fromCharCode(...(little ? bytes.reverse() : bytes))
}

function deflate(text) {
  return deflateSync(Buffer.from(text, 'latin1')).toString('latin1')
}

export function xmpPacket(body) {
  return `<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>
<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
 xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:xmp="http://ns.adobe.com/xap/1.0/">
<rdf:Description rdf:about="">${body}</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>`
}

export const CREATOR_XMP = xmpPacket('<dc:creator><rdf:Seq><rdf:li>Enrico Romanzi</rdf:li></rdf:Seq></dc:creator>')

// Every camera and phone writes this one; on its own it claims nothing.
export const CREATOR_TOOL_XMP = xmpPacket('<xmp:CreatorTool>Pushword</xmp:CreatorTool>')

// --- JPEG ---

function segment(marker, payload) {
  return u16(marker) + u16(payload.length + 2) + payload
}

export function app1Xmp(packet) {
  return segment(0xffe1, XMP_SIGNATURE + packet)
}

export function app1Exif(tiff) {
  return segment(0xffe1, EXIF_SIGNATURE + tiff)
}

/**
 * One 8BIM resource holding the IPTC-IIM record, itself a run of
 * `0x1C, record, dataset, length, value` entries.
 *
 * @param {Array<[number, string]>} datasets dataset number and its value
 */
export function app13Iim(datasets) {
  const iim = datasets.map(([dataset, value]) => '\x1c\x02' + String.fromCharCode(dataset) + u16(value.length) + value).join('')
  // '8BIM', resource id, an empty even-padded Pascal name, then the size.
  const resource = '8BIM' + u16(0x0404) + '\0\0' + u32(iim.length) + iim + (iim.length % 2 === 1 ? '\0' : '')

  return segment(0xffed, PHOTOSHOP_SIGNATURE + resource)
}

/**
 * @param {Array<{tag: number, value: string, type?: number}>} tags
 */
export function tiff(tags, little = true) {
  const directory = 2 + tags.length * 12 + 4
  let values = ''

  const entries = tags.map(({ tag, value, type = 2 }) => {
    // Four bytes or fewer live in the entry itself; longer values at an offset
    // counted from the start of the TIFF header.
    let inline
    if (value.length <= 4) {
      inline = value.padEnd(4, '\0')
    } else {
      inline = u32(8 + directory + values.length, little)
      values += value
    }

    return u16(tag, little) + u16(type, little) + u32(value.length, little) + inline
  })

  return (little ? 'II' : 'MM') + u16(42, little) + u32(8, little) + u16(tags.length, little) + entries.join('') + u32(0, little) + values
}

/**
 * One APP11 fragment of a manifest: `JP`, the instance, the sequence, then the JUMBF
 * bytes. Nothing here looks inside, so the payload only has to be plausible.
 */
export function app11(payload, instance = 1, sequence = 1) {
  return segment(0xffeb, 'JP' + u16(instance) + u32(sequence) + payload)
}

export function jpeg(segments = []) {
  // SOS ends the walk, so nothing past it needs to be a real scan.
  return '\xff\xd8' + segments.join('') + '\xff\xda\x00\x02' + '\xff\xd9'
}

// --- PNG ---

/**
 * The CRC is left at zero: no reader here validates it, and a real one would only
 * add a dependency to the fixture.
 */
function pngChunk(type, data) {
  return u32(data.length) + type + data + u32(0)
}

/**
 * ImageMagick's hex wrapper — a leading newline, the profile name, the byte count in
 * eight columns, then the packet as hex.
 */
export function rawProfile(packet) {
  const hex = Buffer.from(packet, 'latin1').toString('hex')

  return '\n' + 'xmp' + '\n' + String(packet.length).padStart(8, ' ') + '\n' + (hex.match(/.{1,72}/g) ?? []).join('\n') + '\n'
}

/**
 * The keyword decides how the text is wrapped, the chunk type how it is encoded — the
 * same split the reader makes, so either can appear with either.
 */
export function pngTextChunk(keyword, chunkType, text, compressed = false) {
  const payload = keyword === 'Raw profile type xmp' ? rawProfile(text) : text

  if (chunkType === 'tEXt') return pngChunk('tEXt', keyword + '\0' + payload)
  if (chunkType === 'zTXt') return pngChunk('zTXt', keyword + '\0\0' + deflate(payload))

  // iTXt: compression flag, compression method, language tag, translated keyword.
  return pngChunk('iTXt', keyword + '\0' + (compressed ? '\x01' : '\0') + '\0\0\0' + (compressed ? deflate(payload) : payload))
}

export function pngC2paChunk(payload) {
  return pngChunk('caBX', payload)
}

export function png(chunks = []) {
  const ihdr = u32(1) + u32(1) + '\x08\x02\0\0\0'

  return '\x89PNG\r\n\x1a\n' + pngChunk('IHDR', ihdr) + chunks.join('') + pngChunk('IDAT', deflate('\0\0\0\0')) + pngChunk('IEND', '')
}

// --- WebP ---

export function riffChunk(fourCC, data) {
  // An odd-sized chunk is followed by a pad byte that its size does not count.
  return fourCC + u32(data.length, true) + data + (data.length % 2 === 1 ? '\0' : '')
}

export function webp(chunks = []) {
  const body = 'WEBP' + riffChunk('VP8L', '\x2f\0\0\0\0') + chunks.join('')

  return 'RIFF' + u32(body.length, true) + body
}

// --- assembly ---

export function toFile(bytes, type) {
  return new File([Buffer.from(bytes, 'latin1')], 'image', { type })
}
