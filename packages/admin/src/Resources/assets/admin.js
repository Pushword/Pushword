import './admin.css'

// HTMX pour la sauvegarde automatique Ctrl+S
import htmx from 'htmx.org'
window.htmx = htmx

// Modules d'édition
import { easyMDEditor } from './admin.easymde-editor'

// Modules de filtrage
import { filterParentPageFromHost } from './admin.filteringParentPage'
import { filterImageFormField } from './admin.filterImageFormField'

// Modules de compression
import { initImageCompressor } from './admin.imageCompressor'

// Modules de sélection
import { mediaPicker } from './admin.mediaPicker'
import { inlinePopup } from './admin.inlinePopup'

// Modules de formulaire
import { textareaAutoSize, textareaWithoutNewLine } from './admin.textareaHelper'
import { memorizeOpenPanel } from './admin.memorizeOpenPanel'
import { showTitlePixelWidth } from './admin.formHelpers'

// Modules d'état
import { retrieveCurrentPageLocale, retrieveCurrentPageHost } from './admin.pageState'

// Modules utilitaires
import { copyElementText } from './admin.domUtils'

// Modules de sauvegarde
import { initCtrlSAutoSave } from './admin.ctrlSAutoSave'

// Modules de verrouillage d'édition
import { autoInitEditLock } from './admin.editLock'

// Modules de tags
import { suggestTags } from './admin.tagsField'

// Polyfills
import 'core-js/stable'

// Variables globales
window.domChanging = false
window.copyElementText = copyElementText

/**
 * Initialise tous les modules de l'interface d'administration
 */
window.addEventListener('load', function () {
  // Éditeurs
  easyMDEditor()

  // Helpers de formulaire
  showTitlePixelWidth()
  showTitlePixelWidth('desc', 150)

  // Gestion des panels
  memorizeOpenPanel()

  // Helpers de textarea
  textareaWithoutNewLine()
  textareaAutoSize()

  // Filtres
  filterParentPageFromHost()
  filterImageFormField()

  // Compression d'image avant upload
  initImageCompressor()

  // Sélecteurs
  mediaPicker()
  inlinePopup()
  suggestTags()

  // État de la page
  retrieveCurrentPageLocale()
  retrieveCurrentPageHost()

  // Sauvegarde automatique
  initCtrlSAutoSave()

  // Verrouillage d'édition
  autoInitEditLock()

  // document.body.addEventListener('htmx:afterSwap', function () {
  //   suggestTags()
  // })
})
