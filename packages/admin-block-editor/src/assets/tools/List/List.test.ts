import { describe, it, expect } from 'vitest'
import List from './List'

type CapturedList = { style: string; items: any[] }

/**
 * Drive List.importFromMarkdown with a stub editor and return the data that
 * would be written into the inserted list block.
 */
function importList(markdown: string): CapturedList {
  let captured: CapturedList | null = null
  const editor = {
    blocks: {
      insert: () => ({ id: 'block-id' }),
      update: (_id: string, data: CapturedList) => {
        captured = data
      },
    },
  } as any

  List.importFromMarkdown(editor, markdown)

  if (captured === null) {
    throw new Error('list block was never updated')
  }
  return captured
}

describe('List.importFromMarkdown', () => {
  it('imports a "tight" unordered list (no blank lines) as one item per line', () => {
    const markdown = ['- [A](#a)', '- [B](#b)', '- [C](#c)'].join('\n')

    const data = importList(markdown)

    expect(data.style).toBe('unordered')
    expect(data.items).toHaveLength(3)
    expect(data.items.map((i) => i.content)).toEqual([
      '<a href="#a">A</a>',
      '<a href="#b">B</a>',
      '<a href="#c">C</a>',
    ])
    // items 2..N must not be folded into the first item's content
    expect(data.items[0].content).not.toContain('<br>')
  })

  it('imports a tight ordered list as one item per line', () => {
    const markdown = ['1. First', '2. Second', '3. Third'].join('\n')

    const data = importList(markdown)

    expect(data.style).toBe('ordered')
    expect(data.items.map((i) => i.content)).toEqual(['First', 'Second', 'Third'])
  })

  it('nests items by their indentation', () => {
    const markdown = ['- Parent', '  - Child', '  - Child 2', '- Sibling'].join('\n')

    const data = importList(markdown)

    expect(data.items).toHaveLength(2)
    expect(data.items[0].content).toBe('Parent')
    expect(data.items[0].items.map((i: any) => i.content)).toEqual(['Child', 'Child 2'])
    expect(data.items[1].content).toBe('Sibling')
  })

  it('converts inline markdown inside item content', () => {
    const data = importList('- **bold** and _italic_')

    expect(data.items[0].content).toBe('<b>bold</b> and <i>italic</i>')
  })

  it('imports task list markers as a checklist', () => {
    const data = importList(['- [ ] todo', '- [x] done'].join('\n'))

    expect(data.style).toBe('checklist')
    expect(data.items.map((i) => i.content)).toEqual(['todo', 'done'])
    expect(data.items.map((i) => i.meta.checked)).toEqual([false, true])
  })

  it('joins a continuation line as a newline after a soft line break', () => {
    const data = importList('- Déjeuner :\n  80 €')

    expect(data.items[0].content).toBe('Déjeuner :\n80 €')
  })

  it('joins a continuation line with a <br> after a trailing backslash, dropping it', () => {
    const data = importList('- Déjeuner :\\\n  80 €')

    expect(data.items[0].content).toBe('Déjeuner :<br>80 €')
  })

  it('reads blank lines between items as a loose list, not as line breaks', () => {
    const data = importList('* Vélos\n\n    - VTC\n\n    - VAE')

    expect(data.items[0].content).toBe('Vélos')
    expect(data.items[0].items.map((i: any) => i.content)).toEqual(['VTC', 'VAE'])
  })

  it('opens a paragraph in the item for a continuation after a blank line', () => {
    expect(importList('- one\n\n  more').items[0].content).toBe('one<br>\nmore')
  })

  it('opens that paragraph in the sub-item it follows', () => {
    const data = importList('- a\n  - b\n\n    more')

    expect(data.items[0].content).toBe('a')
    expect(data.items[0].items[0].content).toBe('b<br>\nmore')
  })

  it('declares <br> to the sanitizer, which would strip a hard break otherwise', () => {
    expect(importList('- Déjeuner :  \n  80 €').items[0].content).toContain('<br>')
    expect(List.sanitize.items).toHaveProperty('br', true)
  })

  it('leaves a link at the start of an item alone', () => {
    const data = importList('- [x](#anchor) and more')

    expect(data.style).toBe('unordered')
    expect(data.items[0].content).toBe('<a href="#anchor">x</a> and more')
  })
})

describe('List.exportToMarkdown', () => {
  it('writes a checklist back as task list markers', async () => {
    const markdown = await List.exportToMarkdown({
      style: 'checklist',
      meta: {},
      items: [
        { content: 'todo', meta: { checked: false }, items: [] },
        { content: 'done', meta: { checked: true }, items: [] },
      ],
    })

    expect(markdown.trim()).toBe(['- [ ] todo', '- [x] done'].join('\n'))
  })

  it('keeps unordered and ordered markers', async () => {
    const unordered = await List.exportToMarkdown({
      style: 'unordered',
      meta: {},
      items: [{ content: 'one', meta: {}, items: [] }],
    })
    const ordered = await List.exportToMarkdown({
      style: 'ordered',
      meta: {},
      items: [{ content: 'one', meta: {}, items: [] }],
    })

    expect(unordered.trim()).toBe('- one')
    expect(ordered.trim()).toBe('1. one')
  })

  it('writes legacy string items', async () => {
    const markdown = await List.exportToMarkdown({ style: 'unordered', meta: {}, items: ['one', 'two'] })

    expect(markdown.trim()).toBe('- one\n- two')
  })

  it('writes an item without content as an empty item', async () => {
    const markdown = await List.exportToMarkdown({
      style: 'unordered',
      meta: {},
      items: [{ meta: {}, items: [] }, { content: 'two', meta: {}, items: [] }],
    })

    expect(markdown.trim().split('\n').map((line) => line.trimEnd())).toEqual(['-', '- two'])
  })

  it('indents a hard break under the text of an ordered item', async () => {
    const markdown = await List.exportToMarkdown({
      style: 'ordered',
      meta: {},
      items: [{ content: 'Déjeuner :<br>80 €', meta: {}, items: [] }],
    })

    expect(markdown.trim()).toBe('1. Déjeuner :  \n   80 €')
  })

  it('keeps nested items indented under their parent', async () => {
    const markdown = await List.exportToMarkdown({
      style: 'unordered',
      meta: {},
      items: [
        {
          content: 'Parent',
          meta: {},
          items: [
            {
              content: 'Child',
              meta: {},
              items: [{ content: 'Grandchild', meta: {}, items: [] }],
            },
          ],
        },
        { content: 'Sibling', meta: {}, items: [] },
      ],
    })

    expect(markdown.trim()).toBe(
      ['- Parent', '  - Child', '    - Grandchild', '- Sibling'].join('\n'),
    )
  })
})
