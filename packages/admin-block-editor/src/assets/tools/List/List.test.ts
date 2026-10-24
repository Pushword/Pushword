import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import List from './List'
import { MarkdownUtils } from '../utils/MarkdownUtils'

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
  beforeEach(() => {
    vi.spyOn(MarkdownUtils, 'formatMarkdownWithPrettier').mockImplementation(
      async (markdown) => markdown,
    )
  })

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

/**
 * A rendered list in the document, and its saved items. Mounted as
 * blocks.update() does it: render() only, rendered() never called.
 */
async function mountList(style: 'ordered' | 'unordered' | 'checklist', items: any[]) {
  const holder = document.createElement('div')
  document.body.replaceChildren(holder)
  const list = new List({ data: { style, meta: {}, items }, config: {}, api: {} as any, readOnly: false, block: { holder, name: 'list' } as any })
  holder.append(list.render())
  await Promise.resolve()
  const field = (text: string) =>
    Array.from(holder.querySelectorAll<HTMLElement>('.cdx-list__item-content')).find((element) => element.textContent === text)!
  const outline = (entries: any[] = list.save().items, depth = 0): string[] =>
    entries.flatMap((item) => [`${'  '.repeat(depth)}${item.content}`, ...outline(item.items, depth + 1)])

  return { holder, list, field, outline }
}

function caret(node: Node, offset: number): void {
  const range = document.createRange()
  range.setStart(node, offset)
  window.getSelection()!.removeAllRanges()
  window.getSelection()!.addRange(range)
}

const item = (content: string, items: any[] = []) => ({ content, meta: {}, items })

describe('List.pasteItems', () => {
  it('replaces an empty item with the pasted ones, levels kept, the caret after the last', async () => {
    const { list, holder, outline } = await mountList('unordered', [item('Extras', [item('Price')]), item('Luggage'), item('')])
    caret(holder.querySelectorAll('.cdx-list__item-content')[3]!, 0)

    list.pasteItems({ items: [{ content: 'Option A', depth: 0 }, { content: 'Detail', depth: 1 }, { content: 'Option B', depth: 0 }] })

    expect(outline()).toEqual(['Extras', '  Price', 'Luggage', 'Option A', '  Detail', 'Option B'])
    const selection = window.getSelection()!
    expect(selection.anchorNode?.textContent).toBe('Option B')
    expect(selection.anchorOffset).toBe('Option B'.length)
  })

  it('splits the item at the caret like pasted paragraphs, the caret before the moved text', async () => {
    const { list, field, outline } = await mountList('unordered', [item('Hello<b>world</b>')])
    caret(field('Helloworld').firstChild!, 5)

    list.pasteItems({ items: [{ content: ' one', depth: 0 }, { content: 'two ', depth: 0 }] })

    expect(outline()).toEqual(['Hello one', 'two <b>world</b>'])
    expect(window.getSelection()!.anchorNode?.textContent).toBe('two ')
    expect(window.getSelection()!.anchorOffset).toBe(4)
  })

  it('keeps the children of the caret item after the pasted text', async () => {
    const { list, field, outline } = await mountList('unordered', [item('Parent', [item('Child')])])
    caret(field('Parent').firstChild!, 6)

    list.pasteItems({ items: [{ content: ' end', depth: 0 }, { content: 'Next', depth: 0 }] })

    expect(outline()).toEqual(['Parent end', 'Next', '  Child'])
  })

  it('reads depths relative to the first item, one level deeper at most, under the caret item', async () => {
    const { list, field, outline } = await mountList('unordered', [item('Root', [item('')])])
    caret(field(''), 0)

    list.pasteItems({ items: [2, 4, 1, 2].map((depth, index) => ({ content: `p${index}`, depth })) })

    expect(outline()).toEqual(['Root', '  p0', '    p1', '  p2', '    p3'])
  })

  it.each([
    ['a level skipped', [1, 3, 2], ['p0', '  p1', '  p2']],
    ['a first item deeper than the next ones', [3, 1, 2], ['p0', 'p1', '  p2']],
    ['a first item deeper than every other', [2, 0, 0], ['p0', 'p1', 'p2']],
  ])('flattens %s into levels one deeper than their parent at most', async (_label, depths, expected) => {
    const { list, field, outline } = await mountList('unordered', [item('')])
    caret(field(''), 0)

    list.pasteItems({ items: depths.map((depth, index) => ({ content: `p${index}`, depth })) })

    expect(outline()).toEqual(expected)
  })

  it('pastes unchecked items into a checklist and keeps the state of the caret item', async () => {
    const { list, field } = await mountList('checklist', [{ content: 'Done', meta: { checked: true }, items: [] }])
    caret(field('Done').firstChild!, 4)

    list.pasteItems({ items: [{ content: '!', depth: 0 }, { content: 'Todo', depth: 0 }] })

    expect(list.save().items.map((entry: any) => [entry.content, entry.meta.checked])).toEqual([['Done!', true], ['Todo', false]])
  })
})

