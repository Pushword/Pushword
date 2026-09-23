import { describe, it, expect } from 'vitest'
import { MarkdownUtils } from './MarkdownUtils'

describe('MarkdownUtils.wrapInQuotes', () => {
  it('escapes every quote and backslash in a Twig string', () => {
    expect(MarkdownUtils.wrapInQuotes('a\'b"c"\\d')).toBe('"a\'b\\"c\\"\\\\d"')
  })
})

describe('MarkdownUtils.fixer', () => {
  it('does not mistake iframe for an empty inline formatting tag', () => {
    const html = '<iframe src="/video"></iframe>'
    expect(MarkdownUtils.fixer(html)).toBe(html)
  })

  it('keeps a word-initial â or Â and a pipe after a space', () => {
    const text = 'Le Moyen Âge, les âges | la suite'
    expect(MarkdownUtils.fixer(text)).toBe(text)
  })

  it('keeps the two trailing spaces of a hard line break', () => {
    const text = 'Transfert au bateau.  \nVers 18h : cocktail'
    expect(MarkdownUtils.fixer(text)).toBe(text)
  })

  it('still collapses a run of spaces inside a line', () => {
    expect(MarkdownUtils.fixer('un   deux\u00A0 trois')).toBe('un deux trois')
  })

  it('trims a longer trailing run to a two-space hard break', () => {
    expect(MarkdownUtils.fixer('fin    \nsuite')).toBe('fin  \nsuite')
  })

  it('moves the space from before a comma or a dot to after it', () => {
    expect(MarkdownUtils.fixer('mot\u00A0, suite et fin . Suite')).toBe(
      'mot, suite et fin. Suite',
    )
  })

  it('does not pull a line break into the space-before-punctuation fix', () => {
    const text = '- un ,\n- deux .\n- trois'
    expect(MarkdownUtils.fixer(text)).toBe(text)
  })
})

describe('MarkdownUtils.convertInlineMarkdownToHtml', () => {
  const convert = (markdown: string) => MarkdownUtils.convertInlineMarkdownToHtml(markdown)

  it('does not pair an underscore in a word or a URL with one in a link text', () => {
    expect(convert('le mot_clé, [a](https://x.fr/a_b) puis [_Alpinstore_](https://x.fr)')).toBe(
      'le mot_clé, <a href="https://x.fr/a_b">a</a> puis <a href="https://x.fr"><i>Alpinstore</i></a>',
    )
  })

  it('leaves the underscores of a code span and of link attributes alone', () => {
    expect(convert('`snake_case_name` [a](/b){target="_blank"} et _c_')).toBe(
      '<code class="inline-code">snake_case_name</code> <a href="/b" target="_blank">a</a> et <i>c</i>',
    )
  })

  it('carries a link title into the anchor', () => {
    expect(convert('[le site](https://x.fr "Le titre")')).toBe(
      '<a href="https://x.fr" title="Le titre">le site</a>',
    )
  })

  it('keeps an image as markdown text', () => {
    expect(convert('![a_b](x_y.png) texte')).toBe('![a_b](x_y.png) texte')
  })

  it('keeps an image as text even with a code span in its alt, and inside a code span', () => {
    expect(convert('![`a_b`](x.png)')).toBe('![`a_b`](x.png)')
    expect(convert('`![a](x.png)`')).toBe('<code class="inline-code">![a](x.png)</code>')
  })

  it('opens emphasis only at a word boundary', () => {
    expect(convert('un snake_case_name et déjà_vu_là')).toBe('un snake_case_name et déjà_vu_là')
    expect(convert('2 _ 3 _ 4')).toBe('2 _ 3 _ 4')
    expect(convert('(_ici_), _là_.')).toBe('(<i>ici</i>), <i>là</i>.')
  })

  it('marks an obfuscated link, before its other attributes', () => {
    expect(convert('#[a](/b)')).toBe('<a href="/b" rel="obfuscate">a</a>')
    expect(convert('#[a](/b){target="_blank"}')).toBe(
      '<a href="/b" rel="obfuscate" target="_blank">a</a>',
    )
  })

  it('carries a link title next to the link attributes', () => {
    expect(convert('[a](/b "T"){target="_blank"}')).toBe(
      '<a href="/b" title="T" target="_blank">a</a>',
    )
  })

  it('keeps an image inside a link as markdown text in the anchor', () => {
    expect(convert('Voir [![a_b](x_y.png)](/page) ici')).toBe(
      'Voir <a href="/page">![a_b](x_y.png)</a> ici',
    )
  })

  it('converts emphasis and code inside a link text', () => {
    expect(convert('[**gras** `x_y`](/b)')).toBe(
      '<a href="/b"><b>gras</b> <code class="inline-code">x_y</code></a>',
    )
  })

  it('returns an empty string as is', () => {
    expect(convert('')).toBe('')
  })

  it('turns only a hard line break into a <br>', () => {
    expect(convert('un\ndeux')).toBe('un\ndeux')
    expect(convert('un  \ndeux')).toBe('un<br>deux')
    expect(convert('un\\\ndeux')).toBe('un<br>deux')
  })
})

