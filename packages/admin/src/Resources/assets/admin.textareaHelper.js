import { focusEditorJs } from './admin.domUtils'

/**
 * Ajuste automatiquement la hauteur des textareas avec la classe .autosize
 */
export function textareaAutoSize() {
  document.querySelectorAll('.autosize').forEach(function (element) {
    const adjustHeight = (el) => {
      el.style.blockSize = 'auto '
      el.style.height = 'unset '
      el.style.height = el.scrollHeight + 'px '
    }

    adjustHeight(element)
    element.addEventListener('input', function () {
      adjustHeight(this)
    })
  })
}

/**
 * Empêche les retours à la ligne dans les textareas avec la classe .textarea-no-newline
 * Redirige vers l'élément suivant au lieu d'insérer un retour à la ligne
 */
export function textareaWithoutNewLine() {
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
