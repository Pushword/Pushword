import { describe, expect, it } from 'vitest'
import {
  applyEdits,
  computeEnter,
  headingLevel,
  insertImage,
  insertLink,
  isLinkTarget,
  linkifyPaste,
  selectedLinesRange,
  shiftHeading,
  toggleCode,
  toggleHeading,
  toggleLinePrefix,
  toggleTask,
  toggleWrap,
  wordRangeAt,
} from './markdownActions'

/** Runs an action and returns the resulting text with the selection marked as `|` / `[…]`. */
function render(text, result) {
  const next = applyEdits(text, result)
  const [start, end] = result.selection

  return start === end
    ? `${next.slice(0, start)}|${next.slice(start)}`
    : `${next.slice(0, start)}[${next.slice(start, end)}]${next.slice(end)}`
}

describe('wordRangeAt', () => {
  it('expands to the word under the cursor', () => {
    expect(wordRangeAt('one deux trois', 6)).toEqual([4, 8])
  })

  it('keeps accented letters inside the word', () => {
    expect(wordRangeAt('un café serré', 5)).toEqual([3, 7])
  })

  it('stays empty between two spaces', () => {
    expect(wordRangeAt('a  b', 2)).toEqual([2, 2])
  })
})

describe('toggleWrap', () => {
  it('wraps the selection', () => {
    expect(render('hello world', toggleWrap('hello world', 6, 11, '**'))).toBe(
      'hello **[world]**',
    )
  })

  it('wraps the word under the cursor when nothing is selected', () => {
    expect(render('hello world', toggleWrap('hello world', 8, 8, '**'))).toBe(
      'hello **[world]**',
    )
  })

  it('unwraps when the markers surround the selection', () => {
    expect(render('a **bold** b', toggleWrap('a **bold** b', 4, 8, '**'))).toBe(
      'a [bold] b',
    )
  })

  it('unwraps when the markers are inside the selection', () => {
    expect(render('a **bold** b', toggleWrap('a **bold** b', 2, 10, '**'))).toBe(
      'a [bold] b',
    )
  })

  it('nests italic inside bold instead of eating one of the stars', () => {
    expect(render('**bold**', toggleWrap('**bold**', 2, 6, '*'))).toBe('***[bold]***')
  })

  it('unwraps italic when the star is on its own', () => {
    expect(render('*it*', toggleWrap('*it*', 1, 3, '*'))).toBe('[it]')
  })

  it('inserts an empty pair when the cursor sits on no word', () => {
    expect(render('a  b', toggleWrap('a  b', 2, 2, '**'))).toBe('a **|** b')
  })
})

describe('headings', () => {
  it('reads the level', () => {
    expect(headingLevel('### Titre')).toBe(3)
    expect(headingLevel('#NoSpace')).toBe(0)
  })

  it('adds a level and keeps the caret on the same character', () => {
    expect(render('Titre', toggleHeading('Titre', 2, 2))).toBe('## Ti|tre')
  })

  it('replaces an existing level', () => {
    expect(render('## Titre', toggleHeading('## Titre', 5, 3))).toBe('### Ti|tre')
  })

  it('strips the heading when pressing the level it already has', () => {
    expect(render('## Titre', toggleHeading('## Titre', 5, 2))).toBe('Ti|tre')
  })

  it('walks the level up and down', () => {
    expect(applyEdits('## Titre', shiftHeading('## Titre', 4, 1))).toBe('### Titre')
    expect(applyEdits('## Titre', shiftHeading('## Titre', 4, -1))).toBe('# Titre')
    expect(applyEdits('# Titre', shiftHeading('# Titre', 3, -1))).toBe('Titre')
  })

  it('stops at the bounds', () => {
    expect(shiftHeading('Titre', 2, -1)).toBeNull()
    expect(shiftHeading('###### Titre', 8, 1)).toBeNull()
  })

  it('only touches the line under the cursor', () => {
    expect(applyEdits('un\ndeux', toggleHeading('un\ndeux', 4, 2))).toBe('un\n## deux')
  })
})

