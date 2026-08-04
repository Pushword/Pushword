import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'

/**
 * `app.css` is the stylesheet every Pushword site builds from, and its @source
 * list is what makes bundle templates visible to Tailwind. The failure mode is
 * silent: a pattern resolving to a directory rather than to files matches
 * nothing at all, no warning, and the only symptom is a smaller stylesheet with
 * every bundle-template class purged. That is how the vendor glob shipped
 * broken. These assertions are the guard — no rendering test can see a purged
 * class, because the markup still carries it.
 *
 * `yarn test` runs from the package root (see .scripts/test), so the path is
 * relative to it rather than to this file: vitest serves CSS imports empty and
 * does not give this module a file:// url to resolve against.
 */
const css = readFileSync('src/app.css', 'utf8')

/** Drop comments the way a CSS parser does: a comment ends on its first `*` + `/`,
 * wherever that falls — including inside a glob someone spelled out in prose. */
const stripComments = (input) => {
  let code = ''
  let quote = null
  for (let index = 0; index < input.length; index++) {
    const char = input[index]
    if (quote !== null) {
      code += char
      if (char === quote) quote = null
    } else if (char === '"' || char === "'") {
      quote = char
      code += char
    } else if (char === '/' && input[index + 1] === '*') {
      const end = input.indexOf('*/', index + 2)
      index = end === -1 ? input.length : end + 1
      code += ' '
    } else {
      code += char
    }
  }
  return code
}

const code = stripComments(css)
const sources = [...code.matchAll(/@source\s+"([^"]+)"/g)].map((match) => match[1])

describe('app.css tailwind @source list', () => {
  it('declares sources', () => {
    expect(sources.length).toBeGreaterThan(0)
  })

  it('starts every @source on its own statement', () => {
    // A comment cut short by a glob leaves its remaining prose in the code, and
    // that prose parses as a declaration running to the next `;` — swallowing
    // the directive on that line. Tailwind never registers it; the build says
    // nothing louder than an "unexpected token" warning from the CSS optimizer.
    for (const match of code.matchAll(/@source\s+"[^"]+"/g)) {
      const before = code.slice(0, match.index).trimEnd()
      expect(before === '' || before.endsWith(';') || before.endsWith('}')).toBe(true)
    }
  })

  it.each(sources.filter((source) => source.includes('*')))('%s resolves to files, not a directory', (source) => {
    // A glob only ever matches files, so it cannot end on a directory. The last
    // segment may still be a bare `*` — that matches every file in that folder.
    expect(source.endsWith('/')).toBe(false)
  })

  it('covers bundle templates for both the monorepo and an installed site', () => {
    // Installed, this file sits in node_modules/@pushword/js-helper/src/, so
    // ../../../../ is the project root; in the monorepo ../../ is packages/.
    expect(sources).toContain('./../../../../vendor/pushword/*/src/templates/**/*.twig')
    expect(sources).toContain('./../../*/src/templates/**/*.twig')
  })
})
