import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import Observer from './Observer'

/**
 * MutationObserver delivery is scheduled by the DOM implementation, so the
 * suite drives the handler directly: what matters here is which mutations count
 * as a content change and that a burst of them registers once.
 */
type AnyObserver = Record<string, any>

function newObserver(
  registerChange: () => void,
  debounce = 200,
): { observer: AnyObserver; holder: HTMLElement } {
  const holder = document.createElement('div')
  const redactor = document.createElement('div')
  redactor.className = 'codex-editor__redactor'
  holder.appendChild(redactor)
  document.body.appendChild(holder)

  return {
    observer: new Observer(registerChange, holder, debounce) as unknown as AnyObserver,
    holder,
  }
}

function mutation(type: string, target: Element | Node): any {
  return { type, target }
}

function elementWith(className: string): HTMLElement {
  const element = document.createElement('div')
  element.className = className

  return element
}

beforeEach(() => {
  vi.useFakeTimers()
  document.body.innerHTML = ''
})

afterEach(() => {
  vi.useRealTimers()
})

describe('Observer – what counts as a change', () => {
  it('registers text edits and blocks appearing or disappearing', () => {
    const registerChange = vi.fn()
    const { observer } = newObserver(registerChange)

    observer.mutationHandler([mutation('characterData', elementWith('ce-paragraph'))])
    vi.advanceTimersByTime(200)
    expect(registerChange).toHaveBeenCalledTimes(1)

    observer.mutationHandler([mutation('childList', elementWith('ce-block'))])
    vi.advanceTimersByTime(200)
    expect(registerChange).toHaveBeenCalledTimes(2)
  })

  it('ignores a block being selected and the table toolbox moving', () => {
    const registerChange = vi.fn()
    const { observer } = newObserver(registerChange)

    observer.mutationHandler([
      mutation('attributes', elementWith('ce-block ce-block--selected')),
      mutation('attributes', elementWith('tc-toolbox')),
    ])
    vi.advanceTimersByTime(500)

    expect(registerChange).not.toHaveBeenCalled()
  })

  it('registers an attribute change anywhere else', () => {
    const registerChange = vi.fn()
    const { observer } = newObserver(registerChange)

    observer.mutationHandler([mutation('attributes', elementWith('cdx-list__item'))])
    vi.advanceTimersByTime(200)

    expect(registerChange).toHaveBeenCalledTimes(1)
  })

  it('treats the holder losing children as a teardown, not a change', () => {
    const registerChange = vi.fn()
    const { observer, holder } = newObserver(registerChange)
    const destroyed = vi.fn()
    document.addEventListener('destroy', destroyed)

    observer.mutationHandler([mutation('childList', holder)])
    vi.advanceTimersByTime(500)

    expect(registerChange).not.toHaveBeenCalled()
    expect(destroyed).toHaveBeenCalled()
    document.removeEventListener('destroy', destroyed)
  })
})

describe('Observer – debouncing', () => {
  it('registers a burst of mutations once, after it settles', () => {
    const registerChange = vi.fn()
    const { observer } = newObserver(registerChange, 200)

    for (let i = 0; i < 5; i += 1) {
      observer.mutationHandler([mutation('characterData', elementWith('ce-paragraph'))])
      vi.advanceTimersByTime(50)
    }

    expect(registerChange).not.toHaveBeenCalled()
    vi.advanceTimersByTime(200)
    expect(registerChange).toHaveBeenCalledTimes(1)
  })
})

describe('Observer – attaching', () => {
  it('does nothing when the editor has not rendered its redactor', () => {
    const holder = document.createElement('div')
    document.body.appendChild(holder)
    const observer = new Observer(vi.fn(), holder, 200)

    expect(() => observer.setMutationObserver()).not.toThrow()
  })
})
