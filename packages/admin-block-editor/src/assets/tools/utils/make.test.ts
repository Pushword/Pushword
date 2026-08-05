import { describe, it, expect } from 'vitest'
import make from './make'

/**
 * The link tool and the link tune each render a `targetBlank` switch, and both
 * can sit in the document at once — the inline panel is built with the editor,
 * the tune when a block's settings open. A shared id makes every `for=` resolve
 * to whichever input came first, so clicking one switch toggles the other.
 */
describe('make.switchInput', () => {
  function idOf(node: HTMLElement): string {
    return node.querySelector('input')!.id
  }

  it('gives every switch its own id, however many share a name', () => {
    const first = make.switchInput('targetBlank', 'New tab')
    const second = make.switchInput('targetBlank', 'New tab')

    expect(idOf(first)).not.toBe(idOf(second))
  })

  it('points both labels at its own input', () => {
    const node = make.switchInput('targetBlank', 'New tab')

    const targets = [...node.querySelectorAll('label')].map((label) => label.htmlFor)
    expect(targets).toEqual([idOf(node), idOf(node)])
  })

  it('renders the text before the track, which the checked styles rely on', () => {
    const node = make.switchInput('hideForBot', 'Obfuscate')

    const shape = [...node.children].map((child) => child.tagName + '.' + child.className)
    expect(shape).toEqual(['INPUT.', 'LABEL.', 'LABEL.label-default'])
    expect(node.querySelector('label')!.textContent).toBe('Obfuscate')
  })

  it('announces itself as a switch rather than a checkbox', () => {
    const node = make.switchInput('targetBlank', 'New tab', true)

    expect(node.querySelector('input')!.getAttribute('role')).toBe('switch')
    expect(node.querySelector('input')!.checked).toBe(true)
  })
})
