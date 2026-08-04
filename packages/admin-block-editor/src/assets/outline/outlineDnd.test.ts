import { describe, it, expect } from 'vitest'
import { dropIndexFor, isActualMove } from './outlineDnd'
import { OutlineNode } from './OutlineModel'

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
