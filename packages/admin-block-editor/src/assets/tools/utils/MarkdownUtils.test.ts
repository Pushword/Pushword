import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
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

  it('adds the missing space after a prose comma', () => {
    expect(MarkdownUtils.fixer('mot ,suite puis autre,suite')).toBe(
      'mot, suite puis autre, suite',
    )
    expect(MarkdownUtils.fixer('1,7 million')).toBe('1,7 million')
  })

  it('does not clean punctuation or whitespace inside markup attributes', () => {
    expect(
      MarkdownUtils.fixer(
        '<span data-html="<b>a,b</b>" class="x  y">mot,suite et <em>fin,</em></span>',
      ),
    ).toBe('<span data-html="<b>a,b</b>" class="x  y">mot, suite et <em>fin,</em></span>')
    expect(MarkdownUtils.fixer('Voir example.com et fichier.php')).toBe(
      'Voir example.com et fichier.php',
    )
  })

  it('removes a space before punctuation without pulling in a line break', () => {
    const text = '- un ,\n- deux .\n- trois'
    expect(MarkdownUtils.fixer(text)).toBe('- un,\n- deux .\n- trois')
  })
})

describe('MarkdownUtils.convertInlineMarkdownToHtml', () => {
  const convert = (markdown: string) => MarkdownUtils.convertInlineMarkdownToHtml(markdown)

  it('displays escaped hotel stars without their Markdown backslashes', () => {
    expect(convert(String.raw`Nuit en hôtel 2\* ou 3\* en B&B à Moissac.`)).toBe(
      'Nuit en hôtel 2* ou 3* en B&B à Moissac.',
    )
  })

  it('keeps escaped stars literal next to real bold text', () => {
    expect(convert(String.raw`\*\*littéral\*\* et **gras**`)).toBe('**littéral** et <b>gras</b>')
  })

  it('leaves star escapes inside code and images alone', () => {
    expect(convert('`2\\*` ![2\\*](hotel.png)')).toBe(
      '<code class="inline-code">2\\*</code> ![2\\*](hotel.png)',
    )
  })

  it('preserves literal stars and bold after editing and reimporting a paragraph', () => {
    const html = convert(String.raw`\*\*littéral\*\* et **gras** en hôtel 2\*`)
    const markdown = MarkdownUtils.convertInlineHtmlToMarkdown(`${html} corrigé`)
    expect(convert(markdown)).toBe('**littéral** et <b>gras</b> en hôtel 2* corrigé')
  })

  it('round-trips literal hotel stars from a paragraph DOM innerHTML', () => {
    const paragraph = document.createElement('p')
    paragraph.textContent = 'Nuit en hôtel 2* ou 3* en B&B à Moissac.'

    const markdown = MarkdownUtils.convertInlineHtmlToMarkdown(paragraph.innerHTML)

    expect(markdown).toBe(String.raw`Nuit en hôtel 2\* ou 3\* en B&B à Moissac.`)
    expect(convert(markdown)).toBe(paragraph.textContent)
  })

  it.each([0, 1, 2, 3])(
    'round-trips a literal star preceded by %i backslashes',
    (backslashCount) => {
      const htmlText = `avant ${'\\'.repeat(backslashCount)}* après`
      const markdown = MarkdownUtils.convertInlineHtmlToMarkdown(htmlText)

      expect(markdown).toBe(`avant ${'\\'.repeat(backslashCount * 2 + 1)}* après`)
      expect(convert(markdown)).toBe(htmlText)
    },
  )

  it('escapes link text stars while preserving link attributes and code contents', () => {
    const markdown = MarkdownUtils.convertInlineHtmlToMarkdown(
      String.raw`<a href="/hotel*">Hôtel 2*</a> et <code>2*\\suite</code>`,
    )

    expect(markdown).toBe('[Hôtel 2\\*](/hotel*) et `2*\\\\suite`')
    expect(convert(markdown)).toBe(
      String.raw`<a href="/hotel*">Hôtel 2*</a> et <code class="inline-code">2*\\suite</code>`,
    )
  })

  it('leaves stars and backslashes inside Twig tags and literal images unescaped', () => {
    expect(
      MarkdownUtils.convertInlineHtmlToMarkdown(
        String.raw`2* {{ price * 2 }} {% set path = 'a\b' %} ![2*](hotel*.png)`,
      ),
    ).toBe(String.raw`2\* {{ price * 2 }} {% set path = 'a\b' %} ![2*](hotel*.png)`)
  })

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
  it('keeps only the text of an anchor without attributes, which leads nowhere', () => {
    expect(MarkdownUtils.convertInlineHtmlToMarkdown('Texte <a>de <b>dépa</b></a>rt')).toBe(
      'Texte de **dépa**rt',
    )
  })

  it('unwraps only the bare anchor when a real link follows it', () => {
    // The bare-anchor match must stop at its own </a>, not run on to the link's.
    expect(
      MarkdownUtils.convertInlineHtmlToMarkdown('<a>voir</a> ou <a href="/velo">le vélo</a>'),
    ).toBe('voir ou [le vélo](/velo)')
  })

  it('keeps the space a selection took into a bare anchor', () => {
    expect(MarkdownUtils.convertInlineHtmlToMarkdown('Texte <a>de </a>départ')).toBe(
      'Texte de départ',
    )
  })

  it('drops an empty bare anchor even without the fixer', () => {
    expect(MarkdownUtils.convertInlineHtmlToMarkdown('Texte<a></a> départ', false)).toBe(
      'Texte départ',
    )
  })

  it('still writes an anchor with attributes but no href as a link', () => {
    // Only the attribute-less anchor is dropped; a rel alone keeps the link form.
    expect(MarkdownUtils.convertInlineHtmlToMarkdown('<a rel="nofollow">x</a>')).toBe(
      '[x](#){rel="nofollow"}',
    )
  })

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

describe('MarkdownUtils.extractJsonCall', () => {
  it('parses an object or an array and hands back the arguments after it', () => {
    expect(
      MarkdownUtils.extractJsonCall('gallery', '{{ gallery({"a.jpg": "A"}) }}'),
    ).toEqual({
      json: { 'a.jpg': 'A' },
      args: '',
    })
    expect(
      MarkdownUtils.extractJsonCall(
        'card_list',
        "{{ card_list([{'title': 'T'}], 'grid', 'cards') }}",
      ),
    ).toEqual({ json: [{ title: 'T' }], args: "'grid', 'cards'" })
  })

  it('reads a named first argument', () => {
    expect(
      MarkdownUtils.extractJsonCall(
        'gallery',
        '{{ gallery(images: {"a.jpg": ""}, clickable: true) }}',
      ),
    ).toEqual({ json: { 'a.jpg': '' }, args: 'clickable: true' })
  })

  it('does not end the JSON on a bracket or a closing call inside a string', () => {
    expect(
      MarkdownUtils.extractJsonCall(
        'gallery',
        '{{ gallery({"a.jpg": "x }) }} [y", "b.jpg": "\\"}"}) }}',
      ),
    ).toEqual({ json: { 'a.jpg': 'x }) }} [y', 'b.jpg': '"}' }, args: '' })
  })

  it('returns null unless the block is that one call with a JSON first argument', () => {
    expect(
      MarkdownUtils.extractJsonCall('gallery', '{{ gallery({"a.jpg": ""}) }} and text'),
    ).toBeNull()
    expect(
      MarkdownUtils.extractJsonCall('gallery', 'See {{ gallery({"a.jpg": ""}) }}'),
    ).toBeNull()
    expect(MarkdownUtils.extractJsonCall('gallery', "{{ gallery('a.jpg') }}")).toBeNull()
    expect(
      MarkdownUtils.extractJsonCall('gallery', '{{ gallery({"a.jpg": ""}) x }}'),
    ).toBeNull()
    expect(
      MarkdownUtils.extractJsonCall('gallery', '{{ gallery({"a.jpg": "" }}'),
    ).toBeNull()
    expect(MarkdownUtils.extractJsonCall('card_list', '{{ gallery([]) }}')).toBeNull()
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
        'L’ami dit “bonjour”, „hallo“, «cité», ‘salut’ et ‹bis›…',
      ),
    ).toBe('L\'ami dit "bonjour", "hallo", "cité", \'salut\' et \'bis\'...')
  })

  it('normalizes decomposed Unicode in prose', () => {
    expect(
      MarkdownUtils.convertInlineHtmlToMarkdown('Cafe\u0301 et e\u0301te\u0301'),
    ).toBe('Café et été')
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
  it('keeps decomposed Unicode in code while normalizing prose to NFC', () => {
    expect(MarkdownUtils.normalizeTypography('e\u0301crit `e\u0301crit`')).toBe(
      'écrit `e\u0301crit`',
    )
  })

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

describe('MarkdownUtils.normalizeTypography Twig protection', () => {
  it('keeps Twig tags byte-identical while straightening the prose around them', () => {
    const include =
      "{% include 'component/home.html.twig' with {\n  expertise: {\n    title: 'De l’acquisition…',\n    items: [{title: 'Être choisi'}],\n  },\n} only %}"
    const print = "{{ tel('l’accueil') }}"
    expect(
      MarkdownUtils.normalizeTypography(`${include}\n\nAppelez l’accueil au ${print} ou l’après…`),
    ).toBe(`${include}\n\nAppelez l'accueil au ${print} ou l'après...`)
  })

  it('closes a Twig tag only outside brackets and strings', () => {
    const nested = "{{ gallery({'a.jpg': {alt: 'l’été'}}) }}"
    const quoted = "{{ 'fin }} l’été' }}"
    expect(MarkdownUtils.normalizeTypography(`${nested} l’un ${quoted} l’autre`)).toBe(
      `${nested} l'un ${quoted} l'autre`,
    )
  })

  it('straightens an unclosed Twig tag and a Twig comment', () => {
    expect(MarkdownUtils.normalizeTypography('{# l’ami #} et {{ l’autre')).toBe(
      "{# l'ami #} et {{ l'autre",
    )
  })

  it('skips escaped quotes inside Twig strings', () => {
    const single = "{{ 'l\\'été }} l’un' }}"
    const double = '{{ "dit \\"}}\\" l’autre" }}'
    expect(MarkdownUtils.normalizeTypography(`${single} ${double} l’après`)).toBe(
      `${single} ${double} l'après`,
    )
  })

  it('keeps Twig tags before, between and after code ranges', () => {
    expect(
      MarkdownUtils.normalizeTypography(
        "{{ a('l’x') }}`{{ 'l’y' }}` l’z {% set b = 'l’w' %}\n\n```\nl’v…\n```\n\n{{ c('l’u') }} l’t…",
      ),
    ).toBe(
      "{{ a('l’x') }}`{{ 'l’y' }}` l'z {% set b = 'l’w' %}\n\n```\nl’v…\n```\n\n{{ c('l’u') }} l't...",
    )
  })

  it('does not let an unclosed Twig tag hide a later one', () => {
    const print = "{{ tel('l’accueil') }}"
    expect(MarkdownUtils.normalizeTypography(`{{ l’un puis ${print} et l’autre`)).toBe(
      `{{ l'un puis ${print} et l'autre`,
    )
  })
})

describe('MarkdownUtils.formatMarkdownWithPrettier', () => {
  let Utils: typeof MarkdownUtils
  let injected: HTMLScriptElement[]
  const format = vi.fn(async (markdown: string) => `${markdown}\n\n`)
  const plugin = {}

  beforeEach(async () => {
    // A fresh module graph, so no Prettier bundle counts as fetched yet.
    vi.resetModules()
    ;({ MarkdownUtils: Utils } = await import('./MarkdownUtils'))
    injected = []
    vi.spyOn(document.head, 'appendChild').mockImplementation((node) => {
      injected.push(node as HTMLScriptElement)
      return node
    })
    format.mockClear()
    ;(window as any).prettier = { format }
    ;(window as any).prettierPlugins = { markdown: plugin }
  })

  afterEach(() => {
    vi.restoreAllMocks()
    delete (window as any).prettier
    delete (window as any).prettierPlugins
  })

  it('fetches both Prettier bundles once, then formats with the markdown plugin', async () => {
    const first = Utils.formatMarkdownWithPrettier('# Title')
    const second = Utils.formatMarkdownWithPrettier('Text')

    expect(injected.map((script) => script.getAttribute('src'))).toEqual([
      '/bundles/pushwordadminblockeditor/prettier/standalone.js',
      '/bundles/pushwordadminblockeditor/prettier/markdown.js',
    ])
    await new Promise((resolve) => setTimeout(resolve, 0))
    expect(format).not.toHaveBeenCalled()

    for (const script of injected) script.dispatchEvent(new Event('load'))

    await expect(first).resolves.toBe('# Title')
    await expect(second).resolves.toBe('Text')
    expect(format).toHaveBeenCalledWith(
      '# Title',
      expect.objectContaining({ parser: 'markdown', plugins: [plugin] }),
    )
  })

  it('returns the markdown untouched when a Prettier bundle fails to load', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {})

    const formatted = Utils.formatMarkdownWithPrettier('# Title')
    injected[1]!.dispatchEvent(new Event('error'))

    await expect(formatted).resolves.toBe('# Title')
    expect(format).not.toHaveBeenCalled()
  })
})
