import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ScrollXEnhancer, ScrollYEnhancer } from './ScrollEnhancer.js'

beforeEach(() => {
  document.body.innerHTML = ''
})

afterEach(() => {
  document.body.innerHTML = ''
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
  delete window.scrollLeft
  delete window.scrollPreviousDiv
  delete window.manageScrollXControllerVisibility
  delete window.manageScrollYControllerVisibility
  delete window.isScrolling
  delete window.lastScrollTime
})

describe('ScrollEnhancer', () => {
  it('updates the vertical controller when its element scrolls', () => {
    document.body.innerHTML = '<div><div class="enhance-scroll-y"></div></div>'
    const element = document.querySelector('.enhance-scroll-y')
    Object.defineProperties(element, {
      scrollHeight: { value: 200 },
      clientHeight: { value: 100 },
    })

    new ScrollYEnhancer()
    element.scrollTop = 100
    element.dispatchEvent(new Event('scroll'))

    expect(document.querySelector('.scroller').textContent).toBe('⌃')
  })

  it('updates the horizontal controllers on initialization and scroll', () => {
    document.body.innerHTML = '<div><div class="enhance-scroll-x"></div></div>'
    const element = document.querySelector('.enhance-scroll-x')
    element.dataset.arrowleft = '<button class="scroll-left">Previous</button>'
    element.dataset.arrowright = '<button class="scroll-right">Next</button>'
    Object.defineProperties(element, {
      scrollWidth: { value: 200 },
      clientWidth: { value: 100 },
    })
    // The constructor exposes its click handler under the browser's scrollX name.
    vi.stubGlobal('scrollX', window.scrollX)

    new ScrollXEnhancer()

    const previous = document.querySelector('.scroll-left')
    const next = document.querySelector('.scroll-right')
    expect(previous.classList.contains('opacity-30')).toBe(true)
    expect(next.classList.contains('opacity-30')).toBe(false)

    element.scrollLeft = 100
    element.dispatchEvent(new Event('scroll'))

    expect(previous.classList.contains('opacity-30')).toBe(false)
    expect(next.classList.contains('opacity-30')).toBe(true)
  })

  it.each([
    [20, '.scroll-right'],
    [-20, '.scroll-left'],
  ])(
    'hands off a blocked vertical wheel delta %s to its horizontal parent',
    (deltaY, selector) => {
      document.body.innerHTML = `
      <div>
        <button class="scroll-left">Previous</button>
        <button class="scroll-right">Next</button>
        <div class="enhance-scroll-x"><div class="enhance-scroll-y"></div></div>
      </div>`
      const element = document.querySelector('.enhance-scroll-y')
      Object.defineProperty(element, 'scrollTop', { get: () => 0, set: () => {} })
      const horizontalScroll = vi.fn().mockReturnValue(true)
      vi.stubGlobal('scrollX', horizontalScroll)
      vi.stubGlobal('scrollBy', vi.fn())
      const browserParent = window.parent
      const enhancer = new ScrollYEnhancer('.no-elements')
      window.lastScrollTime = 0
      enhancer.wheelScrollY(element)

      const event = new WheelEvent('wheel', { deltaY, cancelable: true })
      element.dispatchEvent(event)

      expect(event.defaultPrevented).toBe(true)
      expect(horizontalScroll).toHaveBeenCalledWith(document.querySelector(selector))
      expect(window.parent).toBe(browserParent)
      expect(window.scrollBy).not.toHaveBeenCalled()
      expect(window.isScrolling).toBe(false)
    },
  )
})
