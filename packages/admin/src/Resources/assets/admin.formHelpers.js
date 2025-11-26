/**
 * Helpers pour les formulaires
 */

import { focusEditorJs } from './admin.domUtils'

/**
 * Affiche la largeur en pixels d'un champ (title, desc, etc.)
 * @param {string} toMeasure - Le nom du champ à mesurer (défaut: 'title')
 * @param {number} maxLength - Longueur maximale autorisée (défaut: 70)
 */
export function showTitlePixelWidth(toMeasure = 'title', maxLength = 70) {
  const input = document.querySelector('.' + toMeasure + 'ToMeasure')
  if (!input) {
    console.debug(`[showTitlePixelWidth] Element .${toMeasure}ToMeasure not found`)
    return
  }

  const resultWrapper = document.getElementById(toMeasure + 'Width')
  if (!resultWrapper) {
    console.debug(`[showTitlePixelWidth] Element #${toMeasure}Width not found`)
    return
  }

  function updateTitleWidth() {
    resultWrapper.style =
      'font-size:20px;margin:0;padding:0;border:0;font-weight:400;display:inline-block;font-family:arial,sans-serif;line-height: 1.3;'
    resultWrapper.innerHTML = input.value
    const titleLength = input.value.length
    resultWrapper.innerHTML = titleLength
    resultWrapper.style = titleLength > maxLength ? 'color:#B0413E' : 'color:#4F805D'
  }

  updateTitleWidth()
  input.addEventListener('input', updateTitleWidth)
}

/**
 * Gère le redimensionnement des colonnes dans le formulaire
 */
export function columnSizeManager() {
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

/**
 * Supprime le bouton de prévisualisation persistant
 */
export function removePreviewBtn() {
  const previewBtn = document.querySelector('.persist-preview')
  if (previewBtn) {
    previewBtn.remove()
  }
}
