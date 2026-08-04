import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'

/**
 * The horizontal scroller is pure CSS, so its worst failures are the ones no
 * rendering test can reach: they only show up in a browser that lacks a feature
 * Chrome has, which is exactly the browser no test here runs. These assertions
 * stand in for that browser.
 *
 * `yarn test` runs from the package root (see .scripts/test), so the path is
 * relative to it — vitest serves CSS imports empty.
 */
const css = readFileSync('src/utility.css', 'utf8')

/** The body of the at-rule whose header contains `needle`, brace-matched. */
const blockOf = (needle) => {
  const start = css.indexOf(needle)
  if (start === -1) return null

  const open = css.indexOf('{', start)
  if (open === -1) return null

  let depth = 0
  for (let i = open; i < css.length; i++) {
    if (css[i] === '{') depth++
    else if (css[i] === '}' && --depth === 0) return css.slice(open + 1, i)
  }

  return null
}

describe('horizontal scroller — guards that only a non-Chromium browser would notice', () => {
  /**
   * The one that bites hardest. An unrecognised `animation-timeline` does not
   * disable the animation: it falls back to the document timeline, where the
   * shorthand's 0s duration plus `both` snaps it straight to its end state —
   * the left edge faded for good and the right edge never. Outside the guard
   * the feature is worse than absent, and Chrome shows nothing wrong.
   */
  it('keeps the scroll-driven fade inside its @supports guard', () => {
    const guarded = blockOf('@supports (animation-timeline')
    expect(guarded).not.toBeNull()
    expect(guarded).toContain('animation-timeline: scroll(self inline)')

    const fadeAnimation = /animation:\s*horizontal-scroll-fade-in/
    expect(css).toMatch(fadeAnimation)
    expect(guarded).toMatch(fadeAnimation)
  })

  /** Same shape for the dots: the marker styling must not escape its guard. */
  it('keeps the dots inside their @supports guard', () => {
    const guarded = blockOf('@supports selector(::scroll-marker-group)')
    expect(guarded).not.toBeNull()
    expect(guarded).toContain('scroll-marker-group: after')
    expect(guarded).toContain('::scroll-marker')
  })

  /** And the arrows, which are the reason the scrollbar is hidden at all. */
  it('hides the scrollbar only where the arrows exist', () => {
    const guarded = blockOf('@supports selector(::scroll-button(inline-end))')
    expect(guarded).not.toBeNull()
    expect(guarded).toContain('scrollbar-width: none')

    // Outside the guard it must stay visible: without arrows it is the only
    // affordance, and the explicit colour is what stops Firefox drawing an
    // overlay scrollbar that shows up only while scrolling.
    const base = blockOf('.horizontal-scroll {')
    expect(base).toContain('scrollbar-width: thin')
    expect(base).toContain('scrollbar-color:')
  })

  /**
   * `overflow-x: hidden` would make the arrows the only way to move the row, so
   * every browser without them would show cards nobody can reach. This is the
   * single decision the whole no-JavaScript design rests on.
   */
  it('never makes the row unscrollable', () => {
    const base = blockOf('.horizontal-scroll {')
    expect(base).toContain('overflow-x: auto')
    expect(base).not.toContain('overflow-x: hidden')
  })

  /**
   * `clip` and not `hidden`: `hidden` creates a scroll container, which breaks
   * `position: sticky` inside it and forces the other axis to `auto`.
   */
  it('guards the page against 100vw bleeds with clip, not hidden', () => {
    const guard = blockOf('body:has(.bleed, .img-stretched)')
    expect(guard).not.toBeNull()
    expect(guard).toContain('overflow-x: clip')
    expect(guard).not.toContain('overflow-x: hidden')
  })

  /**
   * `@property` initial values have to be computationally independent, so they
   * cannot be written in rem and cannot reference --horizontal-scroll-fade.
   * They are the fallback wherever scroll-driven animations are missing, so a
   * drift from the fade default silently changes that fallback.
   */
  it('keeps the fade fallbacks in step with the fade default', () => {
    const fadeDefault = /--horizontal-scroll-fade:\s*([\d.]+)rem/.exec(css)
    expect(fadeDefault).not.toBeNull()

    const expected = `${Number(fadeDefault[1]) * 16}px`
    for (const side of ['start', 'end']) {
      const property = blockOf(`@property --horizontal-scroll-fade-${side}`)
      expect(property).toContain(`initial-value: ${expected}`)
    }
  })
})
