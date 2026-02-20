import { Suggest } from './suggest.js'

/**
 * Custom hook for page tag suggestions
 * Filters logical operators (AND/OR) and adds them to suggestions
 * @param {Suggest} Suggest - Suggester instance
 * @param {string} inputValue - Current input value
 * @param {string} currentSearch - Current search
 * @param {Array} searchResults - Search results
 * @returns {Array} Filtered results with logical operators if needed
 */
export function suggestSearchHookForPageTags(
  Suggest,
  inputValue,
  currentSearch,
  searchResults,
) {
  Suggest.candidateList = Suggest.candidateList.filter(
    (item) => item !== 'AND' && item !== 'OR',
  )

  const search = inputValue.substring(
    0,
    inputValue.length - Suggest.getInputText().length,
  )
  if (search.endsWith(' OR ') || search.endsWith(' AND ')) return searchResults

  if (
    inputValue !== '' &&
    currentSearch !== inputValue &&
    !search.endsWith(' AND ') &&
    !search.endsWith(' OR ')
  ) {
    if (inputValue.includes(' AND ')) {
      Suggest.suggestIndexList = [0]
      Suggest.candidateList = ['AND'].concat(Suggest.candidateList)
      return ['AND']
    }
    if (inputValue.includes(' OR ')) {
      Suggest.suggestIndexList = [0]
      Suggest.candidateList = ['OR'].concat(Suggest.candidateList)
      return ['OR']
    }
    Suggest.suggestIndexList = [0, 1]
    Suggest.candidateList = ['AND', 'OR'].concat(Suggest.candidateList)
    return ['AND', 'OR']
  }
  return searchResults
}

/**
 * Initialize tag suggestion fields
 */
export function suggestTags() {
  document.querySelectorAll('[data-tags]').forEach(function (tagsInput) {
    // Skip if already initialized
    if (tagsInput.dataset.suggestInitialized) return

    const list = JSON.parse(tagsInput.getAttribute('data-tags'))
    const suggester = tagsInput.parentElement?.querySelector('.textSuggester')
    const options = {
      highlight: true,
      dispMax: 10,
      dispAllKey: true,
      delim: tagsInput.getAttribute('data-delimiter') ?? ' ',
    }
    if (tagsInput.getAttribute('data-search-results-hook'))
      options.hookSearchResults = tagsInput.getAttribute('data-search-results-hook')
    if (list && suggester) {
      new Suggest.LocalMulti(tagsInput, suggester, list, options)
      tagsInput.dataset.suggestInitialized = 'true'
    }
  })
}

// Expose function globally for compatibility
window.suggestSearchHookForPageTags = suggestSearchHookForPageTags
