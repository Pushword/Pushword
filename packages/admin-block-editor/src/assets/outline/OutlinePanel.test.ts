import { describe, it, expect, beforeEach } from 'vitest'
import { OutlineLabels, OutlinePanel } from './OutlinePanel'
import { OutlineEntry, OutlineSource } from './OutlineModel'

const labels: OutlineLabels = {
  deleteBlock: 'Delete the block',
  deleteGroup: 'Delete the group',
  deleteHeading: 'Delete the heading',
  deleteSection: 'Delete the section',
  moveBlock: 'Move the block',
  moveGroup: 'Move the group',
  moveHeading: 'Move the heading',
  moveSection: 'Move the section',
  outline: 'Outline',
  toggleSection: 'Collapse',
}

class StubSource implements OutlineSource {
  navigated: OutlineEntry[] = []
  deleted: [number, number][] = []
  moved: [number, number, number][] = []

  constructor(private readonly list: OutlineEntry[]) {}

  entries(): OutlineEntry[] {
    return this.list
  }

  navigateTo(entry: OutlineEntry): void {
    this.navigated.push(entry)
  }

  deleteSpan(start: number, end: number): void {
    this.deleted.push([start, end])
  }

  moveSpan(start: number, end: number, to: number): void {
    this.moved.push([start, end, to])
  }
}

function entry(
  index: number,
  type: string,
  label: string,
  level: number | null = null,
): OutlineEntry {
  return { index, type, label, level }
}

function buildPanel(list: OutlineEntry[]): StubSource {
  const source = new StubSource(list)
  const panel = new OutlinePanel({
    holderId: 'no-such-holder',
    source,
    labels,
    toolMeta: (type) => ({ title: 'title:' + type, icon: '' }),
  })
  panel.refresh()
  return source
}

function rows(): HTMLElement[] {
  return [...document.querySelectorAll<HTMLElement>('.pw-outline-row')]
}

function rowText(row: HTMLElement | undefined): string {
  return row?.querySelector('.pw-outline-text')?.textContent ?? ''
}

beforeEach(() => {
  document.body.innerHTML = ''
  localStorage.setItem('pw-outline-collapsed', '0')
})

describe('OutlinePanel rendering', () => {
  it('renders one row per unit and nests section children', () => {
    buildPanel([
      entry(0, 'paragraph', 'intro'),
      entry(1, 'header', 'Title', 2),
      entry(2, 'paragraph', 'body'),
    ])

    expect(rows()).toHaveLength(3)
    expect(rowText(rows()[1])).toBe('Title')
    const nested = document.querySelector('.pw-outline-children')
    expect(rowText(nested?.querySelector('.pw-outline-row') ?? undefined)).toBe('body')
  })

  it('absorbs a matched groupEnd and falls back to the tool title on empty labels', () => {
    buildPanel([
      entry(0, 'groupStart', ''),
      entry(1, 'paragraph', 'inside'),
      entry(2, 'groupEnd', ''),
    ])

    expect(rows()).toHaveLength(2)
    expect(rowText(rows()[0])).toBe('title:groupStart')
    expect(rows()[0]?.querySelector('.pw-outline-text--type')).not.toBeNull()
  })
})

describe('OutlinePanel folding', () => {
  it('folds a section on caret click and keeps it folded across refreshes', () => {
    buildPanel([entry(0, 'header', 'Title', 2), entry(1, 'paragraph', 'body')])

    const caret = document.querySelector<HTMLButtonElement>('.pw-outline-caret')
    expect(caret?.getAttribute('aria-expanded')).toBe('true')
    caret?.click()

    expect(rows()).toHaveLength(1)
    expect(
      document.querySelector('.pw-outline-caret')?.getAttribute('aria-expanded'),
    ).toBe('false')
  })
})

describe('OutlinePanel actions', () => {
  it('navigates on label click', () => {
    const source = buildPanel([entry(0, 'paragraph', 'intro')])

    document.querySelector<HTMLButtonElement>('.pw-outline-label')?.click()

    expect(source.navigated.map((visited) => visited.index)).toEqual([0])
  })

  it('deletes a leaf as a single block', () => {
    const source = buildPanel([entry(0, 'paragraph', 'intro')])

    document.querySelector<HTMLButtonElement>('.pw-outline-action')?.click()

    expect(source.deleted).toEqual([[0, 0]])
  })

  it('offers heading and section deletion on a section header', () => {
    const source = buildPanel([
      entry(0, 'paragraph', 'intro'),
      entry(1, 'header', 'Title', 2),
      entry(2, 'paragraph', 'body'),
    ])

    const headerActions = [
      ...(rows()[1]?.querySelectorAll<HTMLButtonElement>('.pw-outline-action') ?? []),
    ]
    expect(headerActions.map((button) => button.title)).toEqual([
      'Delete the heading',
      'Delete the section',
    ])

    headerActions[0]?.click()
    headerActions[1]?.click()
    expect(source.deleted).toEqual([
      [1, 1],
      [1, 2],
    ])
  })

  it('pairs heading and section drag handles on a section header', () => {
    buildPanel([
      entry(0, 'paragraph', 'intro'),
      entry(1, 'header', 'Title', 2),
      entry(2, 'paragraph', 'body'),
    ])

    const handles = [
      ...(rows()[1]?.querySelectorAll<HTMLButtonElement>('.pw-outline-handle') ?? []),
    ]
    expect(handles.map((handle) => handle.title)).toEqual([
      'Move the heading',
      'Move the section',
    ])
    expect(handles.every((handle) => handle.draggable)).toBe(true)
  })

  it('deletes a group with its whole span, end marker included', () => {
    const source = buildPanel([
      entry(0, 'groupStart', ''),
      entry(1, 'paragraph', 'inside'),
      entry(2, 'groupEnd', ''),
    ])

    const groupActions = [
      ...(rows()[0]?.querySelectorAll<HTMLButtonElement>('.pw-outline-action') ?? []),
    ]
    expect(groupActions.map((button) => button.title)).toEqual(['Delete the group'])

    groupActions[0]?.click()
    expect(source.deleted).toEqual([[0, 2]])
  })
})

