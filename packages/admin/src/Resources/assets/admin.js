require('./admin.scss')

// used for ctrl-s
import 'htmx.org'
window.htmx = require('htmx.org')

//global.$ = global.jQuery = require('jquery');

import { easyMDEditor } from './admin.easymde-editor'
import { aceEditor } from './admin.ace-editor'
import { filterParentPageFromHost } from './admin.filteringParentPage'
import { filterImageFormField } from './admin.filterImageFormField'
//import { autosize } from 'autosize/src/autosize.js';
import 'core-js/stable'
import 'regenerator-runtime/runtime'

window.aceEditor = aceEditor
window.domChanging = false
window.copyElementText = copyElementText
//var aceEditorElements = null;

async function onDomChanged() {
  window.domChanging = true
  //await console.log('domChanged');
  await autoSizeTextarea()
  //if (aceEditorElements !== null && aceEditorElements.renderer !== 'undefined') {    await aceEditor.renderer.updateFull();}
  // todo put all editor in aceEditorElements and for
  window.domChanging = false
}

window.addEventListener('load', function () {
  // ...
  easyMDEditor()
  showTitlePixelWidth()
  showTitlePixelWidth('desc', 150)
  memorizeOpenPannel()
  onDomChanged()
  textareaWithoutNewLine()
  var aceEditorElements = window.aceEditor()
  onDomChangedAction()
  removePreviewBtn()
  filterParentPageFromHost()
  filterImageFormField()
  suggestTags()
})

window.onresize = onDomChanged

function onDomChangedAction() {
  MutationObserver = window.MutationObserver || window.WebKitMutationObserver

  var observer = new MutationObserver(function (mutations, observer) {
    if (window.domChanging) {
      //console.log('domChanged but not run onDomChanged');
      return
    } else onDomChanged()
    //console.log(mutations, observer);
  })

  observer.observe(document, {
    attributes: true,
    subtree: true,
  })
}

function autoSizeTextarea() {
  $('.autosize')
    .each(function () {
      $(this).css('height', '')
      $(this).height(this.scrollHeight + 'px')
    })
    .on('input', function () {
      $(this).css('height', '')
      $(this).height(this.scrollHeight + 'px')
    })
}

jQuery.extend(jQuery.expr[':'], {
  focusable: function (el, index, selector) {
    return $(el).is('textarea:not([style*="display: none"]),input,.CodeMirror-lines')
  },
})

function textareaWithoutNewLine() {
  $(document).on('keypress', '.textarea-no-newline', function (e) {
    if ((e.keyCode || e.which) == 13) {
      var $canfocus = $(':focusable:visible,.editorjs-holder')
      var index = $canfocus.index(this) + 1
      if (index >= $canfocus.length) index = 0
      $canfocus.eq(index).attr('class') == 'editorjs-holder' ? focusEditorJs($canfocus.eq(index)) : $canfocus.eq(index).focus()
      return false
    }
  })
}
function focusEditorJs(editorJsHolder) {
  const id = editorJsHolder.attr('id')
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
  // todo abstract it (showPixelWith(element))
  if (!$('.' + toMeasure + 'ToMeasure').length) return

  var input = document.querySelector('.' + toMeasure + 'ToMeasure')
  var resultWrapper = document.getElementById('' + toMeasure + 'Width')
  function updateTitleWidth() {
    resultWrapper.style = 'font-size:20px;margin:0;padding:0;border:0;font-weight:400;display:inline-block;font-family:arial,sans-serif;line-height: 1.3;'
    resultWrapper.innerHTML = input.value
    //var titleWidth = resultWrapper.offsetWidth;
    //resultWrapper.innerHTML = titleWidth + "px";
    var titleLenght = input.value.length
    resultWrapper.innerHTML = titleLenght
    //resultWrapper.style = titleWidth > 560 ? "color:#B0413E" : "color:#4F805D";
    resultWrapper.style = titleLenght > maxLenght ? 'color:#B0413E' : 'color:#4F805D'
  }
  updateTitleWidth()
  input.addEventListener('input', updateTitleWidth)
}

