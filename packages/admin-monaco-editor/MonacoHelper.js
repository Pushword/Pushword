import * as monaco from 'monaco-editor'

import { installMarkdownChrome } from './markdown/MarkdownToolbar'

export default class MonacoHelper {
  static defaultSettings = {
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
    folding: false,
    showFoldingControls: 'never',
    wordWrap: 'on',
    guides: { indentation: true },
    tabSize: 2,
    insertSpaces: true,
    detectIndentation: true,
  }

  /**
   * Prose, not code: no completion popups, and no unicode warnings over the
   * curly quotes and non-breaking spaces French copy is full of.
   */
  static markdownSettings = {
    fontSize: 15,
    lineHeight: 24,
    padding: { top: 8, bottom: 8 },
    formatOnPaste: false,
    quickSuggestions: false,
    wordBasedSuggestions: 'off',
    occurrencesHighlight: 'off',
    unicodeHighlight: { ambiguousCharacters: false, invisibleCharacters: false },
    scrollbar: { alwaysConsumeMouseWheel: false },
  }

  /**
   * @param {HTMLTextAreaElement} textarea
   *
   * @returns {typeof import('monaco-editor').IStandaloneCodeEditor}
   */
  static transformTextareaToMonaco(textarea) {
    // The bundle scans the document when it loads and the block editor also asks
    // for its own textarea by hand: whoever comes second gets the same editor.
    if (undefined !== textarea.pwMonaco) return textarea.pwMonaco.editor

    return 'markdown' === textarea.getAttribute('data-editor')
      ? MonacoHelper.createMarkdownEditor(textarea)
      : MonacoHelper.createOverlayEditor(textarea)
  }

  /**
   * Twig, YAML and JSON fields: Monaco is absolutely positioned over a
   * transparent textarea, which keeps holding the space in the form layout.
   *
   * @param {HTMLTextAreaElement} textarea
   */
  static createOverlayEditor(textarea) {
    const language = textarea.getAttribute('data-editor')

    // manage hidden
    const collapsedElement = textarea.closest('.collapse')
    const isCollapsed = collapsedElement
      ? !collapsedElement.classList.contains('in')
      : false
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

    const monacoHelperInstance = new MonacoHelper(editor)
    const resize = () => monacoHelperInstance.updateHeight(textarea)

    window.addEventListener('resize', resize)

    // Manage textarea resize
    const resizeObserver = new ResizeObserver(resize)
    resizeObserver.observe(textarea)

    editor.onDidContentSizeChange(resize)

    textarea.style.opacity = 0

    // Both outlive editor.dispose(), and both would then resize a dead editor.
    MonacoHelper.bindTextarea(editor, textarea, [editDiv], () => {
      resizeObserver.disconnect()
      window.removeEventListener('resize', resize)
    })

    return editor
  }

  /**
   * Page content: Monaco sits in the flow inside a toolbar/status-bar chrome, and
   * the textarea is only kept as the value carrier the form submits.
   *
   * @param {HTMLTextAreaElement} textarea
   */
  static createMarkdownEditor(textarea) {
    // Read before hiding the textarea: the caller sizes the field through it
    // (50vh on the admin form, 70vh in the block editor's markdown mode).
    const computed = window.getComputedStyle(textarea)
    const minHeight =
      Number.parseFloat(computed.minHeight) || textarea.offsetHeight || 300
    const maxHeight =
      Number.parseFloat(computed.maxHeight) || Math.round(window.innerHeight * 0.65)

    const wrapper = document.createElement('div')
    wrapper.className = 'pw-md'
    const host = document.createElement('div')
    host.className = 'pw-md__host'
    wrapper.append(host)
    textarea.parentNode.insertBefore(wrapper, textarea)
    textarea.style.display = 'none'

    const editor = monaco.editor.create(host, {
      value: textarea.value,
      language: 'markdown',
      ...MonacoHelper.defaultSettings,
      ...MonacoHelper.markdownSettings,
    })

    const monacoHelperInstance = new MonacoHelper(editor)
    const resize = () => monacoHelperInstance.updateHeight(host, minHeight, maxHeight)

    const { toolbar, status, controller } = installMarkdownChrome(editor, wrapper, resize)
    wrapper.prepend(toolbar)
    wrapper.append(status)

    editor.onDidContentSizeChange(resize)
    window.addEventListener('resize', resize, { signal: controller.signal })
    resize()

    MonacoHelper.bindTextarea(editor, textarea, [wrapper], () => controller.abort())

    return editor
  }

  /**
   * @param {typeof import('monaco-editor').IStandaloneCodeEditor} editor
   * @param {HTMLTextAreaElement} textarea
   * @param {HTMLElement[]} nodes elements to drop when the editor goes away
   * @param {() => void} dispose releases listeners registered outside the editor
   */
  static bindTextarea(editor, textarea, nodes, dispose) {
    editor.onDidChangeModelContent(() => {
      textarea.value = editor.getValue()
      // Writing .value fires nothing, so the field is invisible to anything
      // watching the form — the local draft recovery in pushword/admin does.
      textarea.dispatchEvent(new Event('input', { bubbles: true }))
    })

    // Uniform write seam (the block editor exposes the same): assigning the
    // textarea's value leaves Monaco showing the old text, so a caller restoring
    // content needs a way in.
    textarea.pwEditor = { setValue: (markdown) => editor.setValue(markdown) }
    textarea.pwMonaco = { editor, nodes, dispose }
  }

  /**
   * Drops the editor and everything it added around the textarea, restoring the
   * plain field. Used when the block editor leaves markdown mode.
   *
   * @param {HTMLTextAreaElement} textarea
   */
  static destroy(textarea) {
    const bound = textarea.pwMonaco
    if (undefined === bound) return

    bound.dispose()
    bound.editor.dispose()
    for (const node of bound.nodes) node.remove()

    textarea.style.opacity = ''
    textarea.style.display = ''
    delete textarea.pwMonaco
    delete textarea.pwEditor
  }

  /** @param {typeof import('monaco-editor').IStandaloneCodeEditor} editor */
  constructor(editor) {
    this.editor = editor
  }

  autocloseTag() {
    const selfClosingTags = [
      'area',
      'base',
      'br',
      'col',
      'embed',
      'hr',
      'img',
      'input',
      'keygen',
      'link',
      'meta',
      'param',
      'source',
      'track',
      'wbr',
    ]
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
            range: new monaco.Range(
              position.lineNumber,
              position.column,
              position.lineNumber,
              position.column,
            ),
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

  /**
   * @param {HTMLElement} wrapperOrTextarea
   * @param {int} minHeight  in PX
   * @param {int} maxHeight  in PX, beyond which the editor scrolls on its own
   */
  updateHeight(wrapperOrTextarea, minHeight = 60, maxHeight = Infinity) {
    // getContentHeight() counts wrapped lines (wordWrap: 'on'), unlike getLineCount()
    const newHeight = Math.min(
      maxHeight,
      Math.max(this.editor.getContentHeight() + 10, minHeight),
    )
    wrapperOrTextarea.style.height = `${newHeight}px`
    wrapperOrTextarea.style.width = `100%`
    this.resizeEditor(wrapperOrTextarea)
  }

  /**
   *
   * @param {HTMLElement} wrapperOrTextarea
   */
  resizeEditor(wrapperOrTextarea) {
    const { clientHeight, clientWidth } = wrapperOrTextarea
    if (this.editor) {
      this.editor.layout({ height: clientHeight, width: clientWidth })
    }
  }
}
