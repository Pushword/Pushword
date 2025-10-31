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
  window.SimpleJekyllSearch({
    searchInput: document.getElementById('search'),
    resultsContainer: document.getElementById('search-results'),
    json: baseUrl + (baseUrl == '/' ? '' : '/') + 'search.json',
    searchResultTemplate:
      '<a href="{url}" class="block py-2 px-1 m-1 hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded"><span class="block">{title}</span><span class="text-xs font-light block mt-1">pushword.piedweb.com › {slug}</span></a>',
  })
  initHighlight()
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
