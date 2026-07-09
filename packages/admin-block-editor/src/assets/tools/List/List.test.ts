import { describe, it, expect, vi } from 'vitest'
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
    expect(data.items[0].items.map((i: any) => i.content)).toEqual([
      'Child',
      'Child 2',
    ])
    expect(data.items[1].content).toBe('Sibling')
  })

  it('converts inline markdown inside item content', () => {
    const data = importList('- **bold** and _italic_')

    expect(data.items[0].content).toBe('<b>bold</b> and <i>italic</i>')
  })
})
