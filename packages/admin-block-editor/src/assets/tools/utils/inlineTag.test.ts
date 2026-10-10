// @vitest-environment jsdom
// jsdom moves a live range out of a node removed from the document, as browsers
// do, and unwrapping a tag relies on it; happy-dom leaves the range behind.
import { describe, it, expect, beforeEach, afterEach, onTestFinished, vi } from 'vitest'
import { API } from '@editorjs/editorjs'
import { unwrapTag, wrapInTag } from './inlineTag'
import Hyperlink from '../Hyperlink/Hyperlink'
import Small from '../Small/Small'

/**
 * The editor API the inline tools call. Its selection calls act as Editor.js's
 * do, except setFakeBackground and save, the link panel's fake selection, which
 * do nothing.
 */
function stubApi(): API {
  return {
    styles: {
      input: 'cdx-input',
      inlineToolButton: 'ce-inline-tool',
      inlineToolButtonActive: 'ce-inline-tool--active',
    },
    i18n: { t: (key: string) => key },
    selection: {
      setFakeBackground: () => {},
      save: () => {},
      findParentTag: (tagName: string) => {
        const node = window.getSelection()?.anchorNode ?? null
        const element = node instanceof Element ? node : (node?.parentElement ?? null)

        return element?.closest(tagName) ?? null
      },
      expandToTag: (element: HTMLElement) => {
        const range = document.createRange()
        range.selectNodeContents(element)
        window.getSelection()!.removeAllRanges()
        window.getSelection()!.addRange(range)
      },
    },
  } as unknown as API
}

/** A window with no selection to work through, as getSelection() may report. */
function withoutSelection(): API {
  vi.spyOn(window, 'getSelection').mockReturnValue(null)

  return { selection: { expandToTag: () => {} } } as unknown as API
}

let paragraph: HTMLElement

beforeEach(() => {
  paragraph = document.createElement('p')
  document.body.replaceChildren(paragraph)
})

afterEach(() => {
  vi.restoreAllMocks()
})

/** Select `text`, found in one text node of the paragraph, and return the range. */
function select(text: string): Range {
  const walker = document.createTreeWalker(paragraph, NodeFilter.SHOW_TEXT)
  let node = walker.nextNode()
  while (node !== null && !node.textContent!.includes(text)) node = walker.nextNode()

  const start = node!.textContent!.indexOf(text)
  const range = document.createRange()
  range.setStart(node!, start)
  range.setEnd(node!, start + text.length)
  window.getSelection()!.removeAllRanges()
  window.getSelection()!.addRange(range)

  return range
}

describe('wrapInTag', () => {
  it('wraps the range in a new element and leaves its text selected', () => {
    paragraph.innerHTML = 'a tiny note'

    const wrapper = wrapInTag(stubApi(), select('tiny'), 'SMALL')

    expect(paragraph.innerHTML).toBe('a <small>tiny</small> note')
    expect(wrapper).toBe(paragraph.querySelector('small'))
    expect(window.getSelection()!.toString()).toBe('tiny')
  })
})

describe('unwrapTag', () => {
  it('puts the content back in place of the tag, left selected', () => {
    paragraph.innerHTML = 'a <small>tiny <b>bold</b></small> note'

    const selection = unwrapTag(stubApi(), paragraph.querySelector('small')!)

    expect(paragraph.innerHTML).toBe('a tiny <b>bold</b> note')
    expect(selection).toBe(window.getSelection())
    expect(selection!.toString()).toBe('tiny bold')
  })

  it('gives back null and keeps the tag when there is no selection', () => {
    paragraph.innerHTML = 'a <small>tiny</small> note'

    expect(unwrapTag(withoutSelection(), paragraph.querySelector('small')!)).toBeNull()
    expect(paragraph.innerHTML).toBe('a <small>tiny</small> note')
  })
})

describe('the inline tools wrapping a tag', () => {
  it('Small toggles <small> around the selection', () => {
    const small = new Small({ api: stubApi() } as never)
    paragraph.innerHTML = 'a tiny note'

    small.surround(select('tiny'))
    expect(paragraph.innerHTML).toBe('a <small>tiny</small> note')

    small.surround(select('tiny'))
    expect(paragraph.innerHTML).toBe('a tiny note')
    expect(window.getSelection()!.toString()).toBe('tiny')
  })

  it('Hyperlink drops a link keeping its text, with the caret after it', () => {
    paragraph.innerHTML = 'a <a href="/x">link</a> here'

    new Hyperlink({ api: stubApi() }).unlink(paragraph.querySelector('a'))

    const selection = window.getSelection()!
    const beforeCaret = document.createRange()
    beforeCaret.setStart(paragraph, 0)
    beforeCaret.setEnd(selection.focusNode!, selection.focusOffset)
    expect(paragraph.innerHTML).toBe('a link here')
    expect(selection.isCollapsed).toBe(true)
    expect(beforeCaret.toString()).toBe('a link')
  })

  it('Hyperlink wraps a new link around the selection and edits that link', () => {
    // jsdom has no execCommand, which only paints the fake selection background.
    document.execCommand = () => true
    onTestFinished(() => {
      delete (document as { execCommand?: unknown }).execCommand
    })
    const tool = new Hyperlink({ api: stubApi() })
    tool.render()
    const address = tool.renderActions().querySelector('input')! // the panel's first field
    paragraph.innerHTML = 'a link here'

    tool.surround(select('link'))
    address.value = '/x'
    tool.updateLink()

    expect(paragraph.innerHTML).toBe('a <a href="/x">link</a> here')
  })

  it('Hyperlink leaves the text alone when there is no link or no selection', () => {
    paragraph.innerHTML = 'a <a href="/x">link</a> here'

    new Hyperlink({ api: stubApi() }).unlink(null)
    new Hyperlink({ api: withoutSelection() }).unlink(paragraph.querySelector('a'))

    expect(paragraph.innerHTML).toBe('a <a href="/x">link</a> here')
  })
})
