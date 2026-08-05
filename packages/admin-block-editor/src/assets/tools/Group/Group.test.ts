import { describe, it, expect, vi, beforeEach } from 'vitest'
import GroupStart, { GroupStartData } from './GroupStart'
import GroupEnd from './GroupEnd'
import { GroupNesting } from './GroupNesting'
import { GroupRegistry } from './GroupRegistry'
import { chunkTool } from '../../EditorJsParseMarkdown'
import { MarkdownUtils } from '../utils/MarkdownUtils'
import CodeBlock from '../CodeBlock/CodeBlock'
import Paragraph from '../Paragraph/Paragraph'
import Raw from '../Raw/Raw'

/**
 * Editor api stub over a mutable block list, recording deletions and
 * insertions — enough for GroupRegistry and the lifecycle hooks.
 */
function apiStub(blocks: { id: string; name: string }[]) {
  const list = [...blocks]
  const deleted: string[] = []
  const inserted: { type: string; index: number }[] = []
  const api = {
    blocks: {
      getBlocksCount: () => list.length,
      getBlockByIndex: (index: number) => list[index],
      getById: (id: string) => list.find((block) => block.id === id) ?? null,
      getBlockIndex: (id: string) => list.findIndex((block) => block.id === id),
      delete: (index: number) => {
        deleted.push(list[index]!.id)
        list.splice(index, 1)
      },
      insert: (type: string, _data: unknown, _config: unknown, index: number) => {
        inserted.push({ type, index })
        list.splice(index, 0, { id: `new-${type}-${inserted.length}`, name: type })
      },
    },
    i18n: { t: (key: string) => key },
  } as any
  return { api, list, deleted, inserted }
}

/** Flush the deferred pairing/cascade work (plain setTimeout, no rAF). */
const tick = () => new Promise((resolve) => setTimeout(resolve, 1))

/** Drive an importFromMarkdown and return what would be inserted. */
function captureInsert(
  importer: (editor: any, markdown: string) => void,
  markdown: string,
): { type: string; data: GroupStartData } {
  let captured: { type: string; data: GroupStartData } | null = null
  const editor = {
    blocks: {
      insert: (type: string, data: GroupStartData) => {
        captured = { type, data }
        return { id: 'block-id' }
      },
    },
  } as any

  importer(editor, markdown)

  if (captured === null) {
    throw new Error('no block was inserted')
  }
  return captured
}

describe('GroupStart.exportToMarkdown', () => {
  it('exports a bare wrapper when no attribute is set', () => {
    expect(GroupStart.exportToMarkdown({ anchor: '', class: '' })).toBe('<div>')
  })

  it('exports id and class when set', () => {
    expect(GroupStart.exportToMarkdown({ anchor: 'faq', class: '' })).toBe('<div id="faq">')
    expect(GroupStart.exportToMarkdown({ anchor: '', class: 'grid gap-4' })).toBe(
      '<div class="grid gap-4">',
    )
    expect(GroupStart.exportToMarkdown({ anchor: 'faq', class: 'grid' })).toBe(
      '<div id="faq" class="grid">',
    )
  })

  it('strips characters that would escape the attribute', () => {
    expect(GroupStart.exportToMarkdown({ anchor: '', class: 'a" onclick="x' })).toBe(
      '<div class="a onclick=x">',
    )
  })
})

describe('GroupStart.isItMarkdownExported', () => {
  it.each(['<div>', '<div id="faq">', '<div class="a b">', '<div id="faq" class="a">'])(
    'claims %s',
    (markdown) => {
      expect(GroupStart.isItMarkdownExported(markdown)).toBe(true)
    },
  )

  it.each([
    '<div style="color:red">', // richer than id/class: stays Raw
    '<div data-x="1">',
    '<div>text</div>', // not a lone wrapper line
    '<div>\n<p>text</p>',
    '</div>',
    '<divx>',
    'text',
  ])('leaves %s to other tools', (markdown) => {
    expect(GroupStart.isItMarkdownExported(markdown)).toBe(false)
  })
})

