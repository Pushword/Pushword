import * as monaco from 'monaco-editor'

import './markdownEditor.css'
import {
  computeEnter,
  insertImage,
  insertLink,
  linkifyPaste,
  shiftHeading,
  toggleCode,
  toggleHeading,
  toggleLinePrefix,
  toggleTask,
  toggleWrap,
} from './markdownActions'

const { CtrlCmd, Shift, Alt } = monaco.KeyMod
const K = monaco.KeyCode

const CHEATSHEET_URL = '/admin/markdown-cheatsheet'
const WORDS = /[\p{L}\p{N}'’-]+/gu

/**
 * Toolbar buttons, in display order. `run` gets the whole document plus the
 * selection offsets and returns the edits to apply, or null to do nothing.
 */
const BUTTONS = [
  {
    name: 'bold',
    icon: 'fa-bold',
    title: 'Bold (Ctrl+B)',
    keybindings: [CtrlCmd | K.KeyB],
    run: (text, start, end) => toggleWrap(text, start, end, '**'),
  },
  {
    name: 'italic',
    icon: 'fa-italic',
    title: 'Italic (Ctrl+I)',
    keybindings: [CtrlCmd | K.KeyI],
    run: (text, start, end) => toggleWrap(text, start, end, '*'),
  },
  {
    name: 'strikethrough',
    icon: 'fa-strikethrough',
    title: 'Strikethrough (Alt+S)',
    keybindings: [Alt | K.KeyS],
    run: (text, start, end) => toggleWrap(text, start, end, '~~'),
  },
  {
    name: 'heading-2',
    text: 'H2',
    title: 'Heading 2',
    run: (text, start) => toggleHeading(text, start, 2),
  },
  {
    name: 'heading-3',
    text: 'H3',
    title: 'Heading 3',
    run: (text, start) => toggleHeading(text, start, 3),
  },
  { separator: true },
  {
    name: 'unordered-list',
    icon: 'fa-list-ul',
    title: 'Bullet list',
    run: (text, start, end) => toggleLinePrefix(text, start, end, 'ul'),
  },
  {
    name: 'ordered-list',
    icon: 'fa-list-ol',
    title: 'Numbered list',
    run: (text, start, end) => toggleLinePrefix(text, start, end, 'ol'),
  },
  {
    name: 'task-list',
    icon: 'fa-square-check',
    title: 'Task (Alt+C)',
    keybindings: [Alt | K.KeyC],
    run: toggleTask,
  },
  { separator: true },
  {
    name: 'link',
    icon: 'fa-link',
    title: 'Link (Ctrl+K)',
    keybindings: [CtrlCmd | K.KeyK],
    run: insertLink,
  },
  { name: 'image', icon: 'fa-image', title: 'Image', run: insertImage },
  {
    name: 'quote',
    icon: 'fa-quote-left',
    title: 'Quote',
    run: (text, start, end) => toggleLinePrefix(text, start, end, 'quote'),
  },
  { name: 'code', icon: 'fa-code', title: 'Code', run: toggleCode },
]

/** Keyboard-only actions: no button, but they belong in the command palette. */
const COMMANDS = [
  {
    name: 'heading-up',
    title: 'Heading level up',
    keybindings: [CtrlCmd | Shift | K.BracketRight],
    run: (text, start) => shiftHeading(text, start, 1),
  },
  {
    name: 'heading-down',
    title: 'Heading level down',
    keybindings: [CtrlCmd | Shift | K.BracketLeft],
    run: (text, start) => shiftHeading(text, start, -1),
  },
]

function offsetsOf(editor) {
  const model = editor.getModel()
  const selection = editor.getSelection()

  return [
    model.getOffsetAt(selection.getStartPosition()),
    model.getOffsetAt(selection.getEndPosition()),
  ]
}

/**
 * Applies an action result. The edit ranges are resolved against the text as it
 * is now, the selection against the text as it will be, so the model has to be
 * read again after executeEdits.
 */
function apply(editor, result) {
  if (result === null) return

  const model = editor.getModel()
  editor.executeEdits(
    'pw-markdown',
    result.edits.map((edit) => ({
      range: monaco.Range.fromPositions(
        model.getPositionAt(edit.start),
        model.getPositionAt(edit.end),
      ),
      text: edit.text,
      forceMoveMarkers: true,
    })),
  )

  const [start, end] = result.selection
  editor.setSelection(
    monaco.Range.fromPositions(model.getPositionAt(start), model.getPositionAt(end)),
  )
  editor.focus()
}

function run(editor, action) {
  const [start, end] = offsetsOf(editor)
  apply(editor, action(editor.getModel().getValue(), start, end))
}

function button(item, editor) {
  const element = document.createElement('button')
  element.type = 'button'
  element.className = 'pw-md__btn'
  element.title = item.title
  element.setAttribute('aria-label', item.title)
  element.dataset.action = item.name

  if (item.icon !== undefined) {
    element.innerHTML = `<i class="fa-solid ${item.icon}" aria-hidden="true"></i>`
  } else {
    element.textContent = item.text
  }

  element.addEventListener('click', () => run(editor, item.run))

  return element
}

function buildToolbar(editor) {
  const toolbar = document.createElement('div')
  toolbar.className = 'pw-md__toolbar'
  toolbar.setAttribute('role', 'toolbar')

  for (const item of BUTTONS) {
    if (item.separator === true) {
      const separator = document.createElement('span')
      separator.className = 'pw-md__separator'
      toolbar.append(separator)
      continue
    }

    toolbar.append(button(item, editor))
  }

  const spacer = document.createElement('span')
  spacer.className = 'pw-md__spacer'
  toolbar.append(spacer)

  return toolbar
}

function fullscreenButton(wrapper, editor, onResize) {
  const element = document.createElement('button')
  element.type = 'button'
  element.className = 'pw-md__btn'
  element.title = 'Fullscreen'
  element.setAttribute('aria-label', 'Fullscreen')
  element.innerHTML = '<i class="fa-solid fa-expand" aria-hidden="true"></i>'

  element.addEventListener('click', () => {
    const full = wrapper.classList.toggle('pw-md--fullscreen')
    element.firstChild.className = `fa-solid ${full ? 'fa-compress' : 'fa-expand'}`
    if (full) editor.layout()
    else onResize()
    editor.focus()
  })

  return element
}

function cheatsheetButton() {
  const element = document.createElement('a')
  element.className = 'pw-md__btn'
  element.href = CHEATSHEET_URL
  element.target = '_blank'
  element.rel = 'noopener'
  element.title = 'Markdown documentation'
  element.setAttribute('aria-label', 'Markdown documentation')
  element.innerHTML = '<i class="fa-solid fa-circle-question" aria-hidden="true"></i>'

  return element
}

function buildStatus(editor) {
  const status = document.createElement('div')
  status.className = 'pw-md__status'

  const counts = document.createElement('span')
  const position = document.createElement('span')
  status.append(counts, position)

  const refresh = () => {
    const model = editor.getModel()
    const value = model.getValue()
    counts.textContent = `${(value.match(WORDS) ?? []).length} words · ${model.getLineCount()} lines`

    const caret = editor.getPosition()
    position.textContent = `${caret.lineNumber}:${caret.column}`
  }

  editor.onDidChangeModelContent(refresh)
  editor.onDidChangeCursorPosition(refresh)
  refresh()

  return status
}

/**
 * Enter carries list markers over. Registered as a keybinding rather than an
 * onEnterRule because clearing an empty item and renumbering both need to edit
 * text, which declarative rules cannot do. Anything the primitives decline falls
 * back to Monaco's own newline handling, indentation included.
 */
function wireEnter(editor) {
  editor.addCommand(
    K.Enter,
    () => {
      const selection = editor.getSelection()
      const model = editor.getModel()
      const result = selection.isEmpty()
        ? computeEnter(model.getValue(), model.getOffsetAt(selection.getPosition()))
        : null

      if (result === null) {
        editor.trigger('keyboard', 'type', { text: '\n' })

        return
      }

      apply(editor, result)
    },
    '!suggestWidgetVisible && !renameInputVisible && !inSnippetMode',
  )
}

/**
 * Pasting a URL over a selection links it instead of replacing it.
 *
 * Bound on the document rather than on the editor: Monaco types into a native
 * EditContext div and swallows the clipboard event on its way down, so a
 * listener on the editor's own node never sees it. The containment test is what
 * keeps two editors on one page apart.
 */
function wirePaste(editor, signal) {
  const node = editor.getDomNode()

  document.addEventListener(
    'paste',
    (event) => {
      if (!node.contains(event.target)) return

      const pasted = event.clipboardData?.getData('text/plain') ?? ''
      const [start, end] = offsetsOf(editor)
      const result = linkifyPaste(editor.getModel().getValue(), start, end, pasted)
      if (result === null) return

      event.preventDefault()
      event.stopPropagation()
      apply(editor, result)
    },
    { capture: true, signal },
  )
}

/**
 * Builds the toolbar and status bar around a markdown editor and binds the
 * shortcuts. Returns the two elements so the caller can place them, plus the
 * controller every listener outside the editor is registered against — disposing
 * the editor does not reach those.
 *
 * @param {typeof import('monaco-editor').IStandaloneCodeEditor} editor
 * @param {HTMLElement} wrapper   element carrying the fullscreen class
 * @param {() => void} onResize   re-applies the content-driven height
 */
export function installMarkdownChrome(editor, wrapper, onResize) {
  const controller = new AbortController()
  const toolbar = buildToolbar(editor)
  toolbar.append(fullscreenButton(wrapper, editor, onResize), cheatsheetButton())

  for (const item of [...BUTTONS, ...COMMANDS]) {
    if (item.separator === true || item.keybindings === undefined) continue

    editor.addAction({
      id: `pw.markdown.${item.name}`,
      label: item.title,
      keybindings: item.keybindings,
      run: () => run(editor, item.run),
    })
  }

  wireEnter(editor)
  wirePaste(editor, controller.signal)

  return { toolbar, status: buildStatus(editor), controller }
}
