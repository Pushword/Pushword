import { describe, it, expect, beforeEach, vi } from 'vitest'
import Table from './table'

/**
 * Characterisation tests for the toolbox-position bounds guards in
 * updateToolboxesPosition(). Row/column 0 is a valid input (initial state, and
 * the explicit updateToolboxesPosition(0, 0) reset fired after addRow), and must
 * keep both toolboxes hidden — showing them then would render at a negative
 * calc() offset or call getRow(0). These tests lock that behaviour so the guards
 * are not "simplified" away in a later refactor.
 */

type AnyTable = Table & Record<string, any>

function newTable(content: string[][] = [['a', 'b'], ['c', 'd']]): AnyTable {
  // The Table only touches api.i18n.t while building its toolboxes; readOnly
  // skips the document-level event bindings we don't need here.
  const api = { i18n: { t: (key: string) => key } }
  return new Table(true, api, { content }, {}) as AnyTable
}

describe('Table.updateToolboxesPosition bounds guards', () => {
  let table: AnyTable
  let colShow: ReturnType<typeof vi.spyOn>
  let rowShow: ReturnType<typeof vi.spyOn>

  beforeEach(() => {
    table = newTable()
    // No-op the show() implementations so we assert on the guard decision only,
    // without running the position callbacks (they read layout geometry).
    colShow = vi.spyOn(table.toolboxColumn, 'show').mockImplementation(() => {})
    rowShow = vi.spyOn(table.toolboxRow, 'show').mockImplementation(() => {})
  })

  it('builds the table from data content', () => {
    expect(table.numberOfRows).toBe(2)
    expect(table.numberOfColumns).toBe(2)
  })

  it('keeps both toolboxes hidden at the (0, 0) reset position', () => {
    table.updateToolboxesPosition(0, 0)
    expect(colShow).not.toHaveBeenCalled()
    expect(rowShow).not.toHaveBeenCalled()
  })

  it('shows both toolboxes for an in-bounds hovered cell', () => {
    table.updateToolboxesPosition(1, 1)
    expect(colShow).toHaveBeenCalledTimes(1)
    expect(rowShow).toHaveBeenCalledTimes(1)
  })

  it('keeps toolboxes hidden when the coordinate exceeds the table size', () => {
    table.updateToolboxesPosition(99, 99)
    expect(colShow).not.toHaveBeenCalled()
    expect(rowShow).not.toHaveBeenCalled()
  })
})

describe('Table colspan (`->`) spanning', () => {
  function cellsOf(table: AnyTable, row: number): HTMLElement[] {
    return Array.from(table.getRow(row).querySelectorAll('.tc-cell'))
  }

  it('spans the content cell across the trailing `->` markers and tags them', () => {
    const table = newTable([['A', '->'], ['1', '2']])
    const [content, marker] = cellsOf(table, 1) as [HTMLElement, HTMLElement]
    expect(content.style.gridColumnStart).toBe('1')
    expect(content.style.gridColumnEnd).toBe('span 2')
    expect(marker.style.gridColumnStart).toBe('2')
    expect(marker.style.gridColumnEnd).toBe('')
    expect(marker.classList.contains('tc-cell--colspan')).toBe(true)
    // a normal row is left on single tracks (no span)
    expect(cellsOf(table, 2)[0]!.style.gridColumnEnd).toBe('')
  })

  it('sets the explicit column-track count on the table', () => {
    const table = newTable([['A', 'B', 'C'], ['1', '2', '3']])
    expect(table.table!.style.getPropertyValue('--table-cols')).toBe('3')
  })

  it('merges a run of consecutive markers and un-merges when a `->` is cleared', () => {
    const table = newTable([['A', '->', '->'], ['1', '2', '3']])
    expect(cellsOf(table, 1)[0]!.style.gridColumnEnd).toBe('span 3')

    // Clear the first marker; the span shifts to the remaining run.
    cellsOf(table, 1)[1]!.textContent = 'x'
    table.updateColspanMarkers()
    expect(cellsOf(table, 1)[0]!.style.gridColumnEnd).toBe('')
    expect(cellsOf(table, 1)[1]!.style.gridColumnStart).toBe('2')
    expect(cellsOf(table, 1)[1]!.style.gridColumnEnd).toBe('span 2')
  })
})
