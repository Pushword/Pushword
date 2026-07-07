import { describe, it, expect } from 'vitest'
import { HtmlTableUtils } from './HtmlTableUtils'

function table(html: string): HTMLTableElement {
  const holder = document.createElement('div')
  holder.innerHTML = html
  return holder.querySelector('table')!
}

describe('HtmlTableUtils.isSimpleTable', () => {
  it('accepts a plain rectangular grid', () => {
    expect(
      HtmlTableUtils.isSimpleTable(
        table('<table><tr><td>a</td><td>b</td></tr><tr><td>c</td><td>d</td></tr></table>'),
      ),
    ).toBe(true)
  })

  it('accepts inline formatting and links in cells', () => {
    expect(
      HtmlTableUtils.isSimpleTable(
        table('<table><tr><td><strong>En bref</strong></td><td><a href="/x">y</a></td></tr></table>'),
      ),
    ).toBe(true)
  })

  it('rejects colspan', () => {
    expect(
      HtmlTableUtils.isSimpleTable(
        table('<table><tr><td colspan="2">a</td></tr><tr><td>b</td><td>c</td></tr></table>'),
      ),
    ).toBe(false)
  })

  it('rejects rowspan', () => {
    expect(
      HtmlTableUtils.isSimpleTable(
        table('<table><tr><td rowspan="2">a</td><td>b</td></tr><tr><td>c</td></tr></table>'),
      ),
    ).toBe(false)
  })

  it('rejects block-level cell content (list)', () => {
    expect(
      HtmlTableUtils.isSimpleTable(
        table('<table><tr><td><ul><li>a</li></ul></td><td>b</td></tr></table>'),
      ),
    ).toBe(false)
  })

  it('rejects nested tables', () => {
    expect(
      HtmlTableUtils.isSimpleTable(
        table('<table><tr><td><table><tr><td>x</td></tr></table></td><td>b</td></tr></table>'),
      ),
    ).toBe(false)
  })

  it('rejects ragged rows', () => {
    expect(
      HtmlTableUtils.isSimpleTable(
        table('<table><tr><td>a</td><td>b</td></tr><tr><td>c</td></tr></table>'),
      ),
    ).toBe(false)
  })

  it('rejects a table with no rows', () => {
    expect(HtmlTableUtils.isSimpleTable(table('<table></table>'))).toBe(false)
  })

  it('rejects a row with no cells', () => {
    expect(HtmlTableUtils.isSimpleTable(table('<table><tr></tr></table>'))).toBe(false)
  })
})

describe('HtmlTableUtils.parse', () => {
  it('injects an empty header row for a headerless table', () => {
    const parsed = HtmlTableUtils.parse(
      table(
        '<table><tbody>' +
          '<tr><td><strong>En bref</strong></td><td>desc</td></tr>' +
          '<tr><td><strong>Ideal</strong></td><td>walk</td></tr>' +
          '</tbody></table>',
      ),
    )

    expect(parsed.withHeadings).toBe(true)
    expect(parsed.content[0]).toEqual(['', ''])
    expect(parsed.content[1]).toEqual(['<strong>En bref</strong>', 'desc'])
    expect(parsed.content).toHaveLength(3)
  })

  it('uses a real <thead> row as the header', () => {
    const parsed = HtmlTableUtils.parse(
      table('<table><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr></tbody></table>'),
    )

    expect(parsed.withHeadings).toBe(true)
    expect(parsed.content[0]).toEqual(['A', 'B'])
    expect(parsed.content[1]).toEqual(['1', '2'])
    expect(parsed.content).toHaveLength(2)
  })

  it('treats an all-<th> first row (no thead) as the header', () => {
    const parsed = HtmlTableUtils.parse(
      table('<table><tr><th>A</th><th>B</th></tr><tr><td>1</td><td>2</td></tr></table>'),
    )

    expect(parsed.content[0]).toEqual(['A', 'B'])
    expect(parsed.content).toHaveLength(2)
  })

  it('reads column alignment from style and align attributes', () => {
    const parsed = HtmlTableUtils.parse(
      table(
        '<table><thead><tr><th style="text-align:center">A</th><th align="right">B</th></tr></thead>' +
          '<tbody><tr><td>1</td><td>2</td></tr></tbody></table>',
      ),
    )

    expect(parsed.columnAlignments).toEqual(['center', 'right'])
  })
})
