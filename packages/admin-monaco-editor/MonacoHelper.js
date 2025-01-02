import * as monaco from 'monaco-editor'

export default class MonacoHelper {
  static get defaultSettings() {
    return {
      theme: 'light+', // You can change the theme if needed
      lineNumbers: 'off',
      minimap: { enabled: false },
      scrollBeyondLastLine: false, // Désactiver le défilement au-delà de la dernière ligne
      automaticLayout: true,
      codeLens: false,
      glyphMargin: false,
      renderLineHighlight: 'none',
      renderWhitespace: 'trailing',
      letterSpacing: 0,
      fontLigatures: true,
      formatOnPaste: true,
      wordWrap: 'on',
      guides: { indentation: true },
    }
  }

  /** @param {HTMLElement} textarea   */
  static transformTextareaToMonaco(textarea) {
    const language = textarea.getAttribute('data-editor')

    // manage hidden
    const collapsedElement = textarea.closest('.collapse')
    const isCollapsed = collapsedElement ? !collapsedElement.classList.contains('in') : false
    if (isCollapsed) collapsedElement.classList.remove('collapse')

    const textareaWidth = textarea.offsetWidth
    const textareaHeight = textarea.offsetHeight
    if (isCollapsed) collapsedElement.classList.add('collapse')

    const editDiv = document.createElement('div')
    editDiv.style.position = 'absolute'
    editDiv.style.width = `${textareaWidth}px`
    editDiv.style.height = `${textareaHeight}px`
    // editDiv.className = textarea.className
    textarea.parentNode.insertBefore(editDiv, textarea)

    const editor = monaco.editor.create(editDiv, {
      value: textarea.value,
      language: language,
      ...MonacoHelper.defaultSettings,
    })

    if (textarea.hasAttribute('readonly')) {
      editor.updateOptions({ readOnly: true })
    }

    // Manage textarea resize
    const resizeObserver = new ResizeObserver((entries) => {
      for (let entry of entries) {
        monacoHelperInstance.updateHeight(textarea)
      }
    })
    resizeObserver.observe(textarea)

    const monacoHelperInstance = new MonacoHelper(editor)

    editor.onDidChangeModelContent(() => {
      textarea.value = editor.getValue()
      //monacoHelperInstance.updateHeight(textarea)
    })

    return editor
  }

  /** @param {typeof import('monaco-editor')} editor */
  constructor(editor) {
    this.editor = editor
  }

  autocloseTag() {
    const selfClosingTags = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr']
    const position = this.editor.getPosition()
    const model = this.editor.getModel()
    const textBeforePosition = model.getValueInRange({
      startLineNumber: position.lineNumber,
      startColumn: 1,
      endLineNumber: position.lineNumber,
      endColumn: position.column,
    })

    const match = textBeforePosition.match(/<(\w+)>$/)
    if (match) {
      const tag = match[1]
      if (!selfClosingTags.includes(tag)) {
        const closingTag = `</${tag}>`
        this.editor.executeEdits('', [
          {
            range: new monaco.Range(position.lineNumber, position.column, position.lineNumber, position.column),
            text: closingTag,
            forceMoveMarkers: true,
          },
        ])

        // Posiciona o cursor entre as tags abertas e fechadas
        this.editor.setPosition({
          lineNumber: position.lineNumber,
          column: position.column,
        })
      }
    }
  }

  updateHeight(wrapperOrTextarea, minHeight = 60) {
    const model = this.editor.getModel()
    const lineCount = model.getLineCount()
    const lineHeight = 21 // Hauteur approximative d'une ligne dans Monaco

    let newHeight = Math.max(lineCount * lineHeight + 10, minHeight)
    if (parseInt(wrapperOrTextarea.style.height) !== newHeight) {
      wrapperOrTextarea.style.height = `${newHeight}px`
      this.resizeEditor(wrapperOrTextarea)
    }
  }

  resizeEditor(wrapperOrTextarea) {
    const { clientHeight, clientWidth } = wrapperOrTextarea
    if (this.editor) {
      this.editor.layout({ height: clientHeight, width: clientWidth })
    }
  }
}
