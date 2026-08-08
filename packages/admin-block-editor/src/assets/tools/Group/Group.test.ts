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
  const updated: { id: string; data: unknown }[] = []
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
      update: (id: string, data: unknown) => {
        updated.push({ id, data })
        return Promise.resolve()
      },
    },
    i18n: { t: (key: string) => key },
  } as any
  return { api, list, deleted, inserted, updated }
}

/** Flush the deferred pairing/cascade work (plain setTimeout, no rAF). */
const tick = () => new Promise((resolve) => setTimeout(resolve, 1))

/** Drive an importFromMarkdown and return what would be inserted. */
function captureInsert<T = GroupStartData>(
  importer: (editor: any, markdown: string) => void,
  markdown: string,
): { type: string; data: T } {
  let captured: { type: string; data: T } | null = null
  const editor = {
    blocks: {
      insert: (type: string, data: T) => {
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
    expect(data).toEqual({ anchor: 'faq', class: 'grid md:grid-cols-2', collapsible: false, legacy: false })
  })

  it('imports a bare wrapper with both keys present', () => {
    const { data } = captureInsert(GroupStart.importFromMarkdown, '<div>')

    // keys must be present: empty data means "fresh toolbox insertion"
    expect(data).toEqual({ anchor: '', class: '', collapsible: false, legacy: false })
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

    expect(tool.save()).toEqual({ anchor: 'MyAnchor', class: 'grid', collapsible: false, legacy: false })
  })

  it('strips characters that would escape the class attribute', () => {
    const { tool, element } = renderedTool({ anchor: '', class: '' })

    type(element, '.pw-group-class', 'a" onclick="x')

    expect(tool.save()).toEqual({ anchor: '', class: 'a onclick=x', collapsible: false, legacy: false })
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

// ─── the collapsible variant (show-more) ──────────────────────────────────────

describe('a collapsible group exports the show-more call', () => {
  const collapsible = (data: Partial<GroupStartData>): string =>
    GroupStart.exportToMarkdown({ anchor: '', class: '', collapsible: true, ...data })

  it('carries the anchor as the block id and the class as the wrapper class', () => {
    expect(collapsible({})).toBe('{{ startShowMore() }}')
    expect(collapsible({ anchor: 'faq' })).toBe("{{ startShowMore('faq') }}")
    expect(collapsible({ anchor: 'faq', class: 'mt-8' })).toBe("{{ startShowMore('faq', 'mt-8') }}")
  })

  /** `''` is a real id to the Twig signature, and every block would share it. */
  it('names the class rather than passing an empty id before it', () => {
    expect(collapsible({ class: 'mt-8' })).toBe("{{ startShowMore(showMoreExtraClass: 'mt-8') }}")
  })

  it('strips what would close the Twig string literal', () => {
    // The quotes are gone, so the class can no longer end the literal it sits in.
    expect(collapsible({ class: "a') }}{{ dump(" })).toBe(
      "{{ startShowMore(showMoreExtraClass: 'a) }}{{ dump(') }}",
    )
  })

  it('closes with endShowMore, keeping arguments the author wrote', () => {
    expect(GroupEnd.exportToMarkdown({ collapsible: true })).toBe('{{ endShowMore() }}')
    expect(GroupEnd.exportToMarkdown({ collapsible: true, args: "'via-gray-100 to-gray-100'" })).toBe(
      "{{ endShowMore('via-gray-100 to-gray-100') }}",
    )
  })

  /** The background is only ever written by hand: losing it on open would be silent. */
  it('round-trips a background set on the closing call', () => {
    const source = "{{ endShowMore('via-gray-100 to-gray-100') }}"
    const { data } = captureInsert(GroupEnd.importFromMarkdown, source)

    expect(GroupEnd.exportToMarkdown(data)).toBe(source)
  })

  it.each([
    '{{ endShowMore() }}',
    "{{ endShowMore('via-white to-white') }}",
    "{{ endShowMore(null, 'an-id') }}",
    '<!--end-show-more-->',
  ])('claims %s as a closer', (markdown) => {
    expect(GroupEnd.isItMarkdownExported(markdown)).toBe(true)
  })

  it.each(['{{ endShowMore(background) }}', '{{ endShowMoreThing() }}', '{# <!--end-show-more--> #}'])(
    'leaves %s to other tools',
    (markdown) => {
      expect(GroupEnd.isItMarkdownExported(markdown)).toBe(false)
    },
  )
})

describe('the legacy comment pair is given back unchanged', () => {
  it('exports back as the comment it was read from', () => {
    expect(
      GroupStart.exportToMarkdown({ anchor: '', class: '', collapsible: true, legacy: true }),
    ).toBe('<!--start-show-more-->')
    expect(GroupEnd.exportToMarkdown({ collapsible: true, legacy: true })).toBe(
      '<!--end-show-more-->',
    )
  })

  /** The comment holds nothing, so anything typed into the fields upgrades the pair. */
  it('upgrades to the Twig call as soon as it carries an anchor or a class', () => {
    expect(
      GroupStart.exportToMarkdown({ anchor: 'faq', class: '', collapsible: true, legacy: true }),
    ).toBe("{{ startShowMore('faq') }}")
  })

  it('round-trips an untouched pair byte for byte', () => {
    const { data } = captureInsert(GroupStart.importFromMarkdown, '<!--start-show-more-->')

    expect(GroupStart.exportToMarkdown(data)).toBe('<!--start-show-more-->')
  })
})

describe('GroupStart claims the show-more spellings', () => {
  it.each([
    '{{ startShowMore() }}',
    "{{ startShowMore('faq') }}",
    "{{ startShowMore('faq', 'mt-8') }}",
    '{{ startShowMore(null, "mt-8") }}',
    "{{ startShowMore(showMoreExtraClass: 'mt-8') }}",
    "{{ startShowMore(id: 'faq') }}",
    '{{startShowMore()}}',
    '<!--start-show-more-->',
  ])('claims %s', (markdown) => {
    expect(GroupStart.isItMarkdownExported(markdown)).toBe(true)
  })

  it.each([
    '{{ startShowMore(page.slug) }}', // a variable: we could not write it back
    "{{ startShowMore('a', 'b', 'c') }}",
    '{{ startShowMoreThing() }}',
    '{# <!--start-show-more--> #}', // an author disabling a block
    '<!--start-show-more--> and more',
  ])('leaves %s to other tools', (markdown) => {
    expect(GroupStart.isItMarkdownExported(markdown)).toBe(false)
  })

  it('parses the call arguments into the anchor and the class', () => {
    const { data } = captureInsert(
      GroupStart.importFromMarkdown,
      "{{ startShowMore('faq', 'mt-8') }}",
    )

    expect(data).toEqual({ anchor: 'faq', class: 'mt-8', collapsible: true, legacy: false })
  })

  it('reads a null id as no anchor', () => {
    const { data } = captureInsert(
      GroupStart.importFromMarkdown,
      "{{ startShowMore(null, 'mt-8') }}",
    )

    expect(data).toEqual({ anchor: '', class: 'mt-8', collapsible: true, legacy: false })
  })

  /** A named argument is the readable way to pass the class alone, so it must read back. */
  it('places a named argument by its name, not by its position', () => {
    const { data } = captureInsert(
      GroupStart.importFromMarkdown,
      "{{ startShowMore(showMoreExtraClass: 'mt-8') }}",
    )

    expect(data).toEqual({ anchor: '', class: 'mt-8', collapsible: true, legacy: false })
  })

  it('reads an anchor named out of order', () => {
    const { data } = captureInsert(
      GroupStart.importFromMarkdown,
      "{{ startShowMore(showMoreExtraClass: 'mt-8', id: 'faq') }}",
    )

    expect(data).toEqual({ anchor: 'faq', class: 'mt-8', collapsible: true, legacy: false })
  })

  /** A colon inside a quoted class is not an argument name. */
  it('does not read a colon inside a literal as a name', () => {
    const { data } = captureInsert(
      GroupStart.importFromMarkdown,
      "{{ startShowMore('faq', 'lg:mt-8') }}",
    )

    expect(data).toEqual({ anchor: 'faq', class: 'lg:mt-8', collapsible: true, legacy: false })
  })
})

describe('a group and a collapsible never close each other', () => {
  const tools = [
    { name: GroupRegistry.START, constructable: GroupStart },
    { name: GroupRegistry.END, constructable: GroupEnd },
    { name: 'codeBlock', constructable: CodeBlock },
    { name: 'paragraph', constructable: Paragraph },
    { name: 'raw', constructable: Raw },
  ] as any[]

  const classify = (markdown: string): string[] => {
    const nesting = new GroupNesting()
    return MarkdownUtils.chunkMarkdown(markdown).map(
      (chunk) => chunkTool(tools, chunk.text, nesting)?.name ?? 'none',
    )
  }

  it('imports a comment pair as markers', () => {
    expect(classify('<!--start-show-more-->\n\ntext\n\n<!--end-show-more-->')).toEqual([
      GroupRegistry.START,
      'paragraph',
      GroupRegistry.END,
    ])
  })

  it('imports a Twig pair as markers', () => {
    expect(classify("{{ startShowMore('faq') }}\n\ntext\n\n{{ endShowMore() }}")).toEqual([
      GroupRegistry.START,
      'paragraph',
      GroupRegistry.END,
    ])
  })

  it('leaves a collapsible closer with nothing open Raw', () => {
    expect(classify('<!--end-show-more-->\n\ntext')).toEqual(['raw', 'paragraph'])
  })

  /** A `</div>` must not close a collapsible, nor the reverse: both stay Raw. */
  it('does not let a div closer end a collapsible', () => {
    expect(classify('<!--start-show-more-->\n\ntext\n\n</div>')).toEqual([
      GroupRegistry.START,
      'paragraph',
      'raw',
    ])
  })

  it('does not let a collapsible closer end a group', () => {
    expect(classify('<div id="faq">\n\ntext\n\n<!--end-show-more-->')).toEqual([
      GroupRegistry.START,
      'paragraph',
      'raw',
    ])
  })

  it('nests a group inside a collapsible', () => {
    expect(
      classify('<!--start-show-more-->\n\n<div class="grid">\n\ntext\n\n</div>\n\n<!--end-show-more-->'),
    ).toEqual([
      GroupRegistry.START,
      GroupRegistry.START,
      'paragraph',
      GroupRegistry.END,
      GroupRegistry.END,
    ])
  })

  it('ignores markers a fenced code block only talks about', () => {
    expect(
      classify('text\n\n```markdown\n<!--start-show-more-->\n```\n\ntext'),
    ).toEqual(['paragraph', 'codeBlock', 'paragraph'])
  })
})

describe('GroupRegistry.computePairs across kinds', () => {
  const start = (id: string, kind: 'div' | 'showMore' = 'div') => ({ id, name: 'groupStart', kind })
  const end = (id: string, kind: 'div' | 'showMore' = 'div') => ({ id, name: 'groupEnd', kind })

  it('pairs a collapsible nested in a group, each with its own closer', () => {
    const pairs = GroupRegistry.computePairs([
      start('d1'),
      start('s1', 'showMore'),
      end('e1', 'showMore'),
      end('e2'),
    ])

    expect(pairs.get('s1')).toBe('e1')
    expect(pairs.get('d1')).toBe('e2')
  })

  /** Crossed ranges are broken content: neither marker gets to drag the other. */
  it('leaves crossed markers unpaired', () => {
    const pairs = GroupRegistry.computePairs([
      start('d1'),
      start('s1', 'showMore'),
      end('e1'),
      end('e2', 'showMore'),
    ])

    expect(pairs.size).toBe(0)
  })
})

describe('the collapsible checkbox', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  function renderedTool(data: GroupStartData) {
    const stub = apiStub([
      { id: 'g1', name: 'groupStart' },
      { id: 'g2', name: 'groupEnd' },
    ])
    const tool = new GroupStart({
      data,
      api: stub.api,
      block: { id: 'g1', dispatchChange: vi.fn() },
      readOnly: false,
    } as any)
    return { stub, tool, element: tool.render() }
  }

  const toggle = (element: HTMLElement): void => {
    const input = element.querySelector('.pw-group-checkbox') as HTMLInputElement
    input.checked = !input.checked
    input.dispatchEvent(new Event('change'))
  }

  it('flips the block to the show-more spelling', () => {
    const { tool, element } = renderedTool({ anchor: 'faq', class: '', collapsible: false })

    toggle(element)

    expect(tool.save().collapsible).toBe(true)
    expect(GroupStart.exportToMarkdown(tool.save())).toBe("{{ startShowMore('faq') }}")
  })

  /** Pairing reads the kind off the rendered marker, so the DOM has to follow. */
  it('retags the marker so pairing sees the new kind', () => {
    const { element } = renderedTool({ anchor: '', class: '', collapsible: false })

    expect(element.dataset.pwGroupKind).toBe('div')
    toggle(element)
    expect(element.dataset.pwGroupKind).toBe('showMore')
  })

  it('tells the closing marker, which is a block of its own', async () => {
    const { stub, element } = renderedTool({ anchor: '', class: '', collapsible: false })
    GroupRegistry.schedule(stub.api)
    await tick()

    toggle(element)

    expect(stub.updated).toEqual([
      { id: 'g2', data: { collapsible: true, legacy: false, args: '' } },
    ])
  })

  it('says which wrapper the class lands on', () => {
    const { element } = renderedTool({ anchor: '', class: '', collapsible: false })
    const classInput = element.querySelector('.pw-group-class') as HTMLInputElement

    expect(classInput.placeholder).toBe('Class')
    toggle(element)
    expect(classInput.placeholder).toBe('Class of the collapsible')
  })
})

describe('pairing reads the kind off the rendered marker', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  /** A block as the editor hands it over: its holder carries what render() drew. */
  const markerBlock = (id: string, name: string, kind: 'div' | 'showMore') => {
    const holder = document.createElement('div')
    const marker = document.createElement('div')
    marker.className = name === GroupRegistry.START ? 'pw-group-start' : 'pw-group-end'
    marker.dataset.pwGroupKind = kind
    holder.appendChild(marker)
    return { id, name, holder }
  }

  /**
   * Crossed ranges: a collapsible opened inside a group and closed outside it.
   * Read the kinds and nothing pairs; miss them and every marker looks like a
   * `div`, so deleting the collapsible's opener would take the group's closer.
   */
  it('leaves crossed markers unpaired, so deleting one drags nothing', async () => {
    const stub = apiStub([
      markerBlock('d1', GroupRegistry.START, 'div'),
      markerBlock('s1', GroupRegistry.START, 'showMore'),
      markerBlock('e1', GroupRegistry.END, 'div'),
      markerBlock('e2', GroupRegistry.END, 'showMore'),
    ])
    GroupRegistry.schedule(stub.api)
    await tick()

    stub.api.blocks.delete(stub.api.blocks.getBlockIndex('s1'))
    stub.deleted.length = 0
    GroupRegistry.removePartnerOf(stub.api, 's1')
    await tick()

    expect(stub.deleted).toEqual([])
  })

  it('still pairs a collapsible properly nested in a group', async () => {
    const stub = apiStub([
      markerBlock('d1', GroupRegistry.START, 'div'),
      markerBlock('s1', GroupRegistry.START, 'showMore'),
      markerBlock('e1', GroupRegistry.END, 'showMore'),
      markerBlock('e2', GroupRegistry.END, 'div'),
    ])
    GroupRegistry.schedule(stub.api)
    await tick()

    stub.api.blocks.delete(stub.api.blocks.getBlockIndex('s1'))
    stub.deleted.length = 0
    GroupRegistry.removePartnerOf(stub.api, 's1')
    await tick()

    expect(stub.deleted).toEqual(['e1'])
  })
})
