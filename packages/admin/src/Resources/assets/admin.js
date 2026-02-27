import './admin.css'

// HTMX for Ctrl+S auto-save
import htmx from 'htmx.org'
window.htmx = htmx

// Editor modules
import { easyMDEditor } from './admin.easymde-editor'

// Filtering modules
import { filterParentPageFromHost } from './admin.filteringParentPage'
import { filterImageFormField } from './admin.filterImageFormField'

// Compression modules
import { initImageCompressor } from './admin.imageCompressor'

// Multi-upload module
import { initMultiUpload } from './admin.multiUpload'

// Selection modules
import { mediaPicker } from './admin.mediaPicker'
import { inlinePopup } from './admin.inlinePopup'

// Form modules
import { textareaAutoSize, textareaWithoutNewLine } from './admin.textareaHelper'
import { memorizeOpenPanel } from './admin.memorizeOpenPanel'
import { showTitlePixelWidth } from './admin.formHelpers'

// State modules
import { retrieveCurrentPageLocale, retrieveCurrentPageHost } from './admin.pageState'

// Utility modules
import { copyElementText } from './admin.domUtils'

// Auto-save modules
import { initCtrlSAutoSave } from './admin.ctrlSAutoSave'

// Edit lock modules
import { autoInitEditLock } from './admin.editLock'

// Tags modules
import { suggestTags } from './admin.tagsField'

// Polyfills
import 'core-js/stable'

// Global variables
window.domChanging = false
window.copyElementText = copyElementText

/**
 * Initialize all admin interface modules
 */
window.addEventListener('load', function () {
  // Editors
  easyMDEditor()

  // Form helpers
  showTitlePixelWidth()
  showTitlePixelWidth('desc', 150)

  // Panel management
  memorizeOpenPanel()

  // Textarea helpers
  textareaWithoutNewLine()
  textareaAutoSize()

  // Filters
  filterParentPageFromHost()
  filterImageFormField()

  // Image compression before upload
  initImageCompressor()

  // Multi-upload
  initMultiUpload()

  // Selectors
  mediaPicker()
  inlinePopup()
  suggestTags()

  // Page state
  retrieveCurrentPageLocale()
  retrieveCurrentPageHost()

  // Auto-save
  initCtrlSAutoSave()

  // Edit lock
  autoInitEditLock()

  // document.body.addEventListener('htmx:afterSwap', function () {
  //   suggestTags()
  // })
})
