/**
 * Utilitaires DOM globaux
 */

/**
 * Copie le texte d'un élément dans le presse-papier
 * Utilise l'API Clipboard moderne si disponible, sinon fallback vers execCommand
 * @param {HTMLElement} element - L'élément dont le texte doit être copié
 * @returns {Promise<boolean>} True si la copie a réussi
 */
export async function copyElementText(element) {
  const text = element.innerText

  // Tente d'utiliser l'API Clipboard moderne
  if (navigator.clipboard && window.isSecureContext) {
    try {
      await navigator.clipboard.writeText(text)
      console.debug('[copyElementText] Text copied using Clipboard API')
      return true
    } catch (err) {
      console.warn('[copyElementText] Clipboard API failed, using fallback', err)
    }
  }

  // Fallback pour les navigateurs plus anciens
  try {
    const elem = document.createElement('textarea')
    elem.value = text
    elem.style.position = 'absolute'
    elem.style.left = '-9999px'
    document.body.appendChild(elem)
    elem.select()
    const success = document.execCommand('copy')
    document.body.removeChild(elem)
    console.debug('[copyElementText] Text copied using execCommand')
    return success
  } catch (err) {
    console.error('[copyElementText] Failed to copy text', err)
    return false
  }
}

/**
 * Focus un éditeur EditorJS
 * @param {HTMLElement} editorJsHolder - Le conteneur de l'éditeur EditorJS
 */
export function focusEditorJs(editorJsHolder) {
  const id = editorJsHolder.getAttribute('id')
  if (window.editors && window.editors[id]) {
    window.editors[id].focus()
  }
}
