// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const load = () => import('../../../newsletter/src/Resources/public/statistics.js')

beforeEach(() => {
  vi.resetModules()
  document.body.innerHTML = `<form data-statistics-filters>
    <input type="date" name="from" value="2026-09-23">
    <input type="date" name="to" value="2026-10-22" min="2026-09-23">
  </form>`
})

afterEach(() => {
  document.body.innerHTML = ''
})

describe('statistics date range', () => {
  it('accepts an earlier historical range after editing the start', async () => {
    await load()
    const from = document.querySelector('[name=from]')
    const to = document.querySelector('[name=to]')
    from.value = '2026-01-01'
    from.dispatchEvent(new Event('input'))
    to.value = '2026-01-31'
    expect(to.min).toBe('2026-01-01')
    expect(document.querySelector('form').checkValidity()).toBe(true)
  })

  it('rejects an end before the edited start and removes a cleared bound', async () => {
    await load()
    const from = document.querySelector('[name=from]')
    const to = document.querySelector('[name=to]')
    from.value = '2026-11-01'
    from.dispatchEvent(new Event('change'))
    expect(to.validity.rangeUnderflow).toBe(true)
    from.value = ''
    from.dispatchEvent(new Event('input'))
    expect(to.min).toBe('')
    expect(to.validity.rangeUnderflow).toBe(false)
  })

  it('can load on campaign reports without a filter form', async () => {
    document.body.innerHTML = ''
    await expect(load()).resolves.toBeDefined()
  })
})