function columnSizeManager() {
  if (!$('.expandColumnFields').length) return
  $('.expandColumnFields').on('click', function () {
    if (!$('.columnFields').hasClass('w-0')) {
      $('.columnFields').removeClass('col-md-3').addClass('col-md-6')
      $('.mainFields').removeClass('col-md-9').addClass('col-md-6')
    }
  })
  $('.mainFields').on('click', function () {
    if (!$('.columnFields').hasClass('w-0')) {
      $('.columnFields').removeClass('col-md-6').addClass('col-md-3')
      $('.mainFields').removeClass('col-md-6').addClass('col-md-9')
    }
  })
}

function memorizeOpenPannel() {
  if (!$('.collapse').length) return

  $('.collapse').on('shown.bs.collapse', function () {
    var active = $(this).attr('id')
    var panels = localStorage.panels === 'undefined' || localStorage.panels === undefined ? new Array() : JSON.parse(localStorage.panels)
    if ($.inArray(active, panels) == -1) panels.push(active)
    localStorage.panels = JSON.stringify(panels)

    $("[href='#" + active + "'] .fa-plus")
      .removeClass('fa-plus')
      .addClass('fa-minus')
  })

  $('.collapse').on('hidden.bs.collapse', function () {
    var active = $(this).attr('id')
    var panels = localStorage.panels === 'undefined' || localStorage.panels === undefined ? new Array() : JSON.parse(localStorage.panels)
    var elementIndex = $.inArray(active, panels)
    if (elementIndex !== -1) {
      panels.splice(elementIndex, 1)
    }
    localStorage.panels = JSON.stringify(panels)

    $("[href='#" + active + "'] .fa-minus")
      .removeClass('fa-minus')
      .addClass('fa-plus')
  })

  function onInit() {
    var panels = localStorage.panels === 'undefined' || localStorage.panels === undefined ? new Array() : JSON.parse(localStorage.panels)
    for (var i in panels) {
      if ($('#' + panels[i]).hasClass('collapse')) {
        $('#' + panels[i]).collapse('show')
        $("[href='#" + panels[i] + "'] .fa-plus")
          .removeClass('fa-plus')
          .addClass('fa-minus')
      }
    }
  }

  onInit()
  onErrorOpenPanel()

  function onErrorOpenPanel() {
    document.querySelectorAll('.sonata-ba-field-error-messages').forEach(function (element) {
      var panel = element.closest('.collapse')
      if (panel) {
        $(panel).collapse('show')
      }
    })
  }
}

function removePreviewBtn() {
  if (!document.querySelector('.persist-preview')) return
  document.querySelector('.persist-preview').remove()
}

import { Suggest } from './suggest.js'

function suggestTags() {
  document.querySelectorAll('[data-tags]').forEach(function (tagsInput) {
    const list = JSON.parse(tagsInput.getAttribute('data-tags'))
    const suggester = document.querySelector('[data-tags]').parentElement.querySelector('.textSuggester')
    const options = { highlight: true, dispMax: 10, dispAllKey: true, delim: tagsInput.getAttribute('data-delimiter') ?? ' ' }
    if (tagsInput.getAttribute('data-search-results-hook')) options.hookSearchResults = tagsInput.getAttribute('data-search-results-hook')
    if (list && suggester) new Suggest.LocalMulti(tagsInput, suggester, list, options)
  })
}

/**
 *
 * @param {string} inputValue
 * @param {string} currentSearch
 * @param {Array} searchResults
 */
window.suggestSearchHookForPageTags = function (Suggest, inputValue, currentSearch, searchResults) {
  Suggest.candidateList = Suggest.candidateList.filter((item) => item !== 'AND' && item !== 'OR')

  const search = inputValue.substring(0, inputValue.length - Suggest.getInputText().length)
  if (search.endsWith(' OR ') || search.endsWith(' AND ')) return searchResults
  if (inputValue !== '' && currentSearch !== inputValue && !search.endsWith(' AND ') && !search.endsWith(' OR ')) {
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
