// @vitest-environment jsdom
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { initMediaFileDrop } from '../../src/Resources/assets/admin.mediaFileDrop.js'

let root
let input

beforeEach(() => {
  root = document.implementation.createHTMLDocument()
  root.body.innerHTML = '<form><input type="file" id="Media_mediaFile"><input id="Media_alt"></form>'
  input = root.querySelector('input[type="file"]')
  // jsdom has no DataTransfer, and its native files setter only accepts FileList.
  Object.defineProperty(input, 'files', { value: [], writable: true })
  vi.stubGlobal('DataTransfer', class {
    files = []
    items = { add: (file) => this.files.push(file) }
  })
})

afterEach(() => vi.unstubAllGlobals())

function dragEvent(type, transfer) {
  const event = new Event(type, { bubbles: true, cancelable: true })
  Object.defineProperty(event, 'dataTransfer', { value: transfer })
  return event
}

it('loads a file dropped outside the chooser and notifies existing change handlers once', () => {
  const file = new File(['audio'], 'recording.mp3', { type: 'audio/mpeg' })
  const changed = vi.fn()
  root.querySelector('form').addEventListener('change', changed)
  initMediaFileDrop(root)
  initMediaFileDrop(root)

  const event = dragEvent('drop', { files: [file] })
  root.querySelector('#Media_alt').dispatchEvent(event)

  expect(event.defaultPrevented).toBe(true)
  expect(input.files).toHaveLength(1)
  expect(input.files[0]).toBe(file)
  expect(changed).toHaveBeenCalledOnce()
  expect(changed.mock.calls[0][0].target).toBe(input)
})

it('replaces an existing selection with only the first dropped file', () => {
  input.files = [new File(['old'], 'old.png')]
  const first = new File(['first'], 'first.png')
  const second = new File(['second'], 'second.png')
  initMediaFileDrop(root)
  root.body.dispatchEvent(dragEvent('drop', { files: [first, second] }))
  expect(input.files).toHaveLength(1)
  expect(input.files[0]).toBe(first)
})

it('allows file drops anywhere in the window with a copy cursor', () => {
  initMediaFileDrop(root)
  const transfer = { types: ['Files'], dropEffect: 'none' }
  const event = dragEvent('dragover', transfer)
  root.body.dispatchEvent(event)
  expect(event.defaultPrevented).toBe(true)
  expect(transfer.dropEffect).toBe('copy')
})

it('preserves text dragging and ignores empty drops', () => {
  const selected = new File(['selected'], 'selected.png')
  input.files = [selected]
  initMediaFileDrop(root)
  const drag = dragEvent('dragover', { types: ['text/plain'] })
  const drop = dragEvent('drop', { files: [] })
  root.body.dispatchEvent(drag)
  root.body.dispatchEvent(drop)
  expect(drag.defaultPrevented).toBe(false)
  expect(drop.defaultPrevented).toBe(false)
  expect(input.files).toHaveLength(1)
  expect(input.files[0]).toBe(selected)
})

it('does not intercept drops on other admin screens', () => {
  input.remove()
  initMediaFileDrop(root)
  const event = dragEvent('drop', { files: [new File(['x'], 'image.png')] })
  root.body.dispatchEvent(event)
  expect(event.defaultPrevented).toBe(false)
})

it('leaves disabled file inputs unchanged', () => {
  initMediaFileDrop(root)
  input.disabled = true
  const event = dragEvent('drop', { files: [new File(['x'], 'image.png')] })
  root.body.dispatchEvent(event)
  expect(event.defaultPrevented).toBe(false)
  expect(input.files).toEqual([])
})
