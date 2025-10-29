import './admin.scss'

// used for ctrl-s
import htmx from 'htmx.org'
window.htmx = htmx

import { easyMDEditor } from './admin.easymde-editor'
import { filterParentPageFromHost } from './admin.filteringParentPage'
import { filterImageFormField } from './admin.filterImageFormField'
import { memorizeOpenPanel } from './admin.memorizeOpenPanel'
//import { autosize } from 'autosize/src/autosize.js';
import 'core-js/stable'
import 'regenerator-runtime/runtime'

window.domChanging = false
window.copyElementText = copyElementText

window.addEventListener('load', function () {
  // ...
  easyMDEditor()
  showTitlePixelWidth()
  showTitlePixelWidth('desc', 150)
  memorizeOpenPanel()
  textareaWithoutNewLine()
  autoSizeTextarea()
  removePreviewBtn()
  filterParentPageFromHost()
  filterImageFormField()
  suggestTags()
  setBgImageUrlForMosaic()
  retrieveCurrentPageLocale()
  retrieveCurrentPageHost()
})

function retrieveCurrentPageHost() {
  const input = document.querySelector('input[id$="_host"]')
  if (!input) return

  window.pageHost = input.value
}

function retrieveCurrentPageLocale() {
  const input = document.querySelector('input[id$="_locale"]')
  if (!input) return

  window.pageLocale = input.value

  input.onchange = () => {
    window.pageLocale = input.value
    console.log('Locale updated to:', window.pageLocale)
  }
}

function setBgImageUrlForMosaic() {
  const imageElementList = document.querySelectorAll(
    'table.sonata-ba-list td .mosaic-inner-box img',
  )
  for (const image of imageElementList) {
    const imageUrl = image.getAttribute('src')
    image.style.setProperty(
      'height',
      image.closest('.mosaic-inner-box').clientHeight + 'px',
    )
    const imageContainer = image.parentElement.parentElement

    // Set the CSS variable on the container
    imageContainer.style.setProperty('--bg-image-url', `url('${imageUrl}')`)
  }
}
function autoSizeTextarea() {
  document.querySelectorAll('.autosize').forEach(function (element) {
    const adjustHeight = (el) => {
      el.style.height = ''
      el.style.height = el.scrollHeight + 'px'
    }

    adjustHeight(element)
    element.addEventListener('input', function () {
      adjustHeight(this)
    })
  })
}

function textareaWithoutNewLine() {
  document.addEventListener('keypress', function (e) {
    if (
      e.target.classList.contains('textarea-no-newline') &&
      (e.keyCode || e.which) == 13
    ) {
      const focusableElements = document.querySelectorAll(
        'textarea:not([style*="display: none"]),input,.CodeMirror-lines',
      )
      const elementArray = Array.from(focusableElements)
      let index = elementArray.indexOf(e.target) + 1
      if (index >= elementArray.length) index = 0
      const nextElement = elementArray[index]
      if (nextElement.classList.contains('editorjs-holder')) {
        focusEditorJs(nextElement)
      } else {
        nextElement.focus()
      }
      return false
    }
  })
}

function focusEditorJs(editorJsHolder) {
  const id = editorJsHolder.getAttribute('id')
  window.editors[id].focus()
}

function copyElementText(element) {
  var text = element.innerText
  var elem = document.createElement('textarea')
  document.body.appendChild(elem)
  elem.value = text
  elem.select()
  document.execCommand('copy')
  document.body.removeChild(elem)
}

function showTitlePixelWidth(toMeasure = 'title', maxLenght = 70) {
  const input = document.querySelector('.' + toMeasure + 'ToMeasure')
  if (!input) return

  const resultWrapper = document.getElementById(toMeasure + 'Width')
  function updateTitleWidth() {
    resultWrapper.style =
      'font-size:20px;margin:0;padding:0;border:0;font-weight:400;display:inline-block;font-family:arial,sans-serif;line-height: 1.3;'
    resultWrapper.innerHTML = input.value
    const titleLenght = input.value.length
    resultWrapper.innerHTML = titleLenght
    resultWrapper.style = titleLenght > maxLenght ? 'color:#B0413E' : 'color:#4F805D'
  }
  updateTitleWidth()
  input.addEventListener('input', updateTitleWidth)
}

function columnSizeManager() {
  const expandColumnFields = document.querySelector('.expandColumnFields')
  const columnFields = document.querySelector('.columnFields')
  const mainFields = document.querySelector('.mainFields')

  if (!expandColumnFields || !columnFields || !mainFields) return

  expandColumnFields.addEventListener('click', function () {
    if (!columnFields.classList.contains('w-0')) {
      columnFields.classList.remove('col-md-3')
      columnFields.classList.add('col-md-6')
      mainFields.classList.remove('col-md-9')
      mainFields.classList.add('col-md-6')
    }
  })

  mainFields.addEventListener('click', function () {
    if (!columnFields.classList.contains('w-0')) {
      columnFields.classList.remove('col-md-6')
      columnFields.classList.add('col-md-3')
      mainFields.classList.remove('col-md-6')
      mainFields.classList.add('col-md-9')
    }
  })
}

function removePreviewBtn() {
  if (!document.querySelector('.persist-preview')) return
  document.querySelector('.persist-preview').remove()
}

import { Suggest } from './suggest.js'

function suggestTags() {
  document.querySelectorAll('[data-tags]').forEach(function (tagsInput) {
    const list = JSON.parse(tagsInput.getAttribute('data-tags'))
    const suggester = document
      .querySelector('[data-tags]')
      .parentElement.querySelector('.textSuggester')
    const options = {
      highlight: true,
      dispMax: 10,
      dispAllKey: true,
      delim: tagsInput.getAttribute('data-delimiter') ?? ' ',
    }
    if (tagsInput.getAttribute('data-search-results-hook'))
      options.hookSearchResults = tagsInput.getAttribute('data-search-results-hook')
    if (list && suggester) new Suggest.LocalMulti(tagsInput, suggester, list, options)
  })
}

/**
 *
 * @param {string} inputValue
 * @param {string} currentSearch
 * @param {Array} searchResults
 */
window.suggestSearchHookForPageTags = function (
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
