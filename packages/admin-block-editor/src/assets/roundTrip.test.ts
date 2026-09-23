import { describe, it, expect, vi, beforeAll } from 'vitest'
import { createRequire } from 'module'
import { API } from '@editorjs/editorjs'
import { MarkdownUtils } from './tools/utils/MarkdownUtils'
import EditorJsParseMarkdown from './EditorJsParseMarkdown'
import EditorJsExportMarkdown from './EditorJsExportMarkdown'
import List from './tools/List/List'
import Paragraph from './tools/Paragraph/Paragraph'
import Image from './tools/Image/Image'
import Raw from './tools/Raw/Raw'
import Quiz from './tools/Quiz/Quiz'

/**
 * Markdown the editor opens must save back unchanged: every loss below went
 * unnoticed because import and export were only ever tested one side at a
 * time. Prettier is the one the browser loads, so what is asserted is what
 * the admin writes.
 */

beforeAll(() => {
  const require = createRequire(import.meta.url)
  const prettier = require('../Resources/public/prettier/standalone.js')
  const plugin = require('../Resources/public/prettier/markdown.js')
  vi.spyOn(MarkdownUtils as any, 'loadPrettier').mockResolvedValue({ prettier, plugin })
  window.pageHost = ''
})

interface FakeBlock {
  id: string
  type: string
  data: any
  tunes: any
}

/** What a contenteditable hands back: the browser re-parses the HTML and drops a stray tag. */
function reserialize(value: unknown): unknown {
  if (typeof value === 'string') {
    const element = document.createElement('div')
    element.innerHTML = value
    return element.innerHTML
  }
  if (Array.isArray(value)) return value.map(reserialize)
  if (value !== null && typeof value === 'object') {
    return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, reserialize(item)]))
  }
  return value
}

/** Just enough of Editor.js for the parser: blocks append, and the new one is current. */
function fakeEditor(): { editor: API; blocks: FakeBlock[] } {
  const blocks: FakeBlock[] = []
  let current = -1
  const tools = { list: List, paragraph: Paragraph, image: Image, quiz: Quiz, raw: Raw }
  const editor = {
    tools: {
      getBlockTools: () =>
        Object.entries(tools).map(([name, constructable]) => ({ name, constructable })),
    },
    blocks: {
      clear: () => {
        blocks.length = 0
        current = -1
      },
      insert: (type: string) => {
        blocks.push({ id: `b${blocks.length}`, type, data: {}, tunes: {} })
        current = blocks.length - 1
        return { id: blocks[current]!.id }
      },
      update: (id: string, data: any, tunes: any) => {
        const block = blocks.find((candidate) => candidate.id === id)!
        block.data = ['paragraph', 'list'].includes(block.type) ? reserialize(data) : data
        block.tunes = tunes ?? {}
      },
      getBlocksCount: () => blocks.length,
      getCurrentBlockIndex: () => current,
      getBlockByIndex: (index: number) => blocks[index],
    },
  } as unknown as API

  return { editor, blocks }
}

function parse(markdown: string): { editor: API; blocks: FakeBlock[] } {
  const fake = fakeEditor()
  new EditorJsParseMarkdown(fake.editor, markdown).parseMarkdown()

  return fake
}

function save(editor: API, blocks: FakeBlock[]): Promise<string> {
  return new EditorJsExportMarkdown(editor, { blocks }).exportToMarkdown()
}

/** Import then export through the tools alone: another editor has no source to fall back on. */
async function roundTrip(markdown: string): Promise<string> {
  return save(fakeEditor().editor, parse(markdown).blocks)
}

describe('import → export', () => {
  it.each([
    ['a soft line break, which the site renders as a space', 'un\ndeux'],
    ['a hard line break', 'un  \ndeux'],
    ['a soft line break inside bold', '**un\ndeux**'],
    ['a soft line break inside italic', '_un\ndeux_'],
    ['a soft line break inside a link', '[un\ndeux](https://x.fr)'],
    ['an obfuscated link', 'Voir #[le site](https://x.fr) ici'],
    ['a link title', 'Voir [le site](https://x.fr "Le titre") ici'],
    ['a link title next to a target', 'Voir [le site](https://x.fr "Le titre"){target="_blank"} ici'],
    ['an italic link text', 'Chez [_Alpinstore_](https://x.fr)'],
    [
      'an italic link text after an underscore in a word and in a URL',
      'le mot_clé, [a](https://x.fr/a_b) puis [_Alpinstore_](https://x.fr/c_d)',
    ],
    ['underscores inside a code span', 'la clé `snake_case_name` et _ici_'],
    ['an image followed by text', '![logo](logo.png) du texte après'],
    ['an image alone', '![alt](photo.jpg)'],
    ['a linked image', '[![alt](photo.jpg)](/page)'],
    ['an obfuscated linked image opening in a new tab', '#[![alt](photo.jpg)](/page){target="_blank"}'],
    ['a linked image inside text', 'Voir [![a_b](x_y.png)](/page) ici'],
    ['nested list items', '- Parent\n  - Child\n    - Grandchild\n- Sibling'],
    ['a list item wrapped on a soft line break', '- Déjeuner :\n  80 €\n- Suite'],
    ['a list item with a hard line break', '- Déjeuner :  \n  80 €\n- Suite'],
    ['an ordered list item with a hard line break', '1. Déjeuner :  \n   80 €\n2. Suite'],
    ['a nested list item with a hard line break', '- Parent\n  - Déjeuner :  \n    80 €'],
  ])('keeps %s', async (_case, markdown) => {
    expect(await roundTrip(markdown)).toBe(markdown)
  })

  it('writes a Shift+Enter in a list item as a hard break under the item', async () => {
    const markdown = await List.exportToMarkdown({
      style: 'unordered',
      meta: {},
      items: [{ content: 'Déjeuner :<br>80 €', meta: {}, items: [] }],
    })

    expect(markdown).toBe('- Déjeuner :  \n  80 €')
  })
})

describe('untouched blocks', () => {
  it('are written back as they were parsed, not as the editor would write them', async () => {
    const markdown = '* one\n* two\n\n![alt](/media/default/photo.jpg)'
    const { editor, blocks } = parse(markdown)

    expect(await save(editor, blocks)).toBe(markdown)
  })

  it('keep their own source when the next chunk yields no block', async () => {
    // An unreadable quiz inserts nothing, so the list stays the current block:
    // recording the chunk against it would save the list as the quiz call.
    const { editor, blocks } = parse("* one\n* two\n\n{{ quiz('not json') }}")

    expect(blocks).toHaveLength(1)
    expect(await save(editor, blocks)).toBe('* one\n* two')
  })

  it('leave an edited block to its export', async () => {
    const { editor, blocks } = parse('* one\n* two\n\nSome text')
    await save(editor, blocks) // the save the parse triggers sets the baseline

    blocks[1]!.data = { text: 'Other text' }

    expect(await save(editor, blocks)).toBe('* one\n* two\n\nOther text')
  })

  it('get their source back once the edit is undone', async () => {
    const { editor, blocks } = parse('* one\n* two')
    await save(editor, blocks)

    const parsed = blocks[0]!.data
    blocks[0]!.data = { ...parsed, items: [] }
    expect(await save(editor, blocks)).toBe('')

    blocks[0]!.data = parsed
    expect(await save(editor, blocks)).toBe('* one\n* two')
  })
})
