import { describe, it, expect } from 'vitest'
import { modeButtonsOf } from './modeButtons'

const modes =
  '<button data-pw-editor-mode="json"></button><button data-pw-editor-mode="markdown"></button>'
const holder = (): HTMLElement => document.getElementById('ed')!
const found = (): string[] =>
  modeButtonsOf(holder()).map((button) => button.dataset.pwEditorMode!)

describe('modeButtonsOf', () => {
  it('finds the buttons in the container naming the editor, wherever it sits', () => {
    document.body.innerHTML =
      `<div data-pw-editor-modes-for="other">${modes}</div>` +
      `<div><div id="ed"></div></div>` +
      `<div data-pw-editor-modes-for="ed">${modes}</div>`

    const named = document.querySelectorAll('[data-pw-editor-modes-for="ed"] button')
    const buttons = modeButtonsOf(holder())
    expect(buttons).toHaveLength(2)
    buttons.forEach((button, index) => expect(button).toBe(named[index]))
  })

  it.each([
    ['right before the wrapper', `<div>${modes}</div><div><div id="ed"></div></div>`],
    [
      'before a diagnostics panel',
      `<div>${modes}</div><div data-pw-diagnostics-for="ed"></div><div><div id="ed"></div></div>`,
    ],
  ])('still finds them in an older template copy, %s', (_, html) => {
    document.body.innerHTML = html

    expect(found()).toEqual(['json', 'markdown'])
  })

  it('is empty when the widget has no mode buttons', () => {
    document.body.innerHTML = '<div><p>Title</p></div><div><div id="ed"></div></div>'

    expect(found()).toEqual([])
  })
})
