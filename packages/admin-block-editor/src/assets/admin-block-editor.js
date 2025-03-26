require('./admin.css')

import { editorJs } from './editor.js'
import { editorJsHelper } from './editorJsHelper.js'

window.editorJsHelper = new editorJsHelper()

window.addEventListener('load', function () {
  window.editors = new editorJs().getEditors()

  // Some workaround to get back the not functionning shortcut for editorjs create inline hyperlink
  document.querySelector('div[id$="_mainContent"]').addEventListener('keydown', (event) => {
    if (event.ctrlKey && event.key === 'k') {
      console.log('ctrl+k')
      const el = document.querySelector('[data-item-name="link"] button')
      if (el) el.click()
    }
  })
})
