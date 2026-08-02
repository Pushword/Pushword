import './admin.css'

// HTMX for Ctrl+S auto-save
import htmx from 'htmx.org'
import { installHtmxCompat } from './admin.htmxCompat'
window.htmx = htmx
installHtmxCompat(htmx)

// Editor modules
import { easyMDEditor } from './admin.easymde-editor'

// Filtering modules
import { filterParentPageFromHost } from './admin.filteringParentPage'
import { filterImageFormField } from './admin.filterImageFormField'

// Compression modules
import { initImageCompressor } from './admin.imageCompressor'

// Multi-upload module
import { initMultiUpload } from './admin.multiUpload'

// Media list inline-edit module
import { initMediaTableEdit } from './admin.mediaInlineEdit'

// Media license fieldset module
import { initMediaLicense } from './admin.mediaLicense'

// Selection modules
import { mediaPicker } from './admin.mediaPicker'
import { inlinePopup } from './admin.inlinePopup'

// Form modules
import { textareaAutoSize, textareaWithoutNewLine } from './admin.textareaHelper'
import { memorizeOpenPanel } from './admin.memorizeOpenPanel'
import { revealInvalidField } from './admin.revealInvalidField'
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

// Sidebar modules
import { submenuFilter } from './admin.submenuFilter'

// Global variables
window.domChanging = false
window.copyElementText = copyElementText

// Prevent EasyAdmin clickable-row navigation when clicking on contenteditable elements.
// EasyAdmin's isInteractiveElement() doesn't recognise [contenteditable], so we tag them
// with a data attribute it DOES check for: [data-bs-toggle].
function markContentEditableElements() {
  document.querySelectorAll('[contenteditable="true"]:not([data-bs-toggle])').forEach(function (el) {
    el.setAttribute('data-bs-toggle', 'pw-inline')
  })
}
document.addEventListener('DOMContentLoaded', markContentEditableElements)
document.addEventListener('turbo:render', markContentEditableElements)
// On document, not body: when a fragment swaps its own trigger away, htmx 4
// dispatches after:swap directly on document (detached-source fallback).
document.addEventListener('htmx:after:swap', markContentEditableElements)

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
  revealInvalidField()

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

  // Media list inline editing (?view=table)
  initMediaTableEdit()

  // Media license fieldset buttons
  initMediaLicense()

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

  // Sidebar submenu filter
  submenuFilter()

  // document.addEventListener('htmx:after:swap', function () {
  //   suggestTags()
  // })
})
