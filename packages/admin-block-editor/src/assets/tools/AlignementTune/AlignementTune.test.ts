import { describe, expect, it, vi } from 'vitest'
import AlignmentTune from './AlignementTune'

describe('AlignmentTune controls', () => {
  it('names native buttons and reflects the default and changed alignment immediately', () => {
    const dispatchChange = vi.fn()
    const tune = new AlignmentTune({
      api: { i18n: { t: (text: string) => `t(${text})` } } as any,
      block: { dispatchChange } as any,
    })
    const content = tune.wrap(document.createElement('p'))
    const buttons = tune.render().querySelectorAll('button')
    expect(buttons).toHaveLength(3)
    expect(buttons[0]?.getAttribute('aria-label')).toBe('t(Align left)')
    expect(buttons[0]?.getAttribute('aria-pressed')).toBe('true')
    buttons[1]!.click()
    expect(tune.save()).toBe('center')
    expect(content.classList.contains('text-center')).toBe(true)
    expect(buttons[0]?.getAttribute('aria-pressed')).toBe('false')
    expect(buttons[1]?.getAttribute('aria-pressed')).toBe('true')
    buttons[0]!.click()
    expect(tune.save()).toBe('')
    expect(content.classList.contains('text-center')).toBe(false)
    expect(buttons[0]?.getAttribute('aria-pressed')).toBe('true')
    expect(dispatchChange).toHaveBeenCalledTimes(2)
  })

  it('reflects a stored alignment when settings reopen', () => {
    const tune = new AlignmentTune({
      api: { i18n: { t: (text: string) => text } } as any,
      data: 'right',
    })
    expect(
      tune.render().querySelector('[aria-pressed="true"]')?.getAttribute('aria-label'),
    ).toBe('Align right')
  })
})
