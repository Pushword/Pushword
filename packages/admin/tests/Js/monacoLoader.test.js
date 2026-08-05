// @vitest-environment jsdom
import { beforeEach, describe, expect, it } from 'vitest'
import { initMonacoEditors } from '../../src/Resources/assets/admin.monacoLoader.js'

const scripts = () =>
  [...document.head.querySelectorAll('script')].map((script) => script.src)

beforeEach(() => {
  document.head.innerHTML = ''
  document.body.innerHTML = ''
  delete window.monacoHelper
  delete window.pwMonacoUrl
  delete window.pwMonacoLoading
})

const withMarkdownField = () => {
  document.body.innerHTML = '<textarea data-editor="markdown"></textarea>'
}

describe('initMonacoEditors', () => {
  it('fetches nothing on a page holding no field Monaco drives', () => {
    document.body.innerHTML = '<textarea name="plain"></textarea>'

    initMonacoEditors()

    expect(scripts()).toHaveLength(0)
  })

  it('fetches nothing when the bundle already ran', () => {
    withMarkdownField()
    window.monacoHelper = {}

    initMonacoEditors()

    expect(scripts()).toHaveLength(0)
  })

  it('fetches the versioned URL the dashboard published', () => {
    withMarkdownField()
    window.pwMonacoUrl = '/bundles/pushwordadmin/monaco/app.js?v=1234'

    initMonacoEditors()

    expect(scripts()).toEqual([
      'http://localhost:3000/bundles/pushwordadmin/monaco/app.js?v=1234',
    ])
  })

  // A site that renders the admin without the dashboard's asset block still gets
  // an editor, only without cache busting.
  it('falls back to the bundled path when no URL was published', () => {
    withMarkdownField()

    initMonacoEditors()

    expect(scripts()).toEqual(['http://localhost:3000/bundles/pushwordadmin/monaco/app.js'])
  })

  it('is fetched once, however many fields ask for it', () => {
    document.body.innerHTML =
      '<textarea data-editor="markdown"></textarea><textarea data-editor="yaml"></textarea>'

    initMonacoEditors()
    initMonacoEditors()

    expect(scripts()).toHaveLength(1)
  })

  // The block editor injects the same bundle for its markdown and JSON modes and
  // parks its promise on the same key: a form carrying both must not fetch twice.
  it('leaves it alone when another bundle is already fetching it', () => {
    withMarkdownField()
    window.pwMonacoLoading = Promise.resolve(true)

    initMonacoEditors()

    expect(scripts()).toHaveLength(0)
  })

  it('clears the guard when the fetch fails, so a later call can retry', async () => {
    withMarkdownField()
    initMonacoEditors()

    const failed = window.pwMonacoLoading
    document.head.querySelector('script').dispatchEvent(new Event('error'))

    await expect(failed).resolves.toBe(false)
    expect(window.pwMonacoLoading).toBeNull()
  })

  it('resolves once the bundle has run', async () => {
    withMarkdownField()
    initMonacoEditors()

    const loading = window.pwMonacoLoading
    document.head.querySelector('script').dispatchEvent(new Event('load'))

    await expect(loading).resolves.toBe(true)
  })
})
