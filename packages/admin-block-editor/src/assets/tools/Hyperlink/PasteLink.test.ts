import { describe, expect, it, vi } from 'vitest'
import type EditorJS from '@editorjs/editorjs'
import PasteLink from './PasteLink'

describe('PasteLink', () => {
  it('escapes selected text and the URL when inserting a pasted link', () => {
    const block = document.createElement('div')
    block.className = 'ce-block__content'
    block.textContent = '<img src=x onerror=alert(1)>'
    document.body.appendChild(block)
    const selection = window.getSelection()!
    const range = document.createRange()
    range.selectNodeContents(block)
    selection.removeAllRanges()
    selection.addRange(range)

    const insert = vi.fn()
    document.execCommand = insert
    new PasteLink({ editor: { selection: { findParentTag: () => null } } as unknown as EditorJS })
    const event = new Event('paste', { bubbles: true, cancelable: true })
    Object.defineProperty(event, 'clipboardData', {
      value: { getData: () => 'https://example.com/?q=" onclick="alert(1)' },
    })

    block.dispatchEvent(event)

    expect(insert).toHaveBeenCalledOnce()
    const html = insert.mock.calls[0]![2] as string
    expect(html).not.toContain('<img')
    expect(html).toContain('&lt;img')
    const holder = document.createElement('div')
    holder.innerHTML = html
    expect(holder.querySelector('a')?.hasAttribute('onclick')).toBe(false)
    expect(holder.querySelector('a')?.textContent).toBe('<img src=x onerror=alert(1)>')
  })
})
