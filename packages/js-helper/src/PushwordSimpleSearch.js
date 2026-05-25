/**
 * PushwordSimpleSearch — client-side search over a static JSON index.
 *
 * Vendored from simple-jekyll-search (MIT, Christian Fei) — the upstream package
 * is unmaintained — then folded into a single ES module and adapted to Pushword:
 *
 * - Field-weighted ranking: results are scored by which field matched, so a
 *   `title`/`h1` hit outranks a body-only hit (the upstream returned matches in
 *   raw file order, with every field weighted equally). The weights mirror the
 *   search bundle's `searchable_attributes` order.
 * - Hardened field reading: a non-string value (e.g. the `tags` array) is
 *   normalised instead of throwing `str.trim is not a function`.
 * - `fetch`-based JSON loading instead of the legacy XMLHttpRequest path.
 *
 * The options stay compatible with simple-jekyll-search, plus `searchableAttributes`.
 */

import fuzzysearch from 'fuzzysearch'

/** Fields searched, ordered by descending weight (first = most important). */
const DEFAULT_SEARCHABLE_ATTRIBUTES = ['title', 'h1', 'tags', 'content']

const DEFAULTS = {
  searchInput: null,
  resultsContainer: null,
  json: [],
  success: () => {},
  searchResultTemplate: '<li><a href="{url}" title="{desc}">{title}</a></li>',
  templateMiddleware: () => {},
  sortMiddleware: () => 0,
  noResultsText: 'No results found',
  limit: 10,
  fuzzy: false,
  debounceTime: null,
  exclude: [],
  searchableAttributes: DEFAULT_SEARCHABLE_ATTRIBUTES,
  onSearch: () => {},
}

const literalStrategy = {
  /** All query words must appear in the field (a trailing space matches the phrase literally). */
  matches(str, crit) {
    if (!str) return false
    str = str.toLowerCase()
    const words = crit.endsWith(' ') ? [crit.toLowerCase()] : crit.trim().toLowerCase().split(' ')
    return words.filter((word) => str.indexOf(word) >= 0).length === words.length
  },
}

const fuzzyStrategy = {
  matches(str, crit) {
    if (!str) return false
    return fuzzysearch(crit.toLowerCase(), str.toLowerCase())
  },
}

/** Coerce any indexed value to a searchable string (arrays joined, null → ''). */
function normalize(value) {
  if (value === null || value === undefined) return ''
  if (Array.isArray(value)) return value.join(' ')
  return String(value)
}

function isExcluded(term, excludedTerms) {
  return excludedTerms.some((excluded) => new RegExp(excluded).test(term))
}

/** Weight map from an ordered attribute list: first attribute scores highest. */
function weightsFrom(attributes) {
  const weights = {}
  attributes.forEach((attr, index) => {
    weights[attr] = attributes.length - index
  })
  return weights
}

/**
 * Rank documents against a query. A document scores the summed weight of every
 * searchable field that matches; non-matching documents (score 0) are dropped.
 *
 * @param {Array<Object>} docs
 * @param {string} query
 * @param {Object} [options]
 * @returns {Array<Object>} matching documents, most relevant first
 */
export function searchDocuments(docs, query, options = {}) {
  if (!query) return []

  const opt = { ...DEFAULTS, ...options }
  const strategy = opt.fuzzy ? fuzzyStrategy : literalStrategy
  const weights = weightsFrom(opt.searchableAttributes)

  const scored = []
  for (const doc of docs) {
    let score = 0
    for (const attr of opt.searchableAttributes) {
      const value = normalize(doc[attr])
      if (value === '' || isExcluded(value, opt.exclude)) continue
      if (strategy.matches(value, query)) score += weights[attr]
    }
    if (score > 0) scored.push({ doc, score })
  }

  scored.sort((a, b) => b.score - a.score || opt.sortMiddleware(a.doc, b.doc))

  return scored.slice(0, opt.limit).map((entry) => entry.doc)
}

function compileTemplate(template, middleware, data) {
  return template.replace(/\{(.*?)\}/g, (match, prop) => {
    const value = middleware(prop, data[prop], template)
    if (typeof value !== 'undefined') return value
    return data[prop] || match
  })
}

async function loadJSON(json) {
  if (Array.isArray(json)) return json
  const response = await fetch(json)
  if (!response.ok) throw new Error(`PushwordSimpleSearch — failed to load JSON (${json})`)
  return response.json()
}

function debounce(func, delayMillis) {
  let handle
  return (...args) => {
    if (!delayMillis) return func(...args)
    clearTimeout(handle)
    handle = setTimeout(() => func(...args), delayMillis)
  }
}

/**
 * Wire a search input to a results container, querying a JSON index.
 *
 * @param {Object} options simple-jekyll-search compatible, plus `searchableAttributes`
 * @returns {{ search: (query: string) => void }}
 */
export function pushwordSimpleSearch(options) {
  for (const required of ['searchInput', 'resultsContainer', 'json']) {
    if (typeof options[required] === 'undefined') {
      throw new Error(`PushwordSimpleSearch — missing required option: ${required}`)
    }
  }

  const opt = { ...DEFAULTS, ...options }
  let documents = []

  const render = (results, query) => {
    opt.resultsContainer.innerHTML = ''
    if (results.length === 0) {
      opt.resultsContainer.innerHTML = opt.noResultsText
      return
    }
    opt.resultsContainer.innerHTML = results
      .map((result) => compileTemplate(opt.searchResultTemplate, opt.templateMiddleware, { ...result, query }))
      .join('')
  }

  const search = (query) => {
    if (!query || query.length === 0) return
    render(searchDocuments(documents, query, opt), query)
    opt.onSearch()
  }

  const runSearch = debounce(search, opt.debounceTime)
  opt.searchInput.addEventListener('input', (e) => runSearch(e.target.value))

  const api = { search }

  loadJSON(opt.json).then((json) => {
    documents = json
    opt.success.call(api)
  })

  return api
}

export default pushwordSimpleSearch
