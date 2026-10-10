// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { API } from '@editorjs/editorjs'
import CardList from './CardList'

describe('CardList title HTML', () => {
  it('keeps formatting but removes executable markup after decoding legacy entities', () => {
    const tool = new CardList({ data: { items: [] }, api: {} as API, readOnly: false })
    const field = (tool as any).createHtmlEditableField(
      'Title',
      'title',
      '&lt;strong&gt;Hello&lt;/strong&gt;&lt;img src=x onerror=alert(1)&gt;',
    ) as HTMLElement
    const editable = field.querySelector('[contenteditable]')!

    expect(editable.querySelector('strong')?.textContent).toBe('Hello')
    expect(editable.querySelector('img')).toBeNull()
  })

  it('removes a script URL without removing the link text', () => {
    const tool = new CardList({ data: { items: [] }, api: {} as API, readOnly: false })
    const field = (tool as any).createHtmlEditableField(
      'Title', 'title', '<a href="javascript:alert(1)">Read</a>',
    ) as HTMLElement

    expect(field.querySelector('a')?.hasAttribute('href')).toBe(false)
    expect(field.querySelector('a')?.textContent).toBe('Read')
  })
})

describe('CardList description', () => {
  it('still shows a newline as a line break, as the export writes a <br>', () => {
    const tool = new CardList({ data: { items: [] }, api: {} as API, readOnly: false })
    const field = (tool as any).createContentEditableField(
      'Description', 'description', '**un**\ndeux',
    ) as HTMLElement

    expect(field.querySelector('[contenteditable]')!.innerHTML).toBe('<b>un</b><br>deux')
  })
})

describe('CardList unknown slug', () => {
  it('says in words that no page has the slug, not only with a red border', () => {
    window.pagesUriList = ['/known']
    const api = {
      i18n: { t: (text: string) => text },
      styles: { block: 'cdx-block' },
    } as unknown as API
    const tool = new CardList({
      data: { items: [{ page: 'missing' }, { page: 'known' }] },
      api,
      readOnly: false,
    })
    tool.render()

    expect(tool.validate()).toBe(false)
    const [missing, known] = (tool as any).itemNodes
    expect(missing.pageInput.getAttribute('aria-invalid')).toBe('true')
    expect(missing.slugError.hidden).toBe(false)
    expect(missing.pageInput.getAttribute('aria-describedby')).toBe(missing.slugError.id)
    expect(known.pageInput.hasAttribute('aria-invalid')).toBe(false)
    expect(known.slugError.hidden).toBe(true)
  })
})

describe('CardList unknown slug message', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  function renderCards(pages: string[]): any[] {
    window.pagesUriList = ['/known']
    const api = {
      i18n: { t: (text: string) => `t(${text})` },
      styles: { block: 'cdx-block' },
    } as unknown as API
    const tool = new CardList({
      data: { items: pages.map((page) => ({ page })) },
      api,
      readOnly: false,
    })
    tool.render()

    return (tool as any).itemNodes
  }

  it('stays silent on blur when the slug names a page', () => {
    vi.useFakeTimers()
    const [nodes] = renderCards(['known'])

    nodes.pageInput.dispatchEvent(new Event('blur'))
    vi.advanceTimersByTime(500)

    expect(nodes.slugError.hidden).toBe(true)
    expect(nodes.pageInput.hasAttribute('aria-invalid')).toBe(false)
  })

  it('puts a translated message under the header row, with its own id per card', () => {
    const [first, second] = renderCards(['missing', 'known'])

    expect(first.slugError.textContent).toBe('t(No page has this slug)')
    const [header, message] = first.wrapper.children
    expect(header.classList.contains('cardlist-item-header')).toBe(true)
    expect(message).toBe(first.slugError)
    expect(first.slugError.id).not.toBe(second.slugError.id)
    expect(second.pageInput.getAttribute('aria-describedby')).toBe(second.slugError.id)
  })
})

function stubApi(): API {
  return {
    i18n: { t: (text: string) => text },
    styles: { block: 'cdx-block' },
  } as unknown as API
}

describe('CardList icon checkboxes', () => {
  afterEach(() => {
    vi.useRealTimers()
    document.body.innerHTML = ''
  })

  it('binds each card label to its own checkbox, even for cards built in the same millisecond', () => {
    // renderItems builds every card in one go: pin the clock to make that explicit.
    vi.useFakeTimers()
    const tool = new CardList({
      data: { items: [{}, {}] },
      api: stubApi(),
      readOnly: false,
    })
    document.body.appendChild(tool.render())

    const [first, second] = (tool as any).itemNodes
    expect(first.obfuscateLinkInput.id).not.toBe(second.obfuscateLinkInput.id)
    for (const input of [first.obfuscateLinkInput, second.obfuscateLinkInput]) {
      expect(input.nextElementSibling.htmlFor).toBe(input.id)
    }

    second.obfuscateLinkInput.nextElementSibling.click()
    expect(second.obfuscateLinkInput.checked).toBe(true)
    expect(first.obfuscateLinkInput.checked).toBe(false)
  })
})

describe('CardList custom fields', () => {
  it('heads every field with its label, above its control', () => {
    const tool = new CardList({ data: { items: [{}] }, api: stubApi(), readOnly: false })
    const fields = [...tool.render().querySelectorAll('.cardlist-item-field')]

    expect(
      fields.map((field) => [field.children[0]!.tagName, field.children[0]!.textContent]),
    ).toEqual([
      ['LABEL', 'Title'],
      ['LABEL', 'Image'],
      ['LABEL', 'Link'],
      ['LABEL', 'Description'],
      ['LABEL', 'Info Label'],
      ['LABEL', 'Button Label'],
      ['LABEL', 'Button Link'],
      ['LABEL', 'ID (anchor)'],
    ])
    expect(fields.every((field) => 2 === field.children.length)).toBe(true)
  })
})
