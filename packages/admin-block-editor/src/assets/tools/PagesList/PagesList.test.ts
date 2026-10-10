import { describe, it, expect } from 'vitest'
import { API } from '@editorjs/editorjs'
import PagesList, { PagesListConfig, PagesListData } from './PagesList'
import { MarkdownUtils } from '../utils/MarkdownUtils'

function stubApi(): API {
  return {
    styles: { block: 'ce-block', input: 'cdx-input', button: 'cdx-button', loader: 'loader' },
    i18n: { t: (key: string) => key },
  } as unknown as API
}

/** The values the format select offers, plus the one it shows as selected. */
function displaySelect(
  display: string,
  displays?: string[],
): { options: string[]; value: string } {
  const tool = new PagesList({
    data: { kw: 'test', display, order: 'publishedAt ↓', max: '9', maxPages: '0' } as PagesListData,
    api: stubApi(),
    readOnly: false,
    config: { preview: '/admin/page/block/1', displays } as PagesListConfig,
  })

  const select = tool.createInputs().querySelector('select')!

  return {
    // The first option is the disabled "format" label, which is not a display.
    options: [...select.options].map((option) => option.value).filter((value) => '' !== value),
    value: select.value,
  }
}

describe('PagesList – the format select', () => {
  it('offers the built-in views when the site declares none', () => {
    expect(displaySelect('list').options).toEqual(['list', 'card', 'horizontalScroll'])
  })

  it('appends the site display variants', () => {
    expect(displaySelect('list', ['smallCard', 'timeline']).options).toEqual([
      'list',
      'card',
      'horizontalScroll',
      'smallCard',
      'timeline',
    ])
  })

  it('does not list a declared variant twice when it is the stored one', () => {
    expect(displaySelect('smallCard', ['smallCard']).options).toEqual([
      'list',
      'card',
      'horizontalScroll',
      'smallCard',
    ])
  })

  /**
   * A block outlives the config that offered its display: a variant the site stopped
   * declaring must stay selected, or merely opening the page would silently rewrite
   * the block to whatever the select falls back to.
   */
  it('keeps an undeclared stored display selectable', () => {
    const { options, value } = displaySelect('legacyVariant')

    expect(options).toContain('legacyVariant')
    expect(value).toBe('legacyVariant')
  })
})

/** The values the order select offers, plus the one it shows as selected. */
function orderSelect(order: string): { options: string[]; value: string } {
  const tool = new PagesList({
    data: { kw: 'test', display: 'list', order, max: '9', maxPages: '0' } as PagesListData,
    api: stubApi(),
    readOnly: false,
    config: { preview: '/admin/page/block/1' } as PagesListConfig,
  })

  const select = tool.createInputs().querySelectorAll('select')[1] as HTMLSelectElement

  return {
    options: [...select.options].map((option) => option.value).filter((value) => '' !== value),
    value: select.value,
  }
}

describe('PagesList – the order select', () => {
  it('offers ordering by the search, alone or ahead of a column', () => {
    const { options } = orderSelect('publishedAt ↓')

    expect(options).toContain('search')
    expect(options).toContain('search, weight ↓, publishedAt ↓')
  })

  /**
   * An order written by hand — a column the list does not offer — used to leave the
   * select empty, so opening the page and saving it rewrote the block's order.
   */
  it('keeps an order it does not offer selectable', () => {
    const { options, value } = orderSelect('prop.eventDate ↑')

    expect(options).toContain('prop.eventDate ↑')
    expect(value).toBe('prop.eventDate ↑')
  })

  it('does not list the stored order twice when it is a known one', () => {
    expect(orderSelect('search').options).toEqual([
      'publishedAt ↓',
      'weight ↓, publishedAt ↓',
      'publishedAt ↑',
      'search',
      'search, weight ↓, publishedAt ↓',
    ])
  })
})

describe('PagesList.importFromMarkdown', () => {
  function importTunes(markdown: string): unknown {
    let tunes: unknown = null
    const editor = {
      blocks: {
        insert: () => ({ id: 'pages-list-id' }),
        update: (_id: string, _data: unknown, blockTunes: unknown) => {
          tunes = blockTunes
        },
      },
    } as unknown as API

    PagesList.importFromMarkdown(editor, markdown)

    return tunes
  }

  it('keeps the tunes of an attribute line when the call passes none', () => {
    const markdown =
      "{#news .bleed}\n{{ pages_list('type:blog', '9', 'publishedAt ↓', 'list') }}"

    expect(importTunes(markdown)).toEqual(
      MarkdownUtils.parseTunesFromMarkdown(markdown).tunes,
    )
    expect(importTunes(markdown)).toHaveProperty('anchor', 'news')
  })

  it('reads the class and anchor written after maxPages', () => {
    expect(
      importTunes(
        "{{ pages_list('type:blog', '9', 'publishedAt ↓', 'list', '0', 'bleed', 'news') }}",
      ),
    ).toEqual({ class: 'bleed', anchor: 'news' })
  })
})
