import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type EditorJS from '@editorjs/editorjs'
import PasteLink from './PasteLink'

describe('PasteLink', () => {
  beforeAll(() => {
    new PasteLink({ editor: { selection: { findParentTag: () => null } } as unknown as EditorJS })
  })

  beforeEach(() => {
    document.body.innerHTML = ''
    window.getSelection()!.removeAllRanges()
    document.execCommand = vi.fn()
  })

  function selectText(text = 'mon mot'): HTMLElement {
    const block = document.createElement('div')
    block.className = 'ce-block__content'
    block.textContent = text
    document.body.appendChild(block)
    const selection = window.getSelection()!
    const range = document.createRange()
    range.selectNodeContents(block)
    selection.removeAllRanges()
    selection.addRange(range)
    return block
  }

  function paste(block: HTMLElement, text: string): Event {
    const event = new Event('paste', { bubbles: true, cancelable: true })
    Object.defineProperty(event, 'clipboardData', {
      value: { getData: () => text },
    })
    block.dispatchEvent(event)
    return event
  }

  it.each([
    'https://example.com/voyage', 'http://example.com', 'HTTPS://example.com',
    '//example.com/voyage', '/voyage', './voyage', '../voyage', '#section', '?page=2',
    'mailto:info@example.com', 'tel:+33123456789',
  ])('turns selected text into a link when pasting %s', (url) => {
    const event = paste(selectText(), url)

    expect(event.defaultPrevented).toBe(true)
    expect(document.execCommand).toHaveBeenCalledOnce()
    expect(document.execCommand).toHaveBeenCalledWith('insertHTML', false, `<a href="${url}">mon mot</a>`)
  })

  it.each([
    "le voyage et la personne qui l'a imaginé", 'mon mot', 'voyage', '123', '', '   ',
    'première ligne\ndeuxième ligne', 'https://example.com followed by text',
    '/a relative phrase', 'javascript:alert(1)', 'data:text/html,<script>alert(1)</script>',
    'http://%',
  ])('leaves normal paste handling in place for %j', (text) => {
    const event = paste(selectText(), text)

    expect(event.defaultPrevented).toBe(false)
    expect(document.execCommand).not.toHaveBeenCalled()
    expect(window.getSelection()!.toString()).toBe('mon mot')
  })

  it('trims the pasted URL and keeps spaces around the selected text', () => {
    paste(selectText(' mon mot '), ' https://example.com/voyage\n')

    expect(document.execCommand).toHaveBeenCalledWith('insertHTML', false, ' <a href="https://example.com/voyage">mon mot</a> ')
  })

  it('escapes selected text and the URL when inserting a pasted link', () => {
    const insert = vi.mocked(document.execCommand)
    paste(selectText('<img src=x onerror=alert(1)>'), 'https://example.com/?q="onclick="alert(1)')

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
