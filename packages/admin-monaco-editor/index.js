/**
 * Different package from admin because generating monaco-editor is very slow
 */
import * as monaco from 'monaco-editor'
import MonacoHelper from './MonacoHelper'

window.monaco = monaco
window.monacoHelper = MonacoHelper

function initEditors() {
  const textareaList = document.querySelectorAll(
    'textarea[data-editor="twig"],textarea[data-editor="yaml"],textarea[data-editor="json"],textarea[data-editor="markdown"]',
  )
  textareaList.forEach((textarea) => MonacoHelper.transformTextareaToMonaco(textarea))
}

// This bundle is injected on demand — only pages that hold a Monaco field pay for
// it — so `load` may already be behind us by the time it runs.
if ('complete' === document.readyState) initEditors()
else window.addEventListener('load', initEditors)
