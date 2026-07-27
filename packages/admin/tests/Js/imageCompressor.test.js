// @vitest-environment jsdom
import { beforeEach, describe, expect, it } from 'vitest'
import { carryMetadata } from '../../src/Resources/assets/admin.imageCompressor.js'

/**
 * The single-file path posts a form rather than a FormData, so the segments ride in a
 * hidden input added to the page. FormData is rebuilt per upload and cannot go stale;
 * this field is reused across every file the editor picks, and can.
 */
describe('carryMetadata', () => {
  let input

  beforeEach(() => {
    document.body.innerHTML = '<form name="Media"><input type="file" id="Media_mediaFile"></form>'
    input = document.getElementById('Media_mediaFile')
  })

  const field = () => document.querySelector('input[name="embeddedMetadata"]')

  it('adds one hidden field carrying the segments', () => {
    carryMetadata(input, { xmp: 'YQ==' })

    expect(field().type).toBe('hidden')
    expect(JSON.parse(field().value)).toEqual({ xmp: 'YQ==' })
  })

  it('sits outside the form namespace so the form never sees a field it cannot map', () => {
    // Named `Media[…]`, Symfony would reject the whole submission as carrying extra
    // fields — the form has no mapping for it and never will.
    carryMetadata(input, { xmp: 'YQ==' })

    expect(field().name).toBe('embeddedMetadata')
    expect(field().name.startsWith('Media[')).toBe(false)
  })

  it('rewrites the one field rather than piling them up', () => {
    carryMetadata(input, { xmp: 'YQ==' })
    carryMetadata(input, { c2pa: 'Yg==' })

    expect(document.querySelectorAll('input[name="embeddedMetadata"]')).toHaveLength(1)
    expect(JSON.parse(field().value)).toEqual({ c2pa: 'Yg==' })
  })

  it('empties the field when the file picked next carries nothing', () => {
    // The case worth the test: changing your mind about which file to upload must not
    // post the first one's photographer alongside the second one's pixels.
    carryMetadata(input, { xmp: 'YQ==' })
    carryMetadata(input, null)

    expect(field().value).toBe('')
  })

  it('does nothing for an input outside any form', () => {
    document.body.innerHTML = '<input type="file" id="loose">'

    carryMetadata(document.getElementById('loose'), { xmp: 'YQ==' })

    expect(field()).toBeNull()
  })
})