describe('MarkdownUtils.convertInlineHtmlToMarkdown links', () => {
  it('writes a link title back into the destination', () => {
    expect(
      MarkdownUtils.convertInlineHtmlToMarkdown('<a href="https://x.fr" title="Le titre">le site</a>'),
    ).toBe('[le site](https://x.fr "Le titre")')
  })

  it('writes a link title before the attribute block', () => {
    expect(
      MarkdownUtils.convertInlineHtmlToMarkdown('<a href="/b" title="T" target="_blank">a</a>'),
    ).toBe('[a](/b "T"){target="_blank"}')
  })
})

describe('MarkdownUtils.extractSnippetCall', () => {
  it('extracts the name from a single-quoted call', () => {
    expect(MarkdownUtils.extractSnippetCall("{{ snippet('hero') }}")).toEqual({
      name: 'hero',
      params: {},
    })
  })

  it('extracts the name from a double-quoted call', () => {
    expect(MarkdownUtils.extractSnippetCall('{{ snippet("cta") }}')).toEqual({
      name: 'cta',
      params: {},
    })
  })

  it('tolerates extra whitespace around the name argument', () => {
    expect(MarkdownUtils.extractSnippetCall("{{ snippet(  'box'  ) }}")).toEqual({
      name: 'box',
      params: {},
    })
  })

  it('parses a params object after the name', () => {
    expect(
      MarkdownUtils.extractSnippetCall("{{ snippet('box', { color: 'red', size: 3 }) }}"),
    ).toEqual({ name: 'box', params: { color: 'red', size: 3 } })
  })

  it('returns null when there is no snippet call', () => {
    expect(MarkdownUtils.extractSnippetCall('just some text')).toBeNull()
  })

  it('returns null when the first argument is not a quoted string', () => {
    expect(MarkdownUtils.extractSnippetCall('{{ snippet(foo) }}')).toBeNull()
  })

  it('stops at the end of a truncated call without a closing paren', () => {
    expect(MarkdownUtils.extractSnippetCall("{{ snippet('x'")).toEqual({
      name: 'x',
      params: {},
    })
  })
})

