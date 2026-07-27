/**
 * Detects, before upload, whether an image carries rights metadata in its bytes.
 *
 * Both upload paths re-encode images through a canvas to scale them down, and a
 * canvas keeps no metadata. That is a fine trade for a plain photo and a bad one for
 * a file whose XMP or IPTC block claims somebody's rights: the server reads those to
 * decide whether it may apply the site's own license, so stripping them silently
 * turns a third party's photo into a site-licensed one.
 *
 * A heuristic on purpose — it only decides whether to hand the server the original
 * bytes. EmbeddedRightsReader stays the authority on what those bytes mean.
 */

// Segment markers are walked, never string-matched against the whole payload:
// compressed scan data, an EXIF thumbnail (itself a JPEG) or a COM segment can all
// contain the literals we look for.
const SOI = 0xffd8
const SOS = 0xffda
const APP1 = 0xffe1
const APP13 = 0xffed

const XMP_SIGNATURE = 'http://ns.adobe.com/xap/1.0/\0'
const PHOTOSHOP_SIGNATURE = 'Photoshop 3.0\0'

// Local names, each kept with its colon so `dc:creator` does not also match
// `xmp:CreatorTool`, and `photoshop:Credit` does not match a word in a caption.
const XMP_RIGHTS_MARKERS = [':creator', ':rights', ':Credit', ':WebStatement', ':LicensorURL', ':UsageTerms']

// IPTC-IIM record 2 datasets: By-line (80), Credit (110), Copyright notice (116).
const IIM_RIGHTS_DATASETS = [80, 110, 116]

const HEAD_BYTES = 1024 * 1024

function readAscii(view, offset, length) {
  let out = ''
  for (let i = 0; i < length; i += 1) out += String.fromCharCode(view.getUint8(offset + i))
  return out
}

function xmpClaimsRights(view, offset, length) {
  const packet = readAscii(view, offset, length)
  return XMP_RIGHTS_MARKERS.some((marker) => packet.includes(marker))
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

function jpegCarriesRights(view) {
  if (view.byteLength < 4 || view.getUint16(0) !== SOI) return false

  let cursor = 2
  while (cursor + 4 <= view.byteLength) {
    const marker = view.getUint16(cursor)
    if (marker === SOS || (marker & 0xff00) !== 0xff00) return false

    const length = view.getUint16(cursor + 2)
    const payload = cursor + 4
    const payloadEnd = cursor + 2 + length
    if (payloadEnd > view.byteLength) return false

    if (marker === APP1 && length > XMP_SIGNATURE.length && readAscii(view, payload, XMP_SIGNATURE.length) === XMP_SIGNATURE) {
      if (xmpClaimsRights(view, payload + XMP_SIGNATURE.length, payloadEnd - payload - XMP_SIGNATURE.length)) return true
    }

    if (marker === APP13 && length > PHOTOSHOP_SIGNATURE.length && readAscii(view, payload, PHOTOSHOP_SIGNATURE.length) === PHOTOSHOP_SIGNATURE) {
      if (iimClaimsRights(view, payload + PHOTOSHOP_SIGNATURE.length, payloadEnd)) return true
    }

    cursor = payloadEnd
  }

  return false
}

function pngCarriesRights(view) {
  let cursor = 8 // PNG signature
  while (cursor + 8 <= view.byteLength) {
    const length = view.getUint32(cursor)
    const type = readAscii(view, cursor + 4, 4)
    const payload = cursor + 8
    if (payload + length > view.byteLength) return false

    if ((type === 'iTXt' || type === 'tEXt') && xmpClaimsRights(view, payload, length)) return true
    if (type === 'IDAT') return false

    cursor = payload + length + 4 // + CRC
  }
  return false
}

export async function carriesEmbeddedRights(file) {
  if (file.type !== 'image/jpeg' && file.type !== 'image/png') return false

  try {
    const view = new DataView(await file.slice(0, HEAD_BYTES).arrayBuffer())
    return file.type === 'image/jpeg' ? jpegCarriesRights(view) : pngCarriesRights(view)
  } catch {
    return false
  }
}
