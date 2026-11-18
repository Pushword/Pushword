import { afterEach, expect, it } from 'vitest'
import { selectedLink } from './selectedLink'

afterEach(() => document.body.replaceChildren())

it('recognizes a range spanning differently formatted parts of the same link', () => {
  document.body.innerHTML = '<p contenteditable="true"><a href="/one">before <b>selected</b> after</a></p>'
  const link = document.querySelector('a')!
  getSelection()!.setBaseAndExtent(link.firstChild!, 2, link.querySelector('b')!.firstChild!, 4)
  expect(selectedLink()).toBe(link)
})

it('does not mistake a range starting in a link and ending outside it for a link selection', () => {
  document.body.innerHTML = '<p contenteditable="true"><a href="/one">same</a> same</p>'
  getSelection()!.setBaseAndExtent(document.querySelector('a')!.firstChild!, 0, document.querySelector('p')!.lastChild!, 5)
  expect(selectedLink()).toBeNull()
})

it('ignores links outside editable content and missing selections', () => {
  document.body.innerHTML = '<a href="/one">navigation</a>'
  const text = document.querySelector('a')!.firstChild!
  getSelection()!.setBaseAndExtent(text, 1, text, 4)
  expect(selectedLink()).toBeNull()
  getSelection()!.removeAllRanges()
  expect(selectedLink()).toBeNull()
})