describe('GroupStart.importFromMarkdown', () => {
  it('parses id and class into anchor and class', () => {
    const { type, data } = captureInsert(
      GroupStart.importFromMarkdown,
      '<div id="faq" class="grid md:grid-cols-2">',
    )

    expect(type).toBe('groupStart')
    expect(data).toEqual({ anchor: 'faq', class: 'grid md:grid-cols-2' })
  })

  it('imports a bare wrapper with both keys present', () => {
    const { data } = captureInsert(GroupStart.importFromMarkdown, '<div>')

    // keys must be present: empty data means "fresh toolbox insertion"
    expect(data).toEqual({ anchor: '', class: '' })
  })

  it('round-trips through export', () => {
    const source = '<div id="pricing" class="grid gap-4">'
    const { data } = captureInsert(GroupStart.importFromMarkdown, source)

    expect(GroupStart.exportToMarkdown(data)).toBe(source)
  })
})

describe('GroupEnd markdown round-trip', () => {
  it('exports and claims the closing line', () => {
    expect(GroupEnd.exportToMarkdown()).toBe('</div>')
    expect(GroupEnd.isItMarkdownExported('</div>')).toBe(true)
    expect(GroupEnd.isItMarkdownExported(' </div> ')).toBe(true)
  })

  it.each(['<div>', '</div> text', '</section>'])('does not claim %s', (markdown) => {
    expect(GroupEnd.isItMarkdownExported(markdown)).toBe(false)
  })
})

describe('markerhood is symmetric', () => {
  /** Real tool classes behind the adapter shape getBlockTools() returns. */
  const tools = [
    { name: GroupRegistry.START, constructable: GroupStart },
    { name: GroupRegistry.END, constructable: GroupEnd },
    { name: 'codeBlock', constructable: CodeBlock },
    { name: 'paragraph', constructable: Paragraph },
    { name: 'raw', constructable: Raw },
  ] as any[]

  /** Classify a whole document the way the importer does: one nesting, in order. */
  const classify = (markdown: string): string[] => {
    const nesting = new GroupNesting()
    return MarkdownUtils.chunkMarkdown(markdown).map(
      (chunk) => chunkTool(tools, chunk.text, nesting)?.name ?? 'none',
    )
  }

  it('imports a group pair as markers', () => {
    expect(classify('<div id="faq">\n\ntext\n\n</div>')).toEqual([
      GroupRegistry.START,
      'paragraph',
      GroupRegistry.END,
    ])
  })

  it('leaves the closer of a hand-written div Raw, like its opener', () => {
    expect(classify('<div style="color:red">\n\ntext\n\n</div>')).toEqual([
      'raw',
      'paragraph',
      'raw',
    ])
  })

  it('a hand-written div inside a group keeps both its tags Raw', () => {
    expect(
      classify('<div id="faq">\n\n<div style="color:red">\n\ntext\n\n</div>\n\n</div>'),
    ).toEqual([GroupRegistry.START, 'raw', 'paragraph', 'raw', GroupRegistry.END])
  })

  it('a group inside a hand-written div keeps its own markers', () => {
    expect(classify('<div style="color:red">\n\n<div>\n\ntext\n\n</div>\n\n</div>')).toEqual([
      'raw',
      GroupRegistry.START,
      'paragraph',
      GroupRegistry.END,
      'raw',
    ])
  })

  it('counts the divs a multi-line Raw chunk leaves open', () => {
    expect(classify('<div class="a">\n\n<div style="x">\n<p>text</p>\n\n</div>\n\n</div>')).toEqual([
      GroupRegistry.START,
      'raw',
      'raw',
      GroupRegistry.END,
    ])
  })

  it('leaves a closer with nothing open Raw', () => {
    expect(classify('</div>\n\ntext')).toEqual(['raw', 'paragraph'])
  })

  it('ignores a balanced div inside a Raw chunk', () => {
    expect(classify('<div>\n\n<figure><div>a</div></figure>\n\n</div>')).toEqual([
      GroupRegistry.START,
      'raw',
      GroupRegistry.END,
    ])
  })

  it('ignores the divs a fenced code block only talks about', () => {
    expect(
      classify('<div id="faq">\n\n```html\n<div style="x">\n```\n\ntext\n\n</div>'),
    ).toEqual([GroupRegistry.START, 'codeBlock', 'paragraph', GroupRegistry.END])
  })

  it('a Raw chunk closing more divs than it opens does not eat an outer group', () => {
    expect(classify('<div>\n\n</div></div>\n\ntext\n\n</div>')).toEqual([
      GroupRegistry.START,
      'raw',
      'paragraph',
      'raw',
    ])
  })
})

