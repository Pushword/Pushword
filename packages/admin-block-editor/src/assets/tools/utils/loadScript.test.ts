// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type * as Loader from './loadScript'

let loadScriptOnce: typeof Loader.loadScriptOnce
let loadMonaco: typeof Loader.loadMonaco

const scripts = (): HTMLScriptElement[] => [...document.head.querySelectorAll('script')]

const settled = async (promise: Promise<unknown>): Promise<boolean> => {
  let done = false
  void promise.then(
    () => (done = true),
    () => (done = true),
  )
  await new Promise((resolve) => setTimeout(resolve, 0))
  return done
}

beforeEach(async () => {
  // Each test starts with nothing fetched yet.
  vi.resetModules()
  ;({ loadScriptOnce, loadMonaco } = await import('./loadScript'))
  document.head.innerHTML = ''
  delete window.monaco
  delete window.monacoHelper
  delete window.pwMonacoUrl
  delete window.pwMonacoLoading
})

describe('loadScriptOnce', () => {
  it('injects one script for concurrent loads, and resolves only once it has run', async () => {
    const first = loadScriptOnce('/prettier/standalone.js')
    const second = loadScriptOnce('/prettier/standalone.js')

    expect(scripts()).toHaveLength(1)
    expect(await settled(second)).toBe(false)

    scripts()[0]!.dispatchEvent(new Event('load'))

    await expect(first).resolves.toBeUndefined()
    await expect(second).resolves.toBeUndefined()
  })

  it('forgets a failed load, so a later call retries', async () => {
    const failed = loadScriptOnce('/prettier/markdown.js')
    scripts()[0]!.dispatchEvent(new Event('error'))
    await expect(failed).rejects.toThrow('/prettier/markdown.js')

    void loadScriptOnce('/prettier/markdown.js')

    expect(scripts()).toHaveLength(2)
  })
})

describe('loadMonaco', () => {
  it('waits on the fetch pushword/admin already started instead of starting another', async () => {
    let settle!: (ready: boolean) => void
    window.pwMonacoLoading = new Promise((resolve) => (settle = resolve))

    const loading = loadMonaco()
    settle(true)

    await expect(loading).resolves.toBe(true)
    expect(scripts()).toHaveLength(0)
  })

  it('fetches the URL the dashboard published once, and shares the fetch on window', async () => {
    window.pwMonacoUrl = '/bundles/pushwordadmin/monaco/app.js?v=1234'

    const first = loadMonaco()
    const second = loadMonaco()

    expect(scripts().map((script) => script.getAttribute('src'))).toEqual([
      '/bundles/pushwordadmin/monaco/app.js?v=1234',
    ])
    expect(window.pwMonacoLoading).toBe(first)
    expect(second).toBe(first)

    scripts()[0]!.dispatchEvent(new Event('load'))
    await expect(first).resolves.toBe(true)
  })

  it('resolves to false and clears the shared guard when the fetch fails', async () => {
    const loading = loadMonaco()
    expect(scripts()[0]!.getAttribute('src')).toBe('/bundles/pushwordadmin/monaco/app.js')

    scripts()[0]!.dispatchEvent(new Event('error'))

    await expect(loading).resolves.toBe(false)
    expect(window.pwMonacoLoading).toBeNull()
  })

  it('fetches nothing once Monaco is on the page', async () => {
    ;(window as any).monaco = {}
    ;(window as any).monacoHelper = {}

    await expect(loadMonaco()).resolves.toBe(true)
    expect(scripts()).toHaveLength(0)
  })
})
