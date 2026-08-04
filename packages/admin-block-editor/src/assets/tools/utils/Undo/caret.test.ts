import { describe, it, expect, beforeEach } from 'vitest'
import Caret from './caret'

function contentEditable(html: string): HTMLElement {
  const element = document.createElement('div')
  element.contentEditable = 'true'
  element.innerHTML = html
  document.body.appendChild(element)

  return element
}

function selectAt(node: Node, offset: number): void {
  const range = document.createRange()
  range.setStart(node, offset)
  range.collapse(true)
  const selection = window.getSelection()
  selection?.removeAllRanges()
  selection?.addRange(range)
}

beforeEach(() => {
  document.body.innerHTML = ''
  window.getSelection()?.removeAllRanges()
})

describe('Caret – reading the position', () => {
  it('reports -1 for an element that does not hold the focus', () => {
    const element = contentEditable('Hello')

    expect(new Caret(element).getPos()).toBe(-1)
  })

  it('counts characters, not nodes, so formatting does not shift it', () => {
    const element = contentEditable('one <b>two</b> three')
    element.focus()
    // Caret right after "two", i.e. 7 characters in
    const bold = element.querySelector('b')!
    selectAt(bold.firstChild!, 3)

    expect(new Caret(element).getPos()).toBe(7)
  })

  it('reads a textarea through its selection start', () => {
    const textarea = document.createElement('textarea')
    textarea.value = 'Hello world'
    document.body.appendChild(textarea)
    textarea.focus()
    textarea.setSelectionRange(5, 5)

    expect(new Caret(textarea).getPos()).toBe(5)
  })
})

describe('Caret – restoring the position', () => {
  it('places the caret back at a character offset across elements', () => {
    const element = contentEditable('one <b>two</b> three')
    element.focus()

    new Caret(element).setPos(7)

    const selection = window.getSelection()!
    expect(selection.rangeCount).toBe(1)
    // The offset lands inside the bold text node, after "two"
    expect(selection.getRangeAt(0).endContainer.textContent).toBe('two')
    expect(selection.getRangeAt(0).endOffset).toBe(3)
  })

  it('restores what it read', () => {
    const element = contentEditable('one <b>two</b> three')
    element.focus()
    selectAt(element.firstChild!, 2)
    const caret = new Caret(element)
    const position = caret.getPos()

    selectAt(element.lastChild!, 5)
    caret.setPos(position)

    expect(new Caret(element).getPos()).toBe(position)
  })

  it('ignores a negative position rather than moving the caret', () => {
    const element = contentEditable('Hello')
    element.focus()
    selectAt(element.firstChild!, 2)

    new Caret(element).setPos(-1)

    expect(new Caret(element).getPos()).toBe(2)
  })

  it('restores a textarea through setSelectionRange', () => {
    const textarea = document.createElement('textarea')
    textarea.value = 'Hello world'
    document.body.appendChild(textarea)
    textarea.focus()

    new Caret(textarea).setPos(4)

    expect(textarea.selectionStart).toBe(4)
    expect(textarea.selectionEnd).toBe(4)
  })
})