describe('GroupRegistry.computePairs', () => {
  const start = (id: string) => ({ id, name: 'groupStart' })
  const end = (id: string) => ({ id, name: 'groupEnd' })

  it('pairs a start with its end', () => {
    const pairs = GroupRegistry.computePairs([start('s1'), end('e1')])

    expect(pairs.get('s1')).toBe('e1')
    expect(pairs.get('e1')).toBe('s1')
  })

  it('pairs nested groups innermost-first', () => {
    const pairs = GroupRegistry.computePairs([start('s1'), start('s2'), end('e1'), end('e2')])

    expect(pairs.get('s2')).toBe('e1')
    expect(pairs.get('s1')).toBe('e2')
  })

  it('pairs sequential groups independently', () => {
    const pairs = GroupRegistry.computePairs([start('s1'), end('e1'), start('s2'), end('e2')])

    expect(pairs.get('s1')).toBe('e1')
    expect(pairs.get('s2')).toBe('e2')
  })

  it('leaves an orphan end unpaired — e.g. a </div> closing a Raw block', () => {
    const pairs = GroupRegistry.computePairs([end('e0'), start('s1'), end('e1')])

    expect(pairs.has('e0')).toBe(false)
    expect(pairs.get('s1')).toBe('e1')
  })

  it('leaves an unclosed start unpaired', () => {
    const pairs = GroupRegistry.computePairs([start('s1'), start('s2'), end('e1')])

    expect(pairs.get('s2')).toBe('e1')
    expect(pairs.has('s1')).toBe(false)
  })
})

describe('GroupRegistry.removePartnerOf', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  const paragraph = (id: string) => ({ id, name: 'paragraph' })
  const start = (id: string) => ({ id, name: 'groupStart' })
  const end = (id: string) => ({ id, name: 'groupEnd' })

  /** Pair from the full document, then simulate the editor removing one marker. */
  async function removedMarker(
    blocks: { id: string; name: string }[],
    removedId: string,
  ) {
    const stub = apiStub(blocks)
    GroupRegistry.schedule(stub.api)
    await tick()
    stub.api.blocks.delete(stub.api.blocks.getBlockIndex(removedId))
    stub.deleted.length = 0 // keep only what the cascade deletes
    GroupRegistry.removePartnerOf(stub.api, removedId)
    await tick()
    return stub
  }

  it('deleting a start deletes its end, the wrapped blocks stay', async () => {
    const { deleted, list } = await removedMarker(
      [paragraph('p1'), start('s1'), paragraph('p2'), end('e1'), paragraph('p3')],
      's1',
    )

    expect(deleted).toEqual(['e1'])
    expect(list.map((block) => block.id)).toEqual(['p1', 'p2', 'p3'])
  })

  it('deleting an end deletes its start', async () => {
    const { deleted } = await removedMarker([start('s1'), paragraph('p1'), end('e1')], 'e1')

    expect(deleted).toEqual(['s1'])
  })

  it('deleting an outer start deletes the outer end, the nested group survives', async () => {
    const { deleted, list } = await removedMarker(
      [start('s1'), start('s2'), end('e1'), end('e2')],
      's1',
    )

    expect(deleted).toEqual(['e2'])
    expect(list.map((block) => block.id)).toEqual(['s2', 'e1'])
  })

  it('does nothing when the partner went away in the same operation', async () => {
    const stub = apiStub([start('s1'), end('e1')])
    GroupRegistry.schedule(stub.api)
    await tick()
    stub.api.blocks.delete(0)
    stub.api.blocks.delete(0)
    stub.deleted.length = 0
    GroupRegistry.removePartnerOf(stub.api, 's1')
    GroupRegistry.removePartnerOf(stub.api, 'e1')
    await tick()

    expect(stub.deleted).toEqual([])
  })

  it('does nothing for an unpaired marker — e.g. a </div> closing a Raw block', async () => {
    const { deleted, list } = await removedMarker(
      [end('e0'), start('s1'), end('e1')],
      'e0',
    )

    expect(deleted).toEqual([])
    expect(list.map((block) => block.id)).toEqual(['s1', 'e1'])
  })
})

