/** Table-block data extracted from an HTML `<table>`. */
export interface ParsedHtmlTable {
  content: string[][]
  withHeadings: boolean
  columnAlignments: string[]
}

/**
 * Convert a simple HTML `<table>` into Table-block data, and detect when a table
 * is too complex to be one.
 *
 * The Table block is a rectangular grid of inline-HTML cell strings: it can't
 * hold merged cells (colspan/rowspan) or block-level cell content. `isSimpleTable`
 * gates on exactly that, so complex tables are left untouched (the caller routes
 * them to a Raw HTML block) while simple ones become editable Table blocks that
 * round-trip losslessly to GFM. A table without a header row gets an empty header
 * prepended — GFM needs a delimiter (hence a header) to render — which the front
 * strips again when it is all-empty (see core EmptyTableHeadProcessor).
 */
export class HtmlTableUtils {
  /** Block-level tags a Table cell (an inline string) cannot represent. */
  private static readonly BLOCK_CELL_SELECTOR =
    'p,div,ul,ol,table,blockquote,pre,figure,hr,h1,h2,h3,h4,h5,h6'

  /** GFM-representable column alignments. */
  private static readonly ALIGNMENTS = ['left', 'center', 'right']

  /** Parse an HTML string and return its first `<table>`, or null when absent. */
  static parseTable(html: string): HTMLTableElement | null {
    const holder = document.createElement('div')
    holder.innerHTML = html
    return holder.querySelector('table')
  }

  /**
   * True when the table maps to a Table block: at least one row, no merged cells,
   * a regular rectangular shape, and only inline content in every cell.
   */
  static isSimpleTable(table: Element): boolean {
    // A nested table can't be flattened into a single grid.
    if (table.querySelector('table') !== null) return false

    const rows = Array.from(table.querySelectorAll('tr'))
    if (rows.length === 0) return false

    let columns = -1
    for (const row of rows) {
      const cells = Array.from(row.querySelectorAll('th,td'))
      if (cells.length === 0) return false

      for (const cell of cells) {
        if (HtmlTableUtils.spanOf(cell, 'colspan') > 1 || HtmlTableUtils.spanOf(cell, 'rowspan') > 1) {
          return false
        }
        if (cell.querySelector(HtmlTableUtils.BLOCK_CELL_SELECTOR) !== null) return false
      }

      if (columns === -1) columns = cells.length
      else if (cells.length !== columns) return false
    }

    return true
  }

  /** Build Table-block data from a simple `<table>` (assumes `isSimpleTable`). */
  static parse(table: Element): ParsedHtmlTable {
    const rows = Array.from(table.querySelectorAll('tr'))
    const content = rows.map((row) =>
      Array.from(row.querySelectorAll('th,td')).map((cell) => HtmlTableUtils.cellContent(cell)),
    )

    const columns = Math.max(...content.map((row) => row.length))
    const headerRow = HtmlTableUtils.headerRow(table, rows)

    if (headerRow === null) {
      // Headerless table: prepend an empty header so it stays a GFM table; the
      // front drops the empty <thead> and renders the original <tbody>-only look.
      content.unshift(new Array(columns).fill(''))
    }

    return {
      content,
      withHeadings: true,
      columnAlignments: HtmlTableUtils.alignments(headerRow ?? rows[0]!, columns),
    }
  }

  private static spanOf(cell: Element, attribute: string): number {
    const value = Number.parseInt(cell.getAttribute(attribute) ?? '1', 10)
    return Number.isNaN(value) ? 1 : value
  }

  /** The header `<tr>` (from `<thead>` or an all-`<th>` first row), or null. */
  private static headerRow(table: Element, rows: Element[]): Element | null {
    const theadRow = table.querySelector('thead tr')
    if (theadRow !== null) return theadRow

    const cells = Array.from(rows[0]!.querySelectorAll('th,td'))
    if (cells.length > 0 && cells.every((cell) => cell.tagName === 'TH')) return rows[0]!

    return null
  }

  private static cellContent(cell: Element): string {
    // The Table block renders cell content as innerHTML, so keep the inline HTML;
    // the grid is single-line, so collapse internal whitespace and line breaks.
    return cell.innerHTML.replace(/\s+/g, ' ').trim()
  }

  private static alignments(row: Element, columns: number): string[] {
    const cells = Array.from(row.querySelectorAll('th,td'))
    return Array.from({ length: columns }, (_unused, index) => HtmlTableUtils.alignOf(cells[index]))
  }

  private static alignOf(cell: Element | undefined): string {
    if (cell === undefined) return ''

    const attribute = (cell.getAttribute('align') ?? '').toLowerCase()
    if (HtmlTableUtils.ALIGNMENTS.includes(attribute)) return attribute

    const textAlign = (cell as HTMLElement).style?.textAlign ?? ''
    if (HtmlTableUtils.ALIGNMENTS.includes(textAlign)) return textAlign

    return ''
  }
}