describe('MarkdownUtils.chunkMarkdown', () => {
  /**
   * The rule chunkMarkdown must reproduce byte-for-byte outside fenced code:
   * the parser's historical split. Fences are the documented exception and are
   * covered on their own below.
   */
  function legacyChunks(markdown: string): string[] {
    if (markdown.trim() === '') return []
    return markdown.replace(/\n\s*\n+/g, '\n\n').split('\n\n')
  }

  it.each([
    ['two blocks', 'a\n\nb'],
    ['single block', 'a'],
    ['multi-line block', 'line1\nline2\n\nb'],
    ['extra blank lines', 'a\n\n\n\nb'],
    ['whitespace-only separator lines', 'a\n  \t\nb'],
    ['leading blank lines', '\n\na'],
    ['trailing blank lines', 'a\n\n'],
    ['empty string', ''],
    ['whitespace-only string', '  \n \n '],
  ])('matches the parser split for %s', (_label, markdown) => {
    expect(MarkdownUtils.chunkMarkdown(markdown).map((chunk) => chunk.text)).toEqual(
      legacyChunks(markdown),
    )
  })

  it('maps each chunk to its source lines', () => {
    const chunks = MarkdownUtils.chunkMarkdown('# T\n\npara line1\npara line2\n\nlast')

    expect(chunks).toEqual([
      { text: '# T', startLine: 0, endLine: 0, separatorAfter: '\n\n' },
      { text: 'para line1\npara line2', startLine: 2, endLine: 3, separatorAfter: '\n\n' },
      { text: 'last', startLine: 5, endLine: 5, separatorAfter: '' },
    ])
  })

  it('keeps line positions across collapsed blank-line runs', () => {
    const chunks = MarkdownUtils.chunkMarkdown('a\n\n\n\nb')

    expect(chunks).toEqual([
      { text: 'a', startLine: 0, endLine: 0, separatorAfter: '\n\n\n\n' },
      { text: 'b', startLine: 4, endLine: 4, separatorAfter: '' },
    ])
  })

  it('anchors an empty leading chunk at line zero', () => {
    expect(MarkdownUtils.chunkMarkdown('\n\na')).toEqual([
      { text: '', startLine: 0, endLine: 0, separatorAfter: '\n\n' },
      { text: 'a', startLine: 2, endLine: 2, separatorAfter: '' },
    ])
  })

  it('reports the whitespace a separator actually held', () => {
    expect(
      MarkdownUtils.chunkMarkdown('a\n  \t\nb').map((chunk) => chunk.separatorAfter),
    ).toEqual(['\n  \t\n', ''])
  })
})

describe('MarkdownUtils.chunkMarkdown loose lists', () => {
  const texts = (markdown: string): string[] =>
    MarkdownUtils.chunkMarkdown(markdown).map((chunk) => chunk.text)

  it('keeps an indented sub-list in the chunk of the item it belongs to', () => {
    const list = '* Vélos\n\n    - VTC : 120 €\n\n    - VAE : 315 €'

    expect(texts(`${list}\n\nSome text`)).toEqual([list, 'Some text'])
  })

  it('keeps a second paragraph of an item with its item', () => {
    expect(texts('- one\n\n  more on one\n- two')).toEqual(['- one\n\n  more on one\n- two'])
  })

  it('reads `+` and `1)` markers as list items too', () => {
    expect(texts('+ one\n\n  - sub')).toEqual(['+ one\n\n  - sub'])
    expect(texts('1) one\n\n   more')).toEqual(['1) one\n\n   more'])
  })

  it('measures against the first item, so a return to a shallower sub-item stays in', () => {
    const list = '- a\n\n  - b\n\n    - c\n\n  - d'

    expect(texts(`${list}\n\nafter`)).toEqual([list, 'after'])
  })

  it('still cuts before a line indented less than the item text', () => {
    // `1. ` puts the text three columns in: two spaces start a paragraph after the list.
    expect(texts('1. one\n\n  after')).toEqual(['1. one', '  after'])
  })

  it('reads the item under a block-attribute line', () => {
    expect(texts('{.tight}\n- one\n\n  - sub')).toEqual(['{.tight}\n- one\n\n  - sub'])
  })

  it('cuts an indented line after a paragraph, which is code', () => {
    expect(texts('para\n\n    code')).toEqual(['para', '    code'])
  })

  it('counts the lines it kept together, so the next chunk stays findable', () => {
    expect(MarkdownUtils.chunkMarkdown('- one\n\n  - sub\n\nnext')[1]).toEqual({
      text: 'next',
      startLine: 4,
      endLine: 4,
      separatorAfter: '',
    })
  })
})