describe('GroupStart lifecycle', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  function renderedStart(data: GroupStartData | Record<string, never>) {
    const stub = apiStub([{ id: 'g1', name: 'groupStart' }])
    const tool = new GroupStart({
      data,
      api: stub.api,
      block: { id: 'g1', dispatchChange: vi.fn() },
      readOnly: false,
    } as any)
    tool.rendered()
    return stub
  }

  it('a toolbox insertion (empty data) closes itself: paragraph + end marker', async () => {
    const { inserted, list } = renderedStart({})
    await tick()

    expect(inserted.map((insertion) => insertion.type)).toEqual(['groupEnd', 'paragraph'])
    expect(list.map((block) => block.name)).toEqual(['groupStart', 'paragraph', 'groupEnd'])
  })

  it('a re-render with saved data (import, undo, JSON load) inserts nothing', async () => {
    const { inserted } = renderedStart({ anchor: '', class: '' })
    await tick()

    expect(inserted).toEqual([])
  })
})

describe('GroupStart inputs', () => {
  function renderedTool(data: GroupStartData) {
    const { api } = apiStub([])
    const tool = new GroupStart({
      data,
      api,
      block: { id: 'g1', dispatchChange: vi.fn() },
      readOnly: false,
    } as any)
    return { tool, element: tool.render() }
  }

  function type(element: HTMLElement, selector: string, value: string): void {
    const input = element.querySelector(selector) as HTMLInputElement
    input.value = value
    input.dispatchEvent(new Event('input'))
  }

  it('sanitizes the anchor and keeps both keys in save()', () => {
    const { tool, element } = renderedTool({ anchor: 'faq', class: 'grid' })

    type(element, '.pw-group-anchor', 'My Anchor!')

    expect(tool.save()).toEqual({ anchor: 'MyAnchor', class: 'grid' })
  })

  it('strips characters that would escape the class attribute', () => {
    const { tool, element } = renderedTool({ anchor: '', class: '' })

    type(element, '.pw-group-class', 'a" onclick="x')

    expect(tool.save()).toEqual({ anchor: '', class: 'a onclick=x' })
  })
})

describe('GroupRegistry decoration', () => {
  const ceBlock = (marker: '' | 'start' | 'end') =>
    `<div class="ce-block">${marker === '' ? '' : `<div class="pw-group-${marker}"></div>`}</div>`

  async function decorated(markers: ('' | 'start' | 'end')[]): Promise<boolean[]> {
    document.body.innerHTML = `<div class="codex-editor__redactor">${markers
      .map(ceBlock)
      .join('')}</div>`
    GroupRegistry.schedule(apiStub([]).api)
    await tick()

    return Array.from(document.querySelectorAll('.ce-block')).map((block) =>
      block.hasAttribute('data-pw-in-group'),
    )
  }

  it('tags the blocks between a start and its end, not the markers or outside', async () => {
    expect(await decorated(['', 'start', '', '', 'end', ''])).toEqual([
      false,
      false,
      true,
      true,
      false,
      false,
    ])
  })

  it('an orphan end never untags what follows', async () => {
    expect(await decorated(['end', '', 'start', '', 'end'])).toEqual([
      false,
      false,
      false,
      true,
      false,
    ])
  })
})
