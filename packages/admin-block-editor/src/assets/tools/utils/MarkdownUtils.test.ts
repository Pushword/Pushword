import { describe, it, expect } from 'vitest'
import { MarkdownUtils } from './MarkdownUtils'

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
