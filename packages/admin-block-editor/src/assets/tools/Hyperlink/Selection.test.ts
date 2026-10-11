import { afterEach, expect, it } from 'vitest'
import SelectionUtils from './Selection'

afterEach(() => document.body.replaceChildren())

it('remembers a range without inserting formatting and restores its exact end', () => {
  document.body.innerHTML = '<p>Before <b>linked</b> after</p>'
  const text = document.querySelector('b')!.firstChild!
  getSelection()!.setBaseAndExtent(text, 1, text, 4)
  const selection = new SelectionUtils()
  selection.save()
  getSelection()!.collapse(text, 6)
  selection.restore()
  expect(getSelection()!.toString()).toBe('ink')
  selection.collapseToEnd()
  expect(getSelection()!.anchorNode).toBe(text)
  expect(getSelection()!.anchorOffset).toBe(4)
  expect(document.body.innerHTML).toBe('<p>Before <b>linked</b> after</p>')
})

it('does nothing when there is no saved range', () => {
  getSelection()!.removeAllRanges()
  const selection = new SelectionUtils()
  selection.save()
  expect(() => selection.restore()).not.toThrow()
  expect(() => selection.collapseToEnd()).not.toThrow()
})
