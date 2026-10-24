// @vitest-environment jsdom
import { afterEach, beforeEach, expect, it, vi } from 'vitest'

let frame
let email
let observe
let resize
let height

beforeEach(() => {
  vi.resetModules()
  document.body.innerHTML = '<div class="pw-campaign-preview"><iframe sandbox="allow-same-origin" srcdoc="Email"></iframe></div>'
  frame = document.querySelector('iframe')
  email = document.implementation.createHTMLDocument('Email')
  Object.defineProperty(frame, 'contentDocument', { get: () => email })
  height = 1200.5
  vi.spyOn(email.body, 'getBoundingClientRect').mockImplementation(() => ({ height }))
  observe = vi.fn()
  vi.stubGlobal('ResizeObserver', class {
    constructor(callback) { resize = callback }
    observe = observe
  })
})

afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
  document.body.innerHTML = ''
})

it('fits the entire email and follows layout changes without enabling scripts', async () => {
  await import('../../../newsletter/src/Resources/public/campaign-review.js')
  frame.dispatchEvent(new Event('load'))
  expect(frame.getAttribute('sandbox')).toBe('allow-same-origin')
  expect(observe).toHaveBeenCalledWith(email.body)
  expect(frame.style.blockSize).toBe('1201px')

  height = 1900
  resize()
  expect(frame.style.blockSize).toBe('1900px')
})

it('leaves an external document alone when its contents cannot be measured', async () => {
  await import('../../../newsletter/src/Resources/public/campaign-review.js')
  email = null
  expect(() => frame.dispatchEvent(new Event('load'))).not.toThrow()
  expect(observe).not.toHaveBeenCalled()
})

it('measures an email that finished loading before the module', async () => {
  Object.defineProperty(email, 'readyState', { value: 'complete' })
  await import('../../../newsletter/src/Resources/public/campaign-review.js')
  expect(frame.style.blockSize).toBe('1201px')
})

it('opens web links separately and keeps anchors within the preview', async () => {
  email.body.innerHTML = '<a href="https://example.com/hiking">Hiking</a><a href="#foot">Foot</a><a href="javascript:alert(1)">Invalid</a>'
  const open = vi.spyOn(window, 'open').mockImplementation(() => null)
  await import('../../../newsletter/src/Resources/public/campaign-review.js')
  frame.dispatchEvent(new Event('load'))
  const links = email.querySelectorAll('a')
  const click = new MouseEvent('click', { bubbles: true, cancelable: true })
  links[0].dispatchEvent(click)
  expect(click.defaultPrevented).toBe(true)
  expect(open).toHaveBeenCalledExactlyOnceWith('https://example.com/hiking', '_blank', 'noopener,noreferrer')
  links[1].click()
  links[2].click()
  expect(open).toHaveBeenCalledTimes(1)
})
