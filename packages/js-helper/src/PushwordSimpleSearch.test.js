import { describe, it, expect, vi } from 'vitest'
import { searchDocuments, pushwordSimpleSearch } from './PushwordSimpleSearch.js'

const docs = [
  { title: 'Authentication', h1: 'Authentication', tags: [], content: 'configure the block editor for login' },
  { title: 'Editor Hidden Power', h1: 'Editor Hidden Power', tags: ['editor'], content: 'cheatsheet' },
  { title: 'Snippets', h1: 'Snippets', tags: [], content: 'reusable fragments' },
]

describe('searchDocuments', () => {
  it('ranks a title/h1 match above a body-only match', () => {
    const results = searchDocuments(docs, 'editor')
    // "Authentication" only mentions "editor" in its body; the Editor page
    // matches title + h1 + tags and must come first.
    expect(results.map((d) => d.title)).toEqual(['Editor Hidden Power', 'Authentication'])
  })

  it('does not throw on a non-string field (array tags)', () => {
    expect(() => searchDocuments(docs, 'editor')).not.toThrow()
  })

  it('returns nothing for an empty query', () => {
    expect(searchDocuments(docs, '')).toEqual([])
  })

  it('drops documents with no matching field', () => {
    expect(searchDocuments(docs, 'snippets').map((d) => d.title)).toEqual(['Snippets'])
  })

  it('requires every query word to match a single field', () => {
    // "block" + "editor" both appear only in Authentication's content.
    expect(searchDocuments(docs, 'block editor').map((d) => d.title)).toEqual(['Authentication'])
  })

  it('honours the limit option', () => {
    expect(searchDocuments(docs, 'editor', { limit: 1 })).toHaveLength(1)
  })

  it('supports fuzzy matching', () => {
    expect(searchDocuments(docs, 'edtr', { fuzzy: true }).map((d) => d.title)).toContain('Editor Hidden Power')
  })

  it('skips fields whose value matches an exclude pattern', () => {
    // Excluding the body text leaves Authentication with no matching field.
    const results = searchDocuments(docs, 'editor', { exclude: ['block editor'] })
    expect(results.map((d) => d.title)).toEqual(['Editor Hidden Power'])
  })

  it('weights only the configured searchableAttributes', () => {
    // Restricting to content makes the body-only match the sole result.
    const results = searchDocuments(docs, 'editor', { searchableAttributes: ['content'] })
    expect(results.map((d) => d.title)).toEqual(['Authentication'])
  })

  it('breaks score ties with sortMiddleware', () => {
    const tied = [
      { title: 'editor B', h1: '', tags: [], content: '' },
      { title: 'editor A', h1: '', tags: [], content: '' },
    ]
    const byTitle = (a, b) => a.title.localeCompare(b.title)
    expect(searchDocuments(tied, 'editor', { sortMiddleware: byTitle }).map((d) => d.title)).toEqual([
      'editor A',
      'editor B',
    ])
  })

  it('matches a multi-word phrase literally when the query ends with a space', () => {
    const phrased = [
      { title: 'block editor power', h1: '', tags: [], content: '' },
      { title: 'editor block list', h1: '', tags: [], content: '' },
    ]
    // Trailing space → "block editor" must appear as a contiguous phrase.
    expect(searchDocuments(phrased, 'block editor ').map((d) => d.title)).toEqual(['block editor power'])
  })

  it('normalises a numeric field to a searchable string', () => {
    const numeric = [{ title: 'Year', h1: '', tags: [], content: 2026 }]
    expect(searchDocuments(numeric, '2026', { searchableAttributes: ['content'] })).toHaveLength(1)
  })
})

describe('pushwordSimpleSearch', () => {
  it('renders ranked results into the container from a JSON array', async () => {
    const input = document.createElement('input')
    const container = document.createElement('div')

    let ready
    const loaded = new Promise((resolve) => {
      ready = resolve
    })

    pushwordSimpleSearch({
      searchInput: input,
      resultsContainer: container,
      json: docs,
      searchResultTemplate: '<a href="#">{title}</a>',
      success() {
        ready()
      },
    })
    await loaded

    input.value = 'editor'
    input.dispatchEvent(new window.Event('input'))

    expect(container.innerHTML).toBe('<a href="#">Editor Hidden Power</a><a href="#">Authentication</a>')
  })

  it('shows noResultsText when nothing matches', async () => {
    const input = document.createElement('input')
    const container = document.createElement('div')

    let ready
    const loaded = new Promise((resolve) => {
      ready = resolve
    })

    pushwordSimpleSearch({
      searchInput: input,
      resultsContainer: container,
      json: docs,
      noResultsText: 'Nope',
      success() {
        ready()
      },
    })
    await loaded

    input.value = 'zzzznomatch'
    input.dispatchEvent(new window.Event('input'))

    expect(container.innerHTML).toBe('Nope')
  })

  it('throws when a required option is missing', () => {
    expect(() => pushwordSimpleSearch({ searchInput: {}, resultsContainer: {} })).toThrow(/missing required option: json/)
  })

  it('loads the index from a URL via fetch (the production path)', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => docs })
    vi.stubGlobal('fetch', fetchMock)

    const input = document.createElement('input')
    const container = document.createElement('div')

    let ready
    const loaded = new Promise((resolve) => {
      ready = resolve
    })

    pushwordSimpleSearch({
      searchInput: input,
      resultsContainer: container,
      json: '/search.json',
      searchResultTemplate: '<a>{title}</a>',
      success() {
        ready()
      },
    })
    await loaded

    expect(fetchMock).toHaveBeenCalledWith('/search.json')

    input.value = 'editor'
    input.dispatchEvent(new window.Event('input'))
    expect(container.innerHTML).toBe('<a>Editor Hidden Power</a><a>Authentication</a>')

    vi.unstubAllGlobals()
  })

  it('passes each placeholder through templateMiddleware', async () => {
    const input = document.createElement('input')
    const container = document.createElement('div')

    let ready
    const loaded = new Promise((resolve) => {
      ready = resolve
    })

    pushwordSimpleSearch({
      searchInput: input,
      resultsContainer: container,
      json: [{ title: 'Editor', h1: '', tags: [], content: '' }],
      searchResultTemplate: '<a>{title}</a>',
      templateMiddleware: (prop, value) => (prop === 'title' ? value.toUpperCase() : undefined),
      success() {
        ready()
      },
    })
    await loaded

    input.value = 'editor'
    input.dispatchEvent(new window.Event('input'))
    expect(container.innerHTML).toBe('<a>EDITOR</a>')
  })
})
