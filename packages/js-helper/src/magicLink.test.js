import { readFileSync } from 'node:fs'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const script = readFileSync('../core/src/Resources/assets/magicLink.js', 'utf8')

describe('magic link automatic connection', () => {
  let listeners
  let submit

  beforeEach(() => {
    vi.useFakeTimers()
    listeners = {}
    document.body.innerHTML = '<form data-magic-login><button type="submit" name="action" value="connect">Connect</button></form>'
    submit = vi.spyOn(HTMLFormElement.prototype, 'requestSubmit').mockImplementation(() => {})
    vi.spyOn(document, 'addEventListener').mockImplementation((name, callback) => {
      listeners[name] = callback
    })
    Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'visible' })
    Object.defineProperty(document, 'prerendering', { configurable: true, value: false })
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
  })

  function run() {
    new Function('document', script)(document)
  }

  it('submits once and includes the connect button action', () => {
    run()
    listeners.visibilitychange()
    listeners.prerenderingchange()
    expect(submit).toHaveBeenCalledExactlyOnceWith(document.querySelector('button'))
  })

  it('waits for a prerendered page to become active', () => {
    Object.defineProperty(document, 'prerendering', { configurable: true, value: true })
    run()
    expect(submit).not.toHaveBeenCalled()
    Object.defineProperty(document, 'prerendering', { configurable: true, value: false })
    listeners.prerenderingchange()
    expect(submit).toHaveBeenCalledOnce()
  })

  it('does not submit from a hidden page', () => {
    Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'hidden' })
    run()
    expect(submit).not.toHaveBeenCalled()
    Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'visible' })
    listeners.visibilitychange()
    expect(submit).toHaveBeenCalledOnce()
  })

  it('leaves recovery forms alone', () => {
    document.querySelector('form').removeAttribute('data-magic-login')
    run()
    expect(submit).not.toHaveBeenCalled()
  })

  function recovery(seconds) {
    document.body.innerHTML = `<button data-magic-resend data-resend-seconds="${seconds}">Resend</button>
      <p data-magic-resend-status data-wait-message="Available in {seconds} s."></p>`
    run()
    return document.querySelector('button')
  }

  it('disables resend only until the existing cooldown expires', () => {
    const button = recovery(60)
    expect(button.disabled).toBe(true)
    expect(document.querySelector('p').textContent).toBe('Available in 60 s.')
    vi.advanceTimersByTime(60_000)
    expect(button.disabled).toBe(false)
    expect(document.querySelector('p').hidden).toBe(true)
    expect(vi.getTimerCount()).toBe(0)
    expect(submit).not.toHaveBeenCalled()
  })

  it('recomputes remaining time when a background tab resumes', () => {
    const startedAt = Date.now()
    const button = recovery(60)
    vi.setSystemTime(startedAt + 61_000)
    listeners.visibilitychange()
    expect(button.disabled).toBe(false)
    expect(document.querySelector('p').hidden).toBe(true)
  })

  it('does not disable an already available code request', () => {
    expect(recovery(0).disabled).toBe(false)
    expect(vi.getTimerCount()).toBe(0)
  })
})
