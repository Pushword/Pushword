import { JSDOM } from 'jsdom'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { initEditLock } from '../../src/Resources/assets/admin.editLock.js'

let dom
let lock
let fetchMock
let logged

beforeEach(() => {
  vi.useFakeTimers()
  dom = new JSDOM('<!doctype html><body></body>', {
    url: 'https://example.org/admin/page/1/edit',
  })
  vi.stubGlobal('window', dom.window)
  vi.stubGlobal('document', dom.window.document)
  vi.stubGlobal('sessionStorage', dom.window.sessionStorage)
  fetchMock = vi.fn(async () => ({
    ok: true,
    json: async () => ({ isOwner: true, lockInfo: null }),
  }))
  vi.stubGlobal('fetch', fetchMock)
  logged = vi.spyOn(console, 'error').mockImplementation(() => {})
})

afterEach(() => {
  lock?.stop()
  dom.window.close()
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
  vi.useRealTimers()
})

it('cancels pending pings on navigation without reporting a network failure', async () => {
  fetchMock.mockImplementationOnce(
    (_url, options) =>
      new Promise((_resolve, reject) => {
        options.signal.addEventListener('abort', () =>
          reject(new DOMException('Navigation', 'AbortError')),
        )
      }),
  )
  lock = initEditLock(1)
  const signal = fetchMock.mock.calls[0][1].signal
  dom.window.dispatchEvent(new dom.window.Event('pagehide'))
  await vi.advanceTimersByTimeAsync(6000)
  Object.defineProperty(document, 'hidden', { value: false, configurable: true })
  document.dispatchEvent(new dom.window.Event('visibilitychange'))
  expect(signal?.aborted).toBe(true)
  expect(fetchMock).toHaveBeenCalledOnce()
  expect(logged).not.toHaveBeenCalled()
})

it('still reports a real network failure while editing', async () => {
  const error = new TypeError('Network unavailable')
  fetchMock.mockRejectedValueOnce(error)
  lock = initEditLock(1)
  await vi.advanceTimersByTimeAsync(0)
  expect(logged).toHaveBeenCalledWith('[EditLock] Ping error:', error)
})

it('aborts before Firefox unload cancellation and keeps pinging if navigation is cancelled', async () => {
  fetchMock.mockImplementationOnce(
    (_url, options) =>
      new Promise((_resolve, reject) => {
        options.signal.addEventListener('abort', () =>
          reject(new DOMException('Navigation', 'AbortError')),
        )
      }),
  )
  lock = initEditLock(1)
  const signal = fetchMock.mock.calls[0][1].signal
  dom.window.dispatchEvent(new dom.window.Event('beforeunload'))
  await vi.advanceTimersByTimeAsync(3000)
  expect(signal.aborted).toBe(true)
  expect(fetchMock).toHaveBeenCalledTimes(2)
  expect(logged).not.toHaveBeenCalled()
})

it('restarts once when restored from the back-forward cache', async () => {
  lock = initEditLock(1)
  await vi.advanceTimersByTimeAsync(0)
  dom.window.dispatchEvent(new dom.window.Event('pagehide'))
  dom.window.dispatchEvent(
    new dom.window.PageTransitionEvent('pageshow', { persisted: true }),
  )
  lock.start()
  await vi.advanceTimersByTimeAsync(3000)
  expect(fetchMock).toHaveBeenCalledTimes(3)
  expect(logged).not.toHaveBeenCalled()
})
