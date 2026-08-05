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
    this.list.splice(start, end - start + 1)
    this.list.forEach((kept, index) => (kept.index = index))
  }

  moveSpan(start: number, end: number, to: number): void {
    this.moved.push([start, end, to])
    const span = this.list.splice(start, end - start + 1)
    this.list.splice(to > end ? to - span.length : to, 0, ...span)
    this.list.forEach((moved, index) => (moved.index = index))
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

/** The panel the current test built, for the change notifications an editor sends. */
let panel: OutlinePanel

function buildPanel(list: OutlineEntry[]): StubSource {
  const source = new StubSource(list)
  panel = new OutlinePanel({
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

/** Label the focus sits on, empty when it left the rail — as a dropped focus does. */
function focusedText(): string {
  const active = document.activeElement
  return active instanceof HTMLElement && active.classList.contains('pw-outline-label')
    ? (active.querySelector('.pw-outline-text')?.textContent ?? '')
    : ''
}

beforeEach(() => {
  document.body.innerHTML = ''
  document.body.className = ''
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

  /** A header row offers both; each click gets its own render, as a user's does. */
  function headerActionsOf(): HTMLButtonElement[] {
    return [...(rows()[1]?.querySelectorAll<HTMLButtonElement>('.pw-outline-action') ?? [])]
  }

  function sectionPanel(): StubSource {
    return buildPanel([
      entry(0, 'paragraph', 'intro'),
      entry(1, 'header', 'Title', 2),
      entry(2, 'paragraph', 'body'),
    ])
  }

  it('offers heading and section deletion on a section header', () => {
    sectionPanel()

    expect(headerActionsOf().map((button) => button.title)).toEqual([
      'Delete the heading',
      'Delete the section',
    ])
  })

  it('deletes the heading alone on the first action', () => {
    const source = sectionPanel()

    headerActionsOf()[0]?.click()

    expect(source.deleted).toEqual([[1, 1]])
  })

  it('deletes the whole section on the second action', () => {
    const source = sectionPanel()

    headerActionsOf()[1]?.click()

    expect(source.deleted).toEqual([[1, 2]])
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

describe('OutlinePanel stale rows', () => {
  function twoSections(): OutlineEntry[] {
    return [
      entry(0, 'header', 'A', 2),
      entry(1, 'paragraph', 'a body'),
      entry(2, 'header', 'B', 2),
      entry(3, 'paragraph', 'b body'),
    ]
  }

  it('does not let a double-click take the section that moved up', () => {
    const list = twoSections()
    const source = buildPanel(list)

    // Both clicks land on the button rendered for section A, as a double-click does.
    const deleteSection = rows()[0]?.querySelectorAll<HTMLButtonElement>(
      '.pw-outline-action',
    )[1]
    deleteSection?.click()
    deleteSection?.click()

    expect(source.deleted).toEqual([[0, 1]])
    expect(list.map((kept) => kept.label)).toEqual(['B', 'b body'])
  })

  it('rebuilds the rows the click acted on', () => {
    const source = buildPanel(twoSections())

    rows()[0]?.querySelectorAll<HTMLButtonElement>('.pw-outline-action')[1]?.click()

    expect(source.deleted).toEqual([[0, 1]])
    expect(rows().map(rowText)).toEqual(['B', 'b body'])
  })

  it('drops a rail action queued behind an unapplied editor edit', () => {
    const list = twoSections()
    const source = buildPanel(list)
    const deleteSection = rows()[0]?.querySelectorAll<HTMLButtonElement>(
      '.pw-outline-action',
    )[1]

    // The editor reports a change; the rebuild is still inside its debounce.
    list.splice(0, 1)
    list.forEach((moved, index) => (moved.index = index))
    panel.scheduleRefresh()
    deleteSection?.click()

    expect(source.deleted).toEqual([])
    expect(rows().map(rowText)).toEqual(['a body', 'B', 'b body'])
  })

  it('refuses a keyboard move from a row the rail has redrawn', () => {
    const source = buildPanel(twoSections())
    const label = rows()[1]?.querySelector<HTMLButtonElement>('.pw-outline-label')

    panel.refresh()
    label?.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'ArrowDown', altKey: true }),
    )

    expect(source.moved).toEqual([])
  })

  /** The guard must cost nothing in normal use: each new render acts again. */
  it('acts again on the row that replaced the one just deleted', () => {
    const list = [
      entry(0, 'paragraph', 'a'),
      entry(1, 'paragraph', 'b'),
      entry(2, 'paragraph', 'c'),
    ]
    const source = buildPanel(list)

    rows()[0]?.querySelector<HTMLButtonElement>('.pw-outline-action')?.click()
    rows()[0]?.querySelector<HTMLButtonElement>('.pw-outline-action')?.click()

    expect(source.deleted).toEqual([
      [0, 0],
      [0, 0],
    ])
    expect(list.map((kept) => kept.label)).toEqual(['c'])
  })

  it('refuses a drop whose rows were rebuilt mid-drag', () => {
    const source = buildPanel(twoSections())

    rows()[1]?.querySelector<HTMLButtonElement>('.pw-outline-handle')?.dispatchEvent(
      new Event('dragstart'),
    )
    panel.refresh()
    rows()[3]?.dispatchEvent(new MouseEvent('drop', { clientY: -5 }))

    expect(source.moved).toEqual([])
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

  it('swaps two sections with Alt+Shift+Arrow, bodies included', () => {
    const list = [
      entry(0, 'header', 'S', 2),
      entry(1, 'paragraph', 'body'),
      entry(2, 'header', 'T', 2),
      entry(3, 'paragraph', 'other'),
    ]
    const source = buildPanel(list)

    pressOnLabel(0, 'ArrowDown', { altKey: true, shiftKey: true })

    expect(source.moved).toEqual([[0, 1, 4]])
    expect(list.map((moved) => moved.label)).toEqual(['T', 'other', 'S', 'body'])
  })

  it('moves a section back up over the section above it', () => {
    const source = buildPanel([
      entry(0, 'header', 'S', 2),
      entry(1, 'paragraph', 'body'),
      entry(2, 'header', 'T', 2),
      entry(3, 'paragraph', 'other'),
    ])

    pressOnLabel(2, 'ArrowDown', { altKey: true, shiftKey: true })
    pressOnLabel(2, 'ArrowUp', { altKey: true, shiftKey: true })

    // Nothing below to swap with, then back over S.
    expect(source.moved).toEqual([[2, 3, 0]])
  })

  it('clears a folded section instead of dropping the block into it', () => {
    const source = buildPanel([
      entry(0, 'paragraph', 'a'),
      entry(1, 'header', 'B', 2),
      entry(2, 'paragraph', 'body'),
    ])
    document.querySelector<HTMLButtonElement>('.pw-outline-caret[aria-expanded]')?.click()

    pressOnLabel(0, 'ArrowDown', { altKey: true })

    expect(source.moved).toEqual([[0, 0, 3]])
    expect(focusedText()).toBe('a')
  })

  it('keeps the focus on the row it moved', () => {
    buildPanel([
      entry(0, 'paragraph', 'a'),
      entry(1, 'header', 'B', 2),
      entry(2, 'paragraph', 'body'),
    ])

    pressOnLabel(2, 'ArrowUp', { altKey: true })

    expect(rowText(rows()[1])).toBe('body')
    expect(focusedText()).toBe('body')
  })

  it('moves a group whole on a plain Alt+Arrow, end marker included', () => {
    const list = [
      entry(0, 'groupStart', ''),
      entry(1, 'paragraph', 'inside'),
      entry(2, 'groupEnd', ''),
      entry(3, 'paragraph', 'after'),
    ]
    const source = buildPanel(list)

    pressOnLabel(0, 'ArrowDown', { altKey: true })

    expect(source.moved).toEqual([[0, 2, 4]])
    expect(list.map((moved) => moved.type)).toEqual([
      'paragraph',
      'groupStart',
      'paragraph',
      'groupEnd',
    ])
    expect(focusedText()).toBe('title:groupStart')
  })

  it('moves a heading alone over its own first block', () => {
    const list = [
      entry(0, 'header', 'S', 2),
      entry(1, 'paragraph', 'a'),
      entry(2, 'paragraph', 'b'),
    ]
    const source = buildPanel(list)

    pressOnLabel(0, 'ArrowDown', { altKey: true })

    expect(source.moved).toEqual([[0, 0, 2]])
    expect(list.map((moved) => moved.label)).toEqual(['a', 'S', 'b'])
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

  it('shifts the admin while open, releases it once collapsed', () => {
    buildPanel([entry(0, 'paragraph', 'intro')])
    expect(document.body.classList.contains('pw-outline-open')).toBe(true)

    document.querySelector<HTMLButtonElement>('.pw-outline-toggle')?.click()

    expect(document.body.classList.contains('pw-outline-open')).toBe(false)
  })
})