describe('toggleLinePrefix', () => {
  it('bullets every selected line', () => {
    expect(applyEdits('un\ndeux', toggleLinePrefix('un\ndeux', 0, 7, 'ul'))).toBe(
      '- un\n- deux',
    )
  })

  it('removes the bullets when every line already has one', () => {
    expect(
      applyEdits('- un\n- deux', toggleLinePrefix('- un\n- deux', 0, 11, 'ul')),
    ).toBe('un\ndeux')
  })

  it('numbers an ordered list from one', () => {
    expect(
      applyEdits('un\ndeux\ntrois', toggleLinePrefix('un\ndeux\ntrois', 0, 13, 'ol')),
    ).toBe('1. un\n2. deux\n3. trois')
  })

  it('converts a bullet list into an ordered one', () => {
    expect(
      applyEdits('- un\n- deux', toggleLinePrefix('- un\n- deux', 0, 11, 'ol')),
    ).toBe('1. un\n2. deux')
  })

  it('keeps the indentation', () => {
    expect(applyEdits('  un', toggleLinePrefix('  un', 3, 3, 'ul'))).toBe('  - un')
  })

  it('quotes in front of a list marker rather than replacing it', () => {
    expect(applyEdits('- un', toggleLinePrefix('- un', 2, 2, 'quote'))).toBe('> - un')
  })

  it('skips blank lines inside a multi-line selection', () => {
    expect(applyEdits('un\n\ndeux', toggleLinePrefix('un\n\ndeux', 0, 8, 'ul'))).toBe(
      '- un\n\n- deux',
    )
  })

  it('marks a lone blank line so an empty document can start a list', () => {
    expect(applyEdits('', toggleLinePrefix('', 0, 0, 'ul'))).toBe('- ')
  })

  it('does not drag in the next line when the selection ends on a line break', () => {
    expect(selectedLinesRange('un\ndeux', 0, 3)).toEqual([0, 2])
  })
})

describe('toggleTask', () => {
  it('turns a plain line into a task', () => {
    expect(applyEdits('acheter du pain', toggleTask('acheter du pain', 0, 0))).toBe(
      '- [ ] acheter du pain',
    )
  })

  it('keeps an existing bullet', () => {
    expect(applyEdits('- acheter', toggleTask('- acheter', 3, 3))).toBe('- [ ] acheter')
  })

  it('checks and unchecks', () => {
    expect(applyEdits('- [ ] a', toggleTask('- [ ] a', 6, 6))).toBe('- [x] a')
    expect(applyEdits('- [x] a', toggleTask('- [x] a', 6, 6))).toBe('- [ ] a')
  })
})

describe('toggleCode', () => {
  it('uses backticks on one line', () => {
    expect(render('run it', toggleCode('run it', 4, 6))).toBe('run `[it]`')
  })

  it('fences a multi-line selection', () => {
    expect(applyEdits('a\nb', toggleCode('a\nb', 0, 3))).toBe('```\na\nb\n```')
  })

  it('unfences an already fenced block', () => {
    expect(applyEdits('```\na\n```', toggleCode('```\na\n```', 0, 9))).toBe('a')
  })
})

describe('links', () => {
  it('parks the caret inside the parentheses', () => {
    expect(render('voir ici', insertLink('voir ici', 5, 8))).toBe('voir [ici](|)')
  })

  it('selects the image placeholder', () => {
    expect(render('logo', insertImage('logo', 0, 4))).toBe(
      '![logo]([/media/default/...])',
    )
  })

  it('recognises what can be linked', () => {
    expect(isLinkTarget('https://example.com/a')).toBe(true)
    expect(isLinkTarget('/une-page')).toBe(true)
    expect(isLinkTarget('mailto:a@b.c')).toBe(true)
    expect(isLinkTarget('juste du texte')).toBe(false)
  })

  it('turns a pasted URL over a selection into a link', () => {
    expect(
      applyEdits('voir ici', linkifyPaste('voir ici', 5, 8, 'https://example.com')),
    ).toBe('voir [ici](https://example.com)')
  })

  it('leaves the paste alone without a selection', () => {
    expect(linkifyPaste('voir ', 5, 5, 'https://example.com')).toBeNull()
  })

  it('leaves the paste alone when it is not a URL', () => {
    expect(linkifyPaste('voir ici', 5, 8, 'du texte')).toBeNull()
  })
})

describe('computeEnter', () => {
  it('carries a bullet over', () => {
    expect(render('- un', computeEnter('- un', 4))).toBe('- un\n- |')
  })

  it('increments an ordered marker', () => {
    expect(render('3. un', computeEnter('3. un', 5))).toBe('3. un\n4. |')
  })

  it('keeps the indentation and the closing character', () => {
    expect(render('  2) un', computeEnter('  2) un', 7))).toBe('  2) un\n  3) |')
  })

  it('starts an unchecked box after a task', () => {
    expect(render('- [x] un', computeEnter('- [x] un', 8))).toBe('- [x] un\n- [ ] |')
  })

  it('clears an empty item instead of adding one more', () => {
    expect(render('- un\n- ', computeEnter('- un\n- ', 7))).toBe('- un\n|')
  })

  it('carries a blockquote over', () => {
    expect(render('> un', computeEnter('> un', 4))).toBe('> un\n> |')
  })

  it('splits the line when the caret sits in the middle', () => {
    expect(render('- undeux', computeEnter('- undeux', 4))).toBe('- un\n- |deux')
  })

  it('leaves Monaco alone outside a list', () => {
    expect(computeEnter('du texte', 8)).toBeNull()
    expect(computeEnter('> ', 2)).toBeNull()
  })

  it('leaves Monaco alone when the caret is before the marker', () => {
    expect(computeEnter('- un', 0)).toBeNull()
  })
})
