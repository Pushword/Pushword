//require("fslightbox");
import hljs from 'highlight.js'

//import SimpleJekyllSearch from 'simple-jekyll-search'
import 'simple-jekyll-search/dest/simple-jekyll-search.min.js'

import {
  uncloakLinks,
  readableEmail,
  convertImageLinkToWebPLink,
  replaceOn,
  liveBlock,
} from '@pushword/js-helper/src/helpers.js'

function onPageLoaded() {
  // eslint-disable-next-line no-undef
  const baseUrl = base || ''
  onDomChanged()
  //new FsLightbox();
  const searchInput = document.getElementById('search')
  const resultsBox = document.getElementById('search-results')
  if (searchInput && resultsBox) {
    window.SimpleJekyllSearch({
      searchInput,
      resultsContainer: resultsBox,
      json: baseUrl + (baseUrl == '/' ? '' : '/') + 'search.json',
      searchResultTemplate:
        '<a role="option" href="{url}" class="block py-2 px-3 text-sm text-stone-700 hover:bg-stone-100 dark:text-stone-300 dark:hover:bg-stone-800 aria-selected:bg-stone-100 dark:aria-selected:bg-stone-800 aria-selected:text-stone-900 dark:aria-selected:text-stone-100"><span class="block font-medium">{title}</span><span class="block mt-0.5 text-xs text-stone-500 dark:text-stone-400">{slug}</span></a>',
      noResultsText: '<p class="px-3 py-2 text-sm text-stone-500 dark:text-stone-400">No results</p>',
    })
    initSearchKeyboardNav(searchInput, resultsBox)
  }
  initHighlight()
}

function initSearchKeyboardNav(input, box) {
  const items = () => Array.from(box.querySelectorAll('[role="option"]'))
  const setActive = (i) => {
    const list = items()
    list.forEach((el, idx) => {
      const active = idx === i
      el.setAttribute('aria-selected', active ? 'true' : 'false')
      if (active) {
        input.setAttribute('aria-activedescendant', el.id || (el.id = 'sr-' + idx))
        el.scrollIntoView({ block: 'nearest' })
      }
    })
  }
  const show = () => {
    const has = items().length > 0
    box.classList.toggle('hidden', !has)
    input.setAttribute('aria-expanded', has ? 'true' : 'false')
  }
  const hide = () => {
    box.classList.add('hidden')
    input.setAttribute('aria-expanded', 'false')
    input.removeAttribute('aria-activedescendant')
  }
  new MutationObserver(show).observe(box, { childList: true, subtree: true })
  input.addEventListener('focus', show)
  input.addEventListener('input', () => {
    if (!input.value) hide()
  })
  document.addEventListener('click', (e) => {
    if (!box.contains(e.target) && e.target !== input) hide()
  })
  input.addEventListener('keydown', (e) => {
    const list = items()
    if (!list.length) return
    const current = list.findIndex((el) => el.getAttribute('aria-selected') === 'true')
    if (e.key === 'ArrowDown') {
      e.preventDefault()
      setActive((current + 1) % list.length)
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setActive((current - 1 + list.length) % list.length)
    } else if (e.key === 'Enter') {
      if (current >= 0) {
        e.preventDefault()
        list[current].click()
      }
    } else if (e.key === 'Escape') {
      hide()
      input.blur()
    }
  })
}

function onDomChanged() {
  liveBlock()
  convertImageLinkToWebPLink()
  uncloakLinks()
  readableEmail('.cea')
  replaceOn()
  //refreshFsLightbox();
}

document.addEventListener('DOMContentLoaded', onPageLoaded())

document.addEventListener('DOMChanged', onDomChanged)

function initHighlight() {
  document.querySelectorAll('pre code').forEach((block) => {
    hljs.highlightBlock(block)
  })
}