describe('List.pasteItems guards', () => {
  it('does nothing without a caret in the list or without items', async () => {
    const { list, field, outline } = await mountList('unordered', [item('One')])
    window.getSelection()!.removeAllRanges()
    list.pasteItems({ items: [{ content: 'X', depth: 0 }] })
    caret(field('One').firstChild!, 3)
    list.pasteItems({ items: [] })

    expect(outline()).toEqual(['One'])
  })

  it('replaces the selected text of the item', async () => {
    const { list, field, outline } = await mountList('unordered', [item('Hello big world')])
    const text = field('Hello big world').firstChild!
    const range = document.createRange()
    range.setStart(text, 5)
    range.setEnd(text, 9)
    window.getSelection()!.removeAllRanges()
    window.getSelection()!.addRange(range)

    list.pasteItems({ items: [{ content: ' A', depth: 0 }, { content: 'B', depth: 0 }] })

    expect(outline()).toEqual(['Hello A', 'B world'])
  })
})

describe('List Delete at the end of an item', () => {
  function pressDelete(target: Element): KeyboardEvent {
    const event = new KeyboardEvent('keydown', { key: 'Delete', bubbles: true, cancelable: true })
    target.dispatchEvent(event)
    return event
  }

  it('listens to nothing in read-only mode', async () => {
    const holder = document.createElement('div')
    const listen = vi.spyOn(holder, 'addEventListener')
    new List({ data: { style: 'unordered', meta: {}, items: [item('One')] }, config: {}, api: {} as any, readOnly: true, block: { holder } as any }).render()
    await Promise.resolve()

    expect(listen).not.toHaveBeenCalled()
  })

  it('registers its capture keydown and mousedown listeners on the block holder once, after render() returns', async () => {
    const holder = document.createElement('div')
    const listen = vi.spyOn(holder, 'addEventListener')
    const list = new List({ data: { style: 'unordered', meta: {}, items: [item('One')] }, config: {}, api: {} as any, readOnly: false, block: { holder } as any })
    list.render()
    expect(listen).not.toHaveBeenCalled()
    await Promise.resolve()

    expect(listen).toHaveBeenCalledTimes(2)
    expect(listen).toHaveBeenCalledWith('keydown', expect.any(Function), true)
    expect(listen).toHaveBeenCalledWith('mousedown', expect.any(Function), true)
  })

  it('brings the next item up, its children kept at their level', async () => {
    const { field, outline } = await mountList('unordered', [item('Single room'), item('<b>210</b> per night', [item('Child')]), item('Luggage')])
    const target = field('Single room')
    caret(target.firstChild!, 'Single room'.length)

    expect(pressDelete(target).defaultPrevented).toBe(true)
    expect(outline()).toEqual(['Single room<b>210</b> per night', '  Child', 'Luggage'])
    expect(window.getSelection()!.anchorNode?.textContent).toBe('Single room')
  })

  it('still brings the next item up once the list style renders a new list element', async () => {
    const { list, field, outline } = await mountList('unordered', [item('One'), item('Two')])
    ;(list as unknown as { listStyle: string }).listStyle = 'ordered'
    caret(field('One').firstChild!, 3)

    expect(pressDelete(field('One')).defaultPrevented).toBe(true)
    expect(outline()).toEqual(['OneTwo'])
  })

  it('brings a first child up into its parent', async () => {
    const { field, outline } = await mountList('unordered', [item('Parent', [item('Child', [item('Grandchild')])])])
    caret(field('Parent').firstChild!, 6)

    pressDelete(field('Parent'))

    expect(outline()).toEqual(['ParentChild', '  Grandchild'])
  })

  it('leaves Delete inside the text, and at the end of the last item, to the editor', async () => {
    const { field, outline } = await mountList('unordered', [item('One'), item('Two')])
    caret(field('One').firstChild!, 1)
    expect(pressDelete(field('One')).defaultPrevented).toBe(false)
    caret(field('Two').firstChild!, 3)
    expect(pressDelete(field('Two')).defaultPrevented).toBe(false)
    expect(outline()).toEqual(['One', 'Two'])
  })

  it.each([
    ['with Shift', { shiftKey: true }],
    ['with Ctrl', { ctrlKey: true }],
  ])('leaves Delete %s to the editor', async (_name, init) => {
    const { field, outline } = await mountList('unordered', [item('One'), item('Two')])
    caret(field('One').firstChild!, 3)
    const event = new KeyboardEvent('keydown', { key: 'Delete', bubbles: true, cancelable: true, ...init })
    field('One').dispatchEvent(event)

    expect(outline()).toEqual(['One', 'Two'])
  })

  it('leaves a Delete already handled by another listener alone', async () => {
    const { field, outline } = await mountList('unordered', [item('One'), item('Two')])
    caret(field('One').firstChild!, 3)
    const earlier = (e: Event) => e.preventDefault()
    document.addEventListener('keydown', earlier, true)
    field('One').dispatchEvent(new KeyboardEvent('keydown', { key: 'Delete', bubbles: true, cancelable: true }))
    document.removeEventListener('keydown', earlier, true)

    expect(outline()).toEqual(['One', 'Two'])
  })

  it('leaves other keys alone', async () => {
    const { field, outline } = await mountList('unordered', [item('One'), item('Two')])
    caret(field('One').firstChild!, 3)
    const event = new KeyboardEvent('keydown', { key: 'Backspace', bubbles: true, cancelable: true })
    field('One').dispatchEvent(event)

    expect(event.defaultPrevented).toBe(false)
    expect(outline()).toEqual(['One', 'Two'])
  })
})

