/**
 * Different package from admin because generating monaco-editor is very slow
 */
import * as monaco from 'monaco-editor'
import MonacoHelper from './MonacoHelper'

window.monaco = monaco
window.monacoHelper = MonacoHelper

window.addEventListener('load', function () {
  const textareaList = document.querySelectorAll(
    'textarea[data-editor="twig"],textarea[data-editor="yaml"],textarea[data-editor="json"]',
  )
  textareaList.forEach((textarea) => MonacoHelper.transformTextareaToMonaco(textarea))
})