describe('MarkdownUtils.chunkMarkdown fenced code', () => {
  const texts = (markdown: string): string[] =>
    MarkdownUtils.chunkMarkdown(markdown).map((chunk) => chunk.text)

  it('keeps a fence holding a blank line in one chunk', () => {
    expect(texts('## Intro\n\n```php\nfoo();\n\nbar();\n```\n\nafter')).toEqual([
      '## Intro',
      '```php\nfoo();\n\nbar();\n```',
      'after',
    ])
  })

  it('does not let a code comment become a heading chunk', () => {
    expect(texts('```php\nfoo();\n\n## Step\nbar();\n```')).toEqual([
      '```php\nfoo();\n\n## Step\nbar();\n```',
    ])
  })

  it('keeps a whitespace-only line inside a fence byte-for-byte', () => {
    expect(texts('```php\nfoo();\n   \nbar();\n```')).toEqual([
      '```php\nfoo();\n   \nbar();\n```',
    ])
  })

  it('counts the fence lines it did not split on, so later chunks stay findable', () => {
    expect(
      MarkdownUtils.chunkMarkdown('## Intro\n\n```php\nfoo();\n\nbar();\n```\n\nafter'),
    ).toEqual([
      { text: '## Intro', startLine: 0, endLine: 0, separatorAfter: '\n\n' },
      {
        text: '```php\nfoo();\n\nbar();\n```',
        startLine: 2,
        endLine: 6,
        separatorAfter: '\n\n',
      },
      { text: 'after', startLine: 8, endLine: 8, separatorAfter: '' },
    ])
  })

  it('opens a second fence once the first has closed', () => {
    expect(texts('```\na\n\nb\n```\n\ntext\n\n```\nc\n\nd\n```')).toEqual([
      '```\na\n\nb\n```',
      'text',
      '```\nc\n\nd\n```',
    ])
  })

  it('splits normally around a fence', () => {
    expect(texts('a\n\n```\ncode\n```\n\nb')).toEqual(['a', '```\ncode\n```', 'b'])
  })

  it('handles tilde fences and longer closing runs', () => {
    expect(texts('~~~js\nx\n\ny\n~~~~\n\nafter')).toEqual(['~~~js\nx\n\ny\n~~~~', 'after'])
  })

  it('does not close a fence on a shorter run', () => {
    expect(texts('````\na\n\n```\n\nb\n````\n\nafter')).toEqual([
      '````\na\n\n```\n\nb\n````',
      'after',
    ])
  })

  it('runs an unclosed fence to the end, as the renderer does', () => {
    expect(texts('intro\n\n```php\nfoo();\n\n## Step')).toEqual([
      'intro',
      '```php\nfoo();\n\n## Step',
    ])
  })

  it('ignores a backtick fence whose info string holds a backtick', () => {
    expect(texts('``` a`b\n\nafter')).toEqual(['``` a`b', 'after'])
  })

  it('treats a four-space indented run as code, not a fence', () => {
    expect(texts('a\n\n    ```\n\nb')).toEqual(['a', '    ```', 'b'])
  })
})

describe('MarkdownUtils.joinChunks', () => {
  it('puts the source separators back positionally', () => {
    expect(MarkdownUtils.joinChunks(['a', 'b'], ['\n\n\n\n'])).toBe('a\n\n\n\nb')
  })

  it('falls back to a blank line when a gap has no separator', () => {
    expect(MarkdownUtils.joinChunks(['a', 'b'], [])).toBe('a\n\nb')
  })

  it('returns a single chunk untouched', () => {
    expect(MarkdownUtils.joinChunks(['only'], [])).toBe('only')
  })
})

