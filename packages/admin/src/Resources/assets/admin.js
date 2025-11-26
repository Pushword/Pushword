import './admin.css'

// HTMX pour la sauvegarde automatique Ctrl+S
import htmx from 'htmx.org'
window.htmx = htmx

// Modules d'édition
import { easyMDEditor } from './admin.easymde-editor'

// Modules de filtrage
import { filterParentPageFromHost } from './admin.filteringParentPage'
import { filterImageFormField } from './admin.filterImageFormField'

// Modules de sélection
import { mediaPicker } from './admin.mediaPicker'

// Modules de formulaire
import { textareaAutoSize, textareaWithoutNewLine } from './admin.textareaHelper'
import { memorizeOpenPanel } from './admin.memorizeOpenPanel'
import {
  showTitlePixelWidth,
  removePreviewBtn,
  columnSizeManager,
} from './admin.formHelpers'

// Modules d'état
import { retrieveCurrentPageLocale, retrieveCurrentPageHost } from './admin.pageState'

// Modules utilitaires
import { copyElementText } from './admin.domUtils'

// Modules de sauvegarde
import { initCtrlSAutoSave } from './admin.ctrlSAutoSave'

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
  removePreviewBtn()
  columnSizeManager()

  // Gestion des panels
  memorizeOpenPanel()

  // Helpers de textarea
  textareaWithoutNewLine()
  textareaAutoSize()

  // Filtres
  filterParentPageFromHost()
  filterImageFormField()

  // Sélecteurs
  mediaPicker()
  suggestTags()

  // État de la page
  retrieveCurrentPageLocale()
  retrieveCurrentPageHost()

  // Sauvegarde automatique
  initCtrlSAutoSave()
})
