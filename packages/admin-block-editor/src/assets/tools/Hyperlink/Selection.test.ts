import { describe, it, expect, vi, beforeEach } from 'vitest'
import SelectionUtils from './Selection'

describe("SelectionUtils – the link tool's fake background", () => {
  beforeEach(() => {
    document.execCommand = vi.fn(() => true)
  })

  it("undoes the highlight without stripping the selection's formatting", () => {
    const selection = new SelectionUtils()

    selection.setFakeBackground()
    selection.removeFakeBackground()

    // `removeFormat` would clear the highlight, but it also drops every inline
    // tag of the selection — linking bold text used to lose the bold.
    expect(document.execCommand).not.toHaveBeenCalledWith('removeFormat')
    expect(document.execCommand).toHaveBeenLastCalledWith(
      'backColor',
      false,
      'transparent',
    )
    expect(selection.isFakeBackgroundEnabled).toBe(false)
  })

  it('does nothing when no highlight is applied', () => {
    const selection = new SelectionUtils()

    selection.removeFakeBackground()

    expect(document.execCommand).not.toHaveBeenCalled()
  })
})