describe('MarkdownUtils.convertInlineHtmlToMarkdown typography normalization', () => {
  it('straightens typographic characters so sources stay plain', () => {
    expect(
      MarkdownUtils.convertInlineHtmlToMarkdown(
        'L’ami dit “bonjour”, „hallo“ et ‘salut’…',
      ),
    ).toBe('L\'ami dit "bonjour", "hallo" et \'salut\'...')
  })

  it('replaces no-break spaces and drops zero-width characters', () => {
    expect(
      MarkdownUtils.convertInlineHtmlToMarkdown('Prix : 10 € et ce­la​ fin﻿'),
    ).toBe('Prix : 10 € et cela fin')
  })

  it('normalizes entity-encoded typography too (post-decode)', () => {
    expect(MarkdownUtils.convertInlineHtmlToMarkdown('l&rsquo;ami&hellip;&nbsp;!')).toBe(
      "l'ami... !",
    )
  })

  it('applies the same normalization on the cleanup=false path', () => {
    expect(MarkdownUtils.convertInlineHtmlToMarkdown('l’ami !', false)).toBe("l'ami !")
  })

  it('keeps typographic characters inside a <code> element', () => {
    expect(
      MarkdownUtils.convertInlineHtmlToMarkdown(
        'Voir <code class="inline-code">"café… déjà"</code> et l’exemple…',
      ),
    ).toBe('Voir `"café… déjà"` et l\'exemple...')
  })

  it('keeps a no-break space inside a <code> element, raw or entity-encoded', () => {
    expect(
      MarkdownUtils.convertInlineHtmlToMarkdown('Un <code>a\u00A0b</code> et un\u00A0autre'),
    ).toBe('Un `a\u00A0b` et un autre')
    expect(
      MarkdownUtils.convertInlineHtmlToMarkdown('Un <code>a&nbsp;b</code> et l&rsquo;autre'),
    ).toBe("Un `a\u00A0b` et l'autre")
  })

  it('keeps typographic characters inside literal backticks the author typed', () => {
    expect(
      MarkdownUtils.convertInlineHtmlToMarkdown('Tapez `l’exemple…` puis l’autre…'),
    ).toBe("Tapez `l’exemple…` puis l'autre...")
  })
})

describe('MarkdownUtils.normalizeTypography code protection', () => {
  it('keeps a fenced block byte-identical while straightening the prose around it', () => {
    expect(
      MarkdownUtils.normalizeTypography(
        "L’intro…\n\n```php\n$s = 'café… déjà';\u00A0\n```\n\nL’outro…",
      ),
    ).toBe("L'intro...\n\n```php\n$s = 'café… déjà';\u00A0\n```\n\nL'outro...")
  })

  it('keeps a tilde fence and its info string byte-identical', () => {
    expect(MarkdownUtils.normalizeTypography('~~~text l’info\ncafé…\n~~~\n\nl’après')).toBe(
      "~~~text l’info\ncafé…\n~~~\n\nl'après",
    )
  })

  it('does not close a fence on a shorter run', () => {
    expect(
      MarkdownUtils.normalizeTypography('````\ncafé…\n```\nencore…\n````\n\nl’après…'),
    ).toBe("````\ncafé…\n```\nencore…\n````\n\nl'après...")
  })

  it('protects an unclosed fence to the end, as the renderer reads it', () => {
    expect(MarkdownUtils.normalizeTypography('l’avant\n\n```\ncafé…')).toBe(
      "l'avant\n\n```\ncafé…",
    )
  })

  it('keeps a multi-backtick inline span byte-identical', () => {
    expect(MarkdownUtils.normalizeTypography('l’un `` l’a `b` … `` et l’autre…')).toBe(
      "l'un `` l’a `b` … `` et l'autre...",
    )
  })

  it('treats a backtick without a same-length closer as literal text', () => {
    expect(MarkdownUtils.normalizeTypography('un ` seul et l’ami…')).toBe(
      "un ` seul et l'ami...",
    )
  })

  it('never pairs a code span across a blank line', () => {
    expect(MarkdownUtils.normalizeTypography('un ` deux\n\ntrois ` l’quatre…')).toBe(
      "un ` deux\n\ntrois ` l'quatre...",
    )
  })
})
