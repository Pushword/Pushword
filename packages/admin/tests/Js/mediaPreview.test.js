// @vitest-environment jsdom
import { beforeEach, expect, it } from 'vitest'
import { initMediaPreviews } from '../../src/Resources/assets/admin.mediaPreview.js'

let image
beforeEach(() => {
  document.body.innerHTML = '<a class="mosaic-inner-box"><img data-pw-media-preview src="image.jpg"><span class="media-mosaic__unavailable" hidden>Preview unavailable</span></a>'
  image = document.querySelector('img')
})

it('replaces a failing preview with readable guidance while keeping its edit link', () => {
  initMediaPreviews()
  image.dispatchEvent(new Event('error'))
  expect(image.hidden).toBe(true)
  expect(image.closest('a').classList.contains('pw-preview-unavailable')).toBe(true)
  expect(document.querySelector('.media-mosaic__unavailable').hidden).toBe(false)
})

it('handles an image that failed before initialization', () => {
  Object.defineProperty(image, 'complete', { value: true })
  Object.defineProperty(image, 'naturalWidth', { value: 0 })
  initMediaPreviews()
  expect(image.hidden).toBe(true)
})

it('preserves successfully loaded images', () => {
  Object.defineProperty(image, 'complete', { value: true })
  Object.defineProperty(image, 'naturalWidth', { value: 992 })
  initMediaPreviews()
  expect(image.hidden).toBe(false)
  expect(document.querySelector('.media-mosaic__unavailable').hidden).toBe(true)
})

it('leaves a lazy image waiting to load visible', () => {
  Object.defineProperty(image, 'complete', { value: false })
  initMediaPreviews()
  expect(image.hidden).toBe(false)
})
