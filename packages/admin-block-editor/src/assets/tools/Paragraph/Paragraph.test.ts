import { describe, it, expect, beforeEach, vi } from 'vitest'
import Paragraph from './Paragraph'
import { MarkdownUtils } from '../utils/MarkdownUtils'
import type { API } from '@editorjs/editorjs'

describe('Paragraph Delete on visually empty text', () => {
  function setup(html: string, readOnly = false, hasNext = true) {
    const next = { id: 'heading' }
    const api = {
      styles: { block: 'cdx-block' },
      i18n: { t: (text: string) => text },
      blocks: {
        getCurrentBlockIndex: vi.fn(() => 0),
        getBlockByIndex: vi.fn(() => hasNext ? next : undefined),
        delete: vi.fn(),
      },
      caret: { setToBlock: vi.fn() },
    }
    const paragraph = new Paragraph({ data: { text: html }, config: {}, api: api as unknown as API, readOnly })
    const field = paragraph.render()
    document.body.replaceChildren(field)
    const range = document.createRange()
    range.selectNodeContents(field)
    range.collapse(false)
    window.getSelection()!.removeAllRanges()
    window.getSelection()!.addRange(range)
    const press = (init: KeyboardEventInit = {}) => {
      const event = new KeyboardEvent('keydown', { key: 'Delete', bubbles: true, cancelable: true, ...init })
      field.dispatchEvent(event)
      return event
    }
    return { api, field, press }
  }

  it.each(['', '<br>', ' ', '&nbsp;', '<b>&nbsp;</b>', '\u200b\u200b', '\uFEFF'])(
    'removes the paragraph containing %j and focuses the next block without converting it', (html) => {
      const { api, press } = setup(html)
      expect(press().defaultPrevented).toBe(true)
      expect(api.blocks.delete).toHaveBeenCalledExactlyOnceWith(0)
      expect(api.caret.setToBlock).toHaveBeenCalledExactlyOnceWith('heading', 'start')
    },
  )

  it.each([
    { key: 'Backspace' }, { shiftKey: true }, { ctrlKey: true }, { altKey: true },
    { metaKey: true }, { isComposing: true },
  ])('leaves other shortcuts and composition to the editor: %j', (init) => {
    const { api, press } = setup('&nbsp;')
    expect(press(init).defaultPrevented).toBe(false)
    expect(api.blocks.delete).not.toHaveBeenCalled()
  })

  it.each(['Text', '<b>Text</b>', '\u200bText'])('keeps paragraphs with visible text: %j', (html) => {
    const { api, press } = setup(html)
    expect(press().defaultPrevented).toBe(false)
    expect(api.blocks.delete).not.toHaveBeenCalled()
  })

  it('leaves character deletion before the end of the paragraph to the browser', () => {
    const { api, field, press } = setup('&nbsp;&nbsp;')
    window.getSelection()!.collapse(field.firstChild, 1)
    expect(press().defaultPrevented).toBe(false)
    expect(api.blocks.delete).not.toHaveBeenCalled()
  })

  it('treats trailing collapsible spaces as the end of the field, like Editor.js', () => {
    const { api, field, press } = setup(' ')
    window.getSelection()!.collapse(field.firstChild, 0)
    expect(press().defaultPrevented).toBe(true)
    expect(api.blocks.delete).toHaveBeenCalledExactlyOnceWith(0)
  })

  it('leaves a text selection to the browser', () => {
    const { api, field, press } = setup('&nbsp;')
    window.getSelection()!.selectAllChildren(field)
    expect(press().defaultPrevented).toBe(false)
    expect(api.blocks.delete).not.toHaveBeenCalled()
  })

  it('does nothing without a caret range', () => {
    const { api, press } = setup('&nbsp;')
    window.getSelection()!.removeAllRanges()
    expect(press().defaultPrevented).toBe(false)
    expect(api.blocks.delete).not.toHaveBeenCalled()
  })

  it('does nothing when the caret belongs to another field', () => {
    const { api, press } = setup('&nbsp;')
    const other = document.createElement('div')
    document.body.append(other)
    window.getSelection()!.collapse(other, 0)
    expect(press().defaultPrevented).toBe(false)
    expect(api.blocks.delete).not.toHaveBeenCalled()
  })

  it('keeps the last paragraph when there is no next block', () => {
    const { api, press } = setup('&nbsp;', false, false)
    expect(press().defaultPrevented).toBe(false)
    expect(api.blocks.delete).not.toHaveBeenCalled()
  })

  it('does not handle Delete in read-only mode', () => {
    const { api, press } = setup('&nbsp;', true)
    expect(press().defaultPrevented).toBe(false)
    expect(api.blocks.delete).not.toHaveBeenCalled()
  })

  it('respects a Delete already handled by another listener', () => {
    const { api, field, press } = setup('&nbsp;')
    field.addEventListener('keydown', event => event.preventDefault(), true)
    press()
    expect(api.blocks.delete).not.toHaveBeenCalled()
  })
})

describe('Paragraph.save', () => {
  it.each([
    ['<br>', ''],
    ['', ''],
    ['Text<br>', 'Text<br>'],
    ['<br><br>', '<br><br>'],
  ])('saves %s without creating history for a browser caret placeholder', (html, expected) => {
    const content = document.createElement('div')
    content.innerHTML = html
    expect(Paragraph.prototype.save(content)).toEqual({ text: expected })
  })
})

describe('Paragraph.exportToMarkdown', () => {
  beforeEach(() => {
    vi.spyOn(MarkdownUtils, 'formatMarkdownWithPrettier').mockImplementation(
      async (markdown) => markdown,
    )
  })

  it('writes a <br> as a markdown hard break', async () => {
    const markdown = await Paragraph.exportToMarkdown(
      { text: 'Transfert au bateau. <br>Vers 18h : cocktail' },
      {},
    )

    expect(markdown.trim()).toBe('Transfert au bateau.  \nVers 18h : cocktail')
  })

  it.each(['<br/>', '<br />', '<BR>'])('writes a %s as a hard break too', async (br) => {
    const markdown = await Paragraph.exportToMarkdown({ text: `un${br}deux` }, {})

    expect(markdown.trim()).toBe('un  \ndeux')
  })
})
