// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
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