describe('OutlinePanel drag and drop', () => {
  function dragFromRow(index: number): void {
    const row = rows()[index]
    row?.querySelector<HTMLButtonElement>('.pw-outline-handle')?.dispatchEvent(
      new Event('dragstart'),
    )
  }

  it('moves a block dropped above another row', () => {
    const source = buildPanel([
      entry(0, 'paragraph', 'a'),
      entry(1, 'paragraph', 'b'),
      entry(2, 'paragraph', 'c'),
    ])

    dragFromRow(0)
    // Row rects are all zero-sized in happy-dom: a negative clientY lands "above".
    rows()[2]?.dispatchEvent(new MouseEvent('drop', { clientY: -5 }))

    expect(source.moved).toEqual([[0, 0, 2]])
  })

  it('drops a section after a folded span, not inside it', () => {
    const source = buildPanel([
      entry(0, 'paragraph', 'a'),
      entry(1, 'header', 'Section', 2),
      entry(2, 'paragraph', 'body'),
    ])
    document.querySelector<HTMLButtonElement>('.pw-outline-caret[aria-expanded]')?.click()

    dragFromRow(0)
    rows()[1]?.dispatchEvent(new MouseEvent('drop', { clientY: 5 }))

    expect(source.moved).toEqual([[0, 0, 3]])
  })

  it('ignores a drop of a span into itself', () => {
    const source = buildPanel([
      entry(0, 'header', 'Section', 2),
      entry(1, 'paragraph', 'body'),
      entry(2, 'paragraph', 'after'),
    ])

    const sectionHandle = [
      ...(rows()[0]?.querySelectorAll<HTMLButtonElement>('.pw-outline-handle') ?? []),
    ][1]
    sectionHandle?.dispatchEvent(new Event('dragstart'))
    rows()[1]?.dispatchEvent(new MouseEvent('drop', { clientY: -5 }))

    expect(source.moved).toEqual([])
  })
})

describe('OutlinePanel keyboard', () => {
  function pressOnLabel(row: number, key: string, init: KeyboardEventInit = {}): void {
    rows()[row]?.querySelector<HTMLButtonElement>('.pw-outline-label')?.dispatchEvent(
      new KeyboardEvent('keydown', { key, ...init }),
    )
  }

  it('moves a block one slot with Alt+Arrow', () => {
    const source = buildPanel([entry(0, 'paragraph', 'a'), entry(1, 'paragraph', 'b')])

    pressOnLabel(0, 'ArrowDown', { altKey: true })

    expect(source.moved).toEqual([[0, 0, 2]])
  })

  it('moves the whole section with Alt+Shift+Arrow', () => {
    const source = buildPanel([
      entry(0, 'header', 'S', 2),
      entry(1, 'paragraph', 'body'),
      entry(2, 'header', 'T', 2),
    ])

    pressOnLabel(0, 'ArrowDown', { altKey: true, shiftKey: true })

    expect(source.moved).toEqual([[0, 1, 3]])
  })

  it('never moves past the edges', () => {
    const source = buildPanel([entry(0, 'paragraph', 'a'), entry(1, 'paragraph', 'b')])

    pressOnLabel(0, 'ArrowUp', { altKey: true })
    pressOnLabel(1, 'ArrowDown', { altKey: true })

    expect(source.moved).toEqual([])
  })
})

describe('OutlinePanel collapse', () => {
  it('collapses to the opener and persists the choice', () => {
    buildPanel([entry(0, 'paragraph', 'intro')])

    document.querySelector<HTMLButtonElement>('.pw-outline-toggle')?.click()

    expect(document.querySelector('.pw-outline--collapsed')).not.toBeNull()
    expect(document.querySelector<HTMLElement>('.pw-outline-opener')?.hidden).toBe(false)
    expect(localStorage.getItem('pw-outline-collapsed')).toBe('1')
  })

  it('starts collapsed when the stored preference says so', () => {
    localStorage.setItem('pw-outline-collapsed', '1')

    buildPanel([entry(0, 'paragraph', 'intro')])

    expect(document.querySelector('.pw-outline--collapsed')).not.toBeNull()
  })
})
