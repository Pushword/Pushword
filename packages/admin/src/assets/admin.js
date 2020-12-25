require('./admin.scss');

//global.$ = global.jQuery = require('jquery');

import { easyMDEditor } from './admin.easymde-editor';
import { aceEditor } from './admin.ace-editor';
//import { autosize } from 'autosize/src/autosize.js';
import 'core-js/stable';
import 'regenerator-runtime/runtime';

window.domChanging = false;
window.copyElementText = copyElementText;
//var aceEditorElements = null;

async function onDomChanged() {
  window.domChanging = true;
  //await console.log('domChanged');
  await autoSizeTextarea();
  //if (aceEditorElements !== null && aceEditorElements.renderer !== 'undefined') {    await aceEditor.renderer.updateFull();}
  // todo put all editor in aceEditorElements and for
  window.domChanging = false;
}

window.addEventListener('load', function () {
  // ...
  easyMDEditor();
  showTitlePixelWidth();
  columnSizeManager();
  memorizeOpenPannel();
  onDomChanged();
  textareaWithoutNewLine();
  var aceEditorElements = aceEditor();
  onDomChangedAction();
});

window.onresize = onDomChanged;

function onDomChangedAction() {
  MutationObserver = window.MutationObserver || window.WebKitMutationObserver;

  var observer = new MutationObserver(function (mutations, observer) {
    if (window.domChanging) {
      //console.log('domChanged but not run onDomChanged');
      return;
    } else onDomChanged();
    //console.log(mutations, observer);
  });

  observer.observe(document, {
    attributes: true,
    subtree: true,
  });
}

function autoSizeTextarea() {
  $('.autosize')
    .each(function () {
      $(this).css('height', '');
      $(this).height(this.scrollHeight + 'px');
    })
    .on('input', function () {
      $(this).css('height', '');
      $(this).height(this.scrollHeight + 'px');
    });
}

jQuery.extend(jQuery.expr[':'], {
  focusable: function (el, index, selector) {
    return $(el).is(
      'textarea:not([style*="display: none"]),input,.CodeMirror-lines'
    );
  },
});

function textareaWithoutNewLine() {
  $(document).on('keypress', '.textarea-no-newline', function (e) {
    if ((e.keyCode || e.which) == 13) {
      var $canfocus = $(':focusable');
      var index = $canfocus.index(this) + 1;
      if (index >= $canfocus.length) index = 0;
      $canfocus.eq(index).focus();
      return false;
    }
  });
}
function copyElementText(element) {
  var text = element.innerText;
  var elem = document.createElement('textarea');
  document.body.appendChild(elem);
  elem.value = text;
  elem.select();
  document.execCommand('copy');
  document.body.removeChild(elem);
}

function showTitlePixelWidth() {
  // todo abstract it (showPixelWith(element))
  if (!$('.titleToMeasure').length) return;

  var input = document.querySelector('.titleToMeasure');
  var resultWrapper = document.getElementById('titleWidth');
  function updateTitleWidth() {
    resultWrapper.style =
      'font-size:20px;margin:0;padding:0;border:0;font-weight:400;display:inline-block;font-family:arial,sans-serif;line-height: 1.3;';
    resultWrapper.innerHTML = input.value;
    var titleWidth = resultWrapper.offsetWidth;
    resultWrapper.innerHTML = titleWidth + 'px';
    resultWrapper.style = titleWidth > 560 ? 'color:#B0413E' : 'color:#4F805D';
  }
  updateTitleWidth();
  input.addEventListener('input', updateTitleWidth);
}

function columnSizeManager() {
  if (!$('.expandColumnFields').length) return;
  $('.expandColumnFields').on('click', function () {
    $('.columnFields').removeClass('col-md-3').addClass('col-md-6');
    $('.mainFields').removeClass('col-md-9').addClass('col-md-6');
  });
  $('.mainFields').on('click', function () {
    $('.columnFields').removeClass('col-md-6').addClass('col-md-3');
    $('.mainFields').removeClass('col-md-6').addClass('col-md-9');
  });
}

function memorizeOpenPannel() {
  if (!$('.collapse').length) return;

  $('.collapse').on('shown.bs.collapse', function () {
    var active = $(this).attr('id');
    var panels =
      localStorage.panels === undefined
        ? new Array()
        : JSON.parse(localStorage.panels);
    if ($.inArray(active, panels) == -1) panels.push(active);
    localStorage.panels = JSON.stringify(panels);
  });

  $('.collapse').on('hidden.bs.collapse', function () {
    var active = $(this).attr('id');
    var panels =
      localStorage.panels === undefined
        ? new Array()
        : JSON.parse(localStorage.panels);
    var elementIndex = $.inArray(active, panels);
    if (elementIndex !== -1) {
      panels.splice(elementIndex, 1);
    }
    localStorage.panels = JSON.stringify(panels);

    var panels =
      localStorage.panels === undefined
        ? new Array()
        : JSON.parse(localStorage.panels);
    for (var i in panels) {
      if ($('#' + panels[i]).hasClass('collapse')) {
        $('#' + panels[i]).collapse('show');
      }
    }
  });
}
