// @vitest-environment jsdom
import { beforeEach, describe, expect, it } from 'vitest'
import { installHtmxCompat } from '../../src/Resources/assets/admin.htmxCompat.js'

const fakeHtmx = { config: { noSwap: [204, 304] } }
installHtmxCompat(fakeHtmx)

const configRequest = (sourceElement) => {
  const e = new CustomEvent('htmx:config:request', {
    cancelable: true,
    detail: { ctx: { sourceElement } },
  })
  document.dispatchEvent(e)
  return e.defaultPrevented
}

describe('installHtmxCompat', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  it('restores the htmx 2 no-swap-on-error behaviour', () => {
    expect(fakeHtmx.config.noSwap).toContain('4xx')
    expect(fakeHtmx.config.noSwap).toContain('5xx')
  })

  it('drops a changed-triggered request whose value equals the rendered one', () => {
    document.body.innerHTML = '<input hx-trigger="blur changed" value="same">'
    expect(configRequest(document.querySelector('input'))).toBe(true)
  })

  it('lets a real edit through', () => {
    document.body.innerHTML = '<input hx-trigger="blur changed" value="old">'
    const input = document.querySelector('input')
    input.value = 'new'
    expect(configRequest(input)).toBe(false)
  })

  it('ignores triggers without the changed modifier', () => {
    document.body.innerHTML = '<input hx-trigger="change" value="same">'
    expect(configRequest(document.querySelector('input'))).toBe(false)
  })

  it('ignores non-form elements and missing context', () => {
    document.body.innerHTML = '<div hx-trigger="blur changed"></div>'
    expect(configRequest(document.querySelector('div'))).toBe(false)
    expect(configRequest(undefined)).toBe(false)
  })
})
