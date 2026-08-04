import { describe, it, expect } from 'vitest'
import { API } from '@editorjs/editorjs'
import Hyperlink from './Hyperlink'

/**
 * Regression guard for codex-team/editor.js#2821.
 *
 * checkState() runs on every selectionchange, and editor.js listens to that event
 * on the document. Assigning the live `value` property of a focused input moves the
 * caret, which raises selectionchange, which calls checkState again: the inline
 * toolbar rebuilds itself in a loop as soon as the caret sits inside a link.
 *
 * Writing the `value` *attribute* sets the default value instead, touching no
 * selection — the same fix editor.js shipped upstream in 2.31.4 as `defaultValue`.
 * The old assignment is still one keystroke away in the source, so this test is
 * what stops it coming back.
 */

function stubApi(): API {
  return {
    styles: {
      input: 'cdx-input',
      inlineToolButton: 'ce-inline-tool',
      inlineToolButtonActive: 'ce-inline-tool--active',
    },
    i18n: { t: (key: string) => key },
  } as unknown as API
}

/** The URL field, the only text input renderActions() builds (the rest are switches). */
function urlInputOf(wrapper: HTMLElement): HTMLInputElement {
  const input = wrapper.querySelector<HTMLInputElement>('input[placeholder="https://..."]')
  if (!input) throw new Error('renderActions() did not build the URL input')

  return input
}

/**
 * Counts direct writes to the live `value` property. setAttribute() does not go
 * through this setter, which is exactly the distinction under test.
 */
function countValueAssignments(input: HTMLInputElement): () => number {
  const descriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!
  let assignments = 0

  Object.defineProperty(input, 'value', {
    configurable: true,
    get: () => descriptor.get!.call(input),
    set: (value: string) => {
      assignments++
      descriptor.set!.call(input, value)
    },
  })

  return () => assignments
}

function toolWithActions(): { tool: Hyperlink; input: HTMLInputElement } {
  const tool = new Hyperlink({ api: stubApi() })
  const input = urlInputOf(tool.renderActions())

  return { tool, input }
}

function anchor(html: string): HTMLElement {
  const holder = document.createElement('div')
  holder.innerHTML = html

  return holder.firstElementChild as HTMLElement
}

describe('Hyperlink.updateActionValues', () => {
  it('writes the href as the value attribute, never as the live property', () => {
    const { tool, input } = toolWithActions()
    const assignments = countValueAssignments(input)

    tool.updateActionValues(anchor('<a href="/">Homepage</a>'))

    expect(assignments()).toBe(0)
    expect(input.getAttribute('value')).toBe('/')
    // The attribute is what an untouched input displays, so the field still shows it.
    expect(input.value).toBe('/')
  })

  it('clears the field through the attribute too when the anchor has no href', () => {
    const { tool, input } = toolWithActions()
    const assignments = countValueAssignments(input)

    tool.updateActionValues(anchor('<a>Not linked yet</a>'))

    expect(assignments()).toBe(0)
    expect(input.getAttribute('value')).toBe('')
  })

  it('reads back the rel, target and design an anchor already carries', () => {
    const { tool } = toolWithActions()
    const wrapper = tool.renderActions()

    tool.updateActionValues(
      anchor('<a href="/x" rel="obfuscate" target="_blank" class="link-btn">x</a>'),
    )

    const switches = wrapper.querySelectorAll<HTMLInputElement>('input[type="checkbox"]')
    expect([...switches].every((box) => box.checked)).toBe(true)
    expect(wrapper.querySelector<HTMLSelectElement>('select')!.value).toBe('link-btn')
  })
})