describe('List item selection', () => {
  let highlights: Map<string, Set<Range>>

  beforeEach(() => {
    highlights = new Map()
    vi.stubGlobal('Highlight', class extends Set<Range> {
      constructor(...ranges: Range[]) {
        super(ranges)
      }
    })
    vi.stubGlobal('CSS', { highlights })
  })

  afterEach(() => {
    // A selection left by a test listens on the window until a click ends it.
    document.body.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }))
    vi.unstubAllGlobals()
  })

  function mouse(type: string, target: Element, init: MouseEventInit = {}): MouseEvent {
    const event = new MouseEvent(type, { bubbles: true, cancelable: true, button: 0, ...init })
    target.dispatchEvent(event)
    return event
  }

  function press(init: KeyboardEventInit): KeyboardEvent {
    const event = new KeyboardEvent('keydown', { bubbles: true, cancelable: true, ...init })
    document.body.dispatchEvent(event)
    return event
  }

  function clipboard(type: 'copy' | 'cut' | 'paste'): { event: Event; data: Record<string, string> } {
    const data: Record<string, string> = {}
    const event = new Event(type, { bubbles: true, cancelable: true })
    Object.defineProperty(event, 'clipboardData', { value: { setData: (format: string, value: string) => { data[format] = value } } })
    document.body.dispatchEvent(event)
    return { event, data }
  }

  function drag(from: Element, to: Element): void {
    mouse('mousedown', from)
    mouse('mouseover', to)
    mouse('mouseup', to)
  }

  const highlighted = () => Array.from(highlights.get('pw-list-items') ?? [], (range) => range.toString())
  const caretAt = () => [window.getSelection()!.anchorNode?.textContent, window.getSelection()!.anchorOffset]
  const listOf = () => [item('A'), item('B', [item('B1')]), item('C'), item('D')]

  it('clears the selection and global key handlers when history destroys the old list', async () => {
    const { list, field, outline } = await mountList('unordered', listOf())
    drag(field('A'), field('C'))
    expect(highlighted()).toEqual(['A', 'B', 'B1', 'C'])
    list.destroy()
    expect(highlighted()).toEqual([])
    expect(press({ key: 'x' }).defaultPrevented).toBe(false)
    expect(outline()).toEqual(['A', 'B', '  B1', 'C', 'D'])
  })

  it('ends an in-progress drag when the list is destroyed', async () => {
    const { list, field } = await mountList('unordered', listOf())
    mouse('mousedown', field('A'))
    list.destroy()
    mouse('mouseover', field('C'))
    expect(highlighted()).toEqual([])
  })

  it('selects whole items from the one a drag started in to the one it reached, and drops the text selection', async () => {
    const { field } = await mountList('unordered', listOf())
    caret(field('A').firstChild!, 0)
    drag(field('A'), field('C'))

    expect(highlighted()).toEqual(['A', 'B', 'B1', 'C'])
    expect(window.getSelection()!.rangeCount).toBe(0)
  })

  it('selects no item while a drag stays in its own, and leaves blocks to Editor.js', async () => {
    const { field } = await mountList('unordered', listOf())
    const outside = document.body.appendChild(document.createElement('p'))
    mouse('mousedown', field('A'))
    mouse('mouseover', field('A'))
    expect(highlighted()).toEqual([])

    mouse('mouseover', field('C'))
    mouse('mouseover', field('A'))
    expect(highlighted()).toEqual(['A'])

    mouse('mouseover', outside)
    expect(highlights.has('pw-list-items')).toBe(false)
    mouse('mouseup', outside)
    mouse('mouseover', field('C'))
    expect(highlighted()).toEqual([])
  })

  it('selects from the caret\'s item with Shift+click, and extends from the same item', async () => {
    const { field } = await mountList('unordered', listOf())
    caret(field('B').firstChild!, 1)
    expect(mouse('mousedown', field('B'), { shiftKey: true }).defaultPrevented).toBe(false)
    expect(highlighted()).toEqual([])

    expect(mouse('mousedown', field('D'), { shiftKey: true }).defaultPrevented).toBe(true)
    expect(highlighted()).toEqual(['B', 'B1', 'C', 'D'])

    mouse('mousedown', field('A'), { shiftKey: true })
    expect(highlighted()).toEqual(['A', 'B'])
  })

  it('removes the selected items on Delete, children of a removed parent kept with their siblings', async () => {
    const { field, outline } = await mountList('unordered', [item('Intro'), item('Parent', [item('Child 1'), item('Child 2')]), item('Last')])
    drag(field('Intro'), field('Parent'))

    expect(press({ key: 'Delete' }).defaultPrevented).toBe(true)
    expect(outline()).toEqual(['Child 1', 'Child 2', 'Last'])
    expect(caretAt()).toEqual(['Child 1', 0])
    expect(highlights.has('pw-list-items')).toBe(false)
  })

  it('puts the caret at the end of the item above once Backspace removed the last ones', async () => {
    const { field, outline } = await mountList('unordered', listOf())
    drag(field('D'), field('C'))
    press({ key: 'Backspace' })

    expect(outline()).toEqual(['A', 'B', '  B1'])
    expect(caretAt()).toEqual(['B1', 2])
  })

  it('leaves one empty item, unchecked, when every item is removed', async () => {
    const { list, field, outline } = await mountList('checklist', [{ content: 'Done', meta: { checked: true }, items: [] }, item('Todo')])
    drag(field('Done'), field('Todo'))
    press({ key: 'Delete' })

    expect(outline()).toEqual([''])
    expect(list.save().items[0]?.meta).toEqual({ checked: false })
  })

  it('replaces the selected items with one holding a typed character, as text', async () => {
    const { field, outline } = await mountList('unordered', listOf())
    drag(field('B'), field('C'))

    expect(press({ key: '<' }).defaultPrevented).toBe(true)
    expect(outline()).toEqual(['A', '&lt;', 'D'])
    expect(caretAt()).toEqual(['<', 1])
  })

  it('copies the items as lines, a list and Editor.js data, levels relative to the first, and keeps them selected', async () => {
    const { field, outline } = await mountList('unordered', [item('A'), item('B', [item('<b>B1</b>', [item('B2')])]), item('C')])
    drag(field('B1'), field('C'))
    const { event, data } = clipboard('copy')

    expect(event.defaultPrevented).toBe(true)
    expect(data['text/plain']).toBe('B1\nB2\nC')
    expect(data['text/html']).toBe('<ul><li><b>B1</b><ul><li>B2</li></ul></li><li>C</li></ul>')
    expect(JSON.parse(data['application/x-editor-js']!)).toEqual([{
      tool: 'list',
      data: { style: 'unordered', meta: {}, items: [item('<b>B1</b>', [item('B2')]), item('C')] },
    }])
    expect(outline()).toEqual(['A', 'B', '  <b>B1</b>', '    B2', 'C'])
    expect(highlighted()).toEqual(['B1', 'B2', 'C'])
  })

  it('copies a line break as a line of its own, a soft one as a space, and an ordered list as one', async () => {
    const { field } = await mountList('ordered', [item('One<br>two'), item('Three\nfour')])
    drag(field('Onetwo'), field('Three\nfour'))
    const { data } = clipboard('copy')

    expect(data['text/plain']).toBe('One\ntwo\nThree four')
    expect(data['text/html']).toBe('<ol><li>One<br>two</li><li>Three\nfour</li></ol>')
  })

  it('cuts the items: copied, then removed', async () => {
    const { field, outline } = await mountList('unordered', listOf())
    drag(field('C'), field('D'))
    const { event, data } = clipboard('cut')

    expect(event.defaultPrevented).toBe(true)
    expect(data['text/plain']).toBe('C\nD')
    expect(outline()).toEqual(['A', 'B', '  B1'])
  })

  it('leaves an empty item holding the caret in place of the items for a paste to land in', async () => {
    const { field, outline } = await mountList('unordered', listOf())
    drag(field('B'), field('C'))
    const { event } = clipboard('paste')

    expect(event.defaultPrevented).toBe(false)
    expect(outline()).toEqual(['A', '', 'D'])
    expect(window.getSelection()!.anchorNode).toBe(field(''))
  })

  it('keeps the selection for the clipboard shortcuts, and puts a caret at the end of the item reached for other keys', async () => {
    const { field, outline } = await mountList('unordered', listOf())
    drag(field('A'), field('C'))
    press({ key: 'Control', ctrlKey: true })
    expect(press({ key: 'c', ctrlKey: true }).defaultPrevented).toBe(false)
    expect(highlighted()).toEqual(['A', 'B', 'B1', 'C'])

    expect(press({ key: 'ArrowDown' }).defaultPrevented).toBe(false)
    expect(highlights.has('pw-list-items')).toBe(false)
    expect(caretAt()).toEqual(['C', 1])
    expect(press({ key: 'x' }).defaultPrevented).toBe(false)
    expect(outline()).toEqual(['A', 'B', '  B1', 'C', 'D'])
  })

  it('ends the selection on a click elsewhere, the keys then left to the field', async () => {
    const { field } = await mountList('unordered', listOf())
    drag(field('A'), field('C'))
    expect(highlighted()).toEqual(['A', 'B', 'B1', 'C'])
    mouse('mousedown', document.body)

    expect(highlights.has('pw-list-items')).toBe(false)
    expect(press({ key: 'Delete' }).defaultPrevented).toBe(false)
  })

  it('selects nothing without the CSS Highlight API, leaving the browser\'s selection', async () => {
    vi.stubGlobal('CSS', {})
    const { field } = await mountList('unordered', listOf())
    caret(field('A').firstChild!, 0)
    drag(field('A'), field('C'))

    expect(window.getSelection()!.rangeCount).toBe(1)
    expect(press({ key: 'Delete' }).defaultPrevented).toBe(false)
  })
})
