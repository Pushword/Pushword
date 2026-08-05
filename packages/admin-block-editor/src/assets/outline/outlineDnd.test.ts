import { describe, it, expect } from 'vitest'
import { dropIndexFor, isActualMove, keyboardDropIndex } from './outlineDnd'
import { buildOutlineTree, OutlineEntry, OutlineNode } from './OutlineModel'

const section: OutlineNode = {
  entry: { index: 5, type: 'header', label: 'S', level: 2 },
  children: [{ entry: { index: 6, type: 'paragraph', label: '', level: null }, children: [], spanEnd: 6 }],
  spanEnd: 9,
}

describe('dropIndexFor', () => {
  it('targets the row itself for a drop above', () => {
    expect(dropIndexFor(section, false, true)).toBe(5)
  })

  it('targets the first child slot below an expanded row', () => {
    expect(dropIndexFor(section, true, true)).toBe(6)
  })

  it('skips the whole span below a folded row or leaf', () => {
    expect(dropIndexFor(section, true, false)).toBe(10)
  })
})

describe('keyboardDropIndex', () => {
  /** A header level builds a header entry, null a paragraph; 'groupStart'/'groupEnd' a marker. */
  function treeOf(...units: (number | null | string)[]): OutlineNode[] {
    return buildOutlineTree(
      units.map((unit, index): OutlineEntry => {
        if (typeof unit === 'string') return { index, type: unit, label: '', level: null }

        return { index, type: unit === null ? 'paragraph' : 'header', label: '', level: unit }
      }),
    )
  }

  const unfolded = new Set<number>()

  function target(
    tree: OutlineNode[],
    span: { start: number; end: number },
    up: boolean,
    wholeSpan = false,
    folded = unfolded,
  ): number | null {
    return keyboardDropIndex(tree, { span, up, wholeSpan }, folded)
  }

  it('clears the neighbouring section, so two sections swap', () => {
    // ## A / a / ## B / b
    const tree = treeOf(2, null, 2, null)

    expect(target(tree, { start: 0, end: 1 }, false, true)).toBe(4)
    expect(target(tree, { start: 2, end: 3 }, true, true)).toBe(0)
  })

  it('walks a lone block one rendered row, into the section opening below it', () => {
    // a / ## B / b
    const tree = treeOf(null, 2, null)

    expect(target(tree, { start: 0, end: 0 }, false)).toBe(2)
    expect(target(tree, { start: 2, end: 2 }, true)).toBe(1)
  })

  it('walks a heading alone over the first block of its own section', () => {
    // The section closing right below the heading answers for its own body.
    expect(target(treeOf(2, null, 2), { start: 0, end: 0 }, false)).toBe(2)
    expect(target(treeOf(2, null, null, 2), { start: 0, end: 0 }, false)).toBe(2)
  })

  it('clears a folded section rather than landing in the hidden region', () => {
    // a / ## B (folded) / b / ## C
    const tree = treeOf(null, 2, null, 2)
    const folded = new Set([1])

    expect(target(tree, { start: 0, end: 0 }, false, false, folded)).toBe(3)
    expect(target(tree, { start: 3, end: 3 }, true, false, folded)).toBe(1)
  })

  it('leaves a group whole: a block steps out of it instead of hopping its end marker', () => {
    // groupStart / x / groupEnd / p
    const tree = treeOf('groupStart', null, 'groupEnd', null)

    expect(target(tree, { start: 1, end: 1 }, false)).toBe(3)
    expect(target(tree, { start: 0, end: 2 }, false, true)).toBe(4)
    // Up from below the group, the last row on screen is the block inside it.
    expect(target(tree, { start: 3, end: 3 }, true)).toBe(1)
  })

  it('has nowhere to go past the edges', () => {
    const tree = treeOf(null, null)

    expect(target(tree, { start: 0, end: 0 }, true)).toBeNull()
    expect(target(tree, { start: 1, end: 1 }, false)).toBeNull()
  })
})

describe('isActualMove', () => {
  it('accepts a target outside the span', () => {
    expect(isActualMove({ start: 1, end: 2 }, 0)).toBe(true)
    expect(isActualMove({ start: 1, end: 2 }, 4)).toBe(true)
  })

  it('rejects targets inside or right around the span', () => {
    expect(isActualMove({ start: 1, end: 2 }, 1)).toBe(false)
    expect(isActualMove({ start: 1, end: 2 }, 2)).toBe(false)
    expect(isActualMove({ start: 1, end: 2 }, 3)).toBe(false)
  })
})
