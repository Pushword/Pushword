import { describe, it, expect } from 'vitest'
import { buildOutlineTree, OutlineEntry, OutlineNode } from './OutlineModel'

/** 'h2'..'h6' → header, 'gs'/'ge' → group markers, anything else → leaf type. */
function entries(types: string[]): OutlineEntry[] {
  return types.map((type, index) => {
    const header = /^h([2-6])$/.exec(type)
    if (header) {
      return { index, type: 'header', label: type, level: Number(header[1]) }
    }
    if (type === 'gs') return { index, type: 'groupStart', label: 'gs', level: null }
    if (type === 'ge') return { index, type: 'groupEnd', label: 'ge', level: null }
    return { index, type, label: type, level: null }
  })
}

/** One line per tree: label:start-spanEnd[children]. */
function shape(nodes: OutlineNode[]): string {
  return nodes
    .map((node) => {
      const base = `${node.entry.label}:${node.entry.index}-${node.spanEnd}`
      return node.children.length === 0 ? base : `${base}[${shape(node.children)}]`
    })
    .join(' ')
}

function treeOf(types: string[]): string {
  return shape(buildOutlineTree(entries(types)))
}

describe('buildOutlineTree — headers', () => {
  it('keeps a headerless document flat', () => {
    expect(treeOf(['p', 'p'])).toBe('p:0-0 p:1-1')
  })

  it('leaves blocks before the first header at top level', () => {
    expect(treeOf(['p', 'h2', 'p'])).toBe('p:0-0 h2:1-2[p:2-2]')
  })

  it('nests an h3 section inside its h2 and closes both at the next h2', () => {
    expect(treeOf(['h2', 'p', 'h3', 'p', 'h2', 'p'])).toBe(
      'h2:0-3[p:1-1 h3:2-3[p:3-3]] h2:4-5[p:5-5]',
    )
  })

  it('gives adjacent same-level headers empty sections', () => {
    expect(treeOf(['h2', 'h2'])).toBe('h2:0-0 h2:1-1')
  })

  it('closes an h3 section when an h2 follows', () => {
    expect(treeOf(['h3', 'p', 'h2', 'p'])).toBe('h3:0-1[p:1-1] h2:2-3[p:3-3]')
  })

  it('owns everything to the end when no header follows', () => {
    expect(treeOf(['h2', 'p', 'p'])).toBe('h2:0-2[p:1-1 p:2-2]')
  })
})

describe('buildOutlineTree — groups', () => {
  it('absorbs the end marker into the group node', () => {
    expect(treeOf(['gs', 'p', 'ge', 'p'])).toBe('gs:0-2[p:1-1] p:3-3')
  })

  it('nests groups innermost-first', () => {
    expect(treeOf(['gs', 'gs', 'p', 'ge', 'ge'])).toBe('gs:0-4[gs:1-3[p:2-2]]')
  })

  it('keeps sequential groups independent', () => {
    expect(treeOf(['gs', 'ge', 'gs', 'ge'])).toBe('gs:0-1 gs:2-3')
  })

  it('keeps unmatched markers as plain leaves', () => {
    expect(treeOf(['ge', 'p'])).toBe('ge:0-0 p:1-1')
    expect(treeOf(['gs', 'p'])).toBe('gs:0-0 p:1-1')
  })

  it('represents an empty group as a childless span', () => {
    expect(treeOf(['gs', 'ge'])).toBe('gs:0-1')
  })
})

describe('buildOutlineTree — headers and groups interleaved', () => {
  it('lets a header section adopt a whole group', () => {
    expect(treeOf(['h2', 'gs', 'p', 'ge', 'p'])).toBe('h2:0-4[gs:1-3[p:2-2] p:4-4]')
  })

  it('never lets a header inside a group terminate a section outside it', () => {
    expect(treeOf(['h2', 'gs', 'h2', 'p', 'ge', 'p'])).toBe(
      'h2:0-5[gs:1-4[h2:2-3[p:3-3]] p:5-5]',
    )
  })

  it('closes sections inside a group at the group end', () => {
    expect(treeOf(['gs', 'h2', 'p', 'ge', 'p'])).toBe('gs:0-3[h2:1-2[p:2-2]] p:4-4')
  })

  it('closes a section holding a group when the next header arrives', () => {
    expect(treeOf(['h2', 'gs', 'p', 'ge', 'h2'])).toBe('h2:0-3[gs:1-3[p:2-2]] h2:4-4')
  })
})
