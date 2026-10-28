import EditorJS, { API, OutputData } from '@editorjs/editorjs'
import type { BlockMutationEvent } from '@editorjs/editorjs/types/events/block'
import Header from './tools/Header/Header'
import Small from './tools/Small/Small'
import List from './tools/List/List'
import Delimiter from './tools/Delimiter/Delimiter'
import Quote from './tools/Quote/Quote'
import Notice from './tools/Notice/Notice'
// @ts-ignore
import Marker from '@editorjs/marker'
import InlineCode from '@editorjs/inline-code'
import Hyperlink from './tools/Hyperlink/Hyperlink'
import Paragraph from './tools/Paragraph/Paragraph'
import Table from './tools/Table'
// @ts-ignore
import DragDrop from 'editorjs-drag-drop'
import Undo, { type UndoOptions } from './tools/utils/Undo/Undo'
// @ts-ignore
import Strikethrough from '@sotaproject/strikethrough'
import Attaches from './tools/Attaches/Attaches'
import Image from './tools/Image/Image'
import Embed from './tools/Embed/Embed'
import PagesList from './tools/PagesList/PagesList'
import CardList from './tools/CardList/CardList'
import Snippet from './tools/Snippet/Snippet'
import Quiz from './tools/Quiz/Quiz'
import Gallery from './tools/Gallery/Gallery'
import AlignementTune from './tools/AlignementTune/AlignementTune'
import HyperlinkTune from './tools/HyperlinkTune/HyperlinkTune'
import PasteLink from './tools/Hyperlink/PasteLink'
import ClipboardManager, { setupBlockCut } from './tools/utils/ClipboardManager'
import { setupBlockSelection } from './tools/utils/BlockSelection'
import { setupEditorPopovers } from './tools/utils/editorPopovers'
import Raw from './tools/Raw/Raw'
import CodeBlock from './tools/CodeBlock/CodeBlock'
import { EditorModeManager } from './EditorModeManager'
import { editorJsHelper } from './editorJsHelper'
import { boundInputOf } from './boundInput'
import { EditorDiagnostics, reportEditorError } from './EditorDiagnostics'
import ClickableTune from './tools/Gallery/ClickableTune'
import AnchorTune from './tools/AnchorTune/AnchorTune'
import ClassTune from './tools/ClassTune/ClassTune'
import GroupStart from './tools/Group/GroupStart'
import GroupEnd from './tools/Group/GroupEnd'
import { GroupRegistry } from './tools/Group/GroupRegistry'
import EditorJsExportMarkdown from './EditorJsExportMarkdown'
import { OutlineLabels, OutlinePanel, OutlineToolMeta } from './outline/OutlinePanel'
import { EditorJsOutlineSource } from './outline/EditorJsOutlineSource'
import {
  JsonMonacoSource,
  MarkdownMonacoSource,
  MonacoContext,
} from './outline/MonacoOutlineSource'

export interface EditorJSConfig {
  holder?: string
  tools?: Record<string, any>
  onChange?: (api: API, events: BlockMutationEvent | BlockMutationEvent[]) => void
  onReady?: () => void
  undo?: Pick<UndoOptions, 'normalizeBlockData'>
  [key: string]: any
}

interface EditorJSTool {
  className?: string
  class?: any
  [key: string]: any
}

/** Editors on the page, including dynamically added form fields. */
export class editorJs {
  private editors: Record<string, EditorJS> = {}
  private editorjsTools: Record<string, any> = {}
  private forms = new WeakSet<HTMLFormElement>()
  private pendingSaves = new Map<string, Promise<OutputData | null>>()

  constructor() {
    this.editors = {}
    this.editorjsTools = window.editorjsTools || {
      HyperlinkTune: HyperlinkTune,
      AnchorTune: AnchorTune,
      ClickableTune: ClickableTune,
      ClassTune: ClassTune,
      AlignementTune: AlignementTune,
      Header: Header,
      List: List,
      Raw: Raw,
      Delimiter: Delimiter,
      GroupStart: GroupStart,
      GroupEnd: GroupEnd,
      // Before Quote: both claim a `> ` chunk, and the first match wins.
      Notice: Notice,
      Quote: Quote,
      Marker: Marker,
      Hyperlink: Hyperlink,
      InlineCode: InlineCode,
      Paragraph: Paragraph,
      Table: Table,
      Attaches: Attaches,
      Image: Image,
      Embed: Embed,
      PagesList: PagesList,
      CardList: CardList,
      Snippet: Snippet,
      Quiz: Quiz,
      Gallery: Gallery,
      Strikethrough: Strikethrough,
      CodeBlock: CodeBlock,
      Small: Small,
    }

    for (const config of Object.values(window.editorjsConfigs ?? { default: window.editorjsConfig })) {
      if (config) this.initEditor(config)
    }
  }

  getEditors(): Record<string, EditorJS> {
    return this.editors
  }

  getTools(): Record<string, any> {
    return this.editorjsTools
  }

  initEditor(config: EditorJSConfig): void {
    if (typeof config.holder === 'undefined' || this.editors[config.holder]) {
      return
    }
    setupEditorPopovers({
      ...config.i18n?.messages?.toolNames,
      ...config.i18n?.messages?.ui?.popover,
    })
    if (typeof config.tools !== 'undefined') {
      // set tool classes
      Object.keys(config.tools).forEach((toolName) => {
        const tool = config.tools![toolName] as EditorJSTool
        // Sites may supply their own constructors through Editor.js' native config.
        if (typeof tool.class === 'function') return
        if (typeof this.editorjsTools[tool.className || ''] !== 'undefined') {
          tool.class = this.editorjsTools[tool.className || '']
          // if (toolName === 'Hyperlink') {
          //   config.tools[toolName].shortcut = 'CMD+K'
          // }
        } else {
          delete config.tools![toolName]
        }
      })
    }

    // Strip Pushword's outline and undo options before constructing Editor.js.
    const outlineConfig = config.outline as { labels: OutlineLabels } | undefined
    delete config.outline
    const undoConfig = config.undo
    delete config.undo
    const diagnosticConfig = config.diagnostics
    delete config.diagnostics
    let diagnostics: EditorDiagnostics | null = null
    const editorListeners = new AbortController()
    let outline: OutlinePanel | null = null

    // Undo takes its baseline when it is built, at which point an editor whose
    // content is markdown is still empty — it would then count the parse as an
    // edit, and one Ctrl+Z before typing anything would empty the page (and the
    // field Save submits). The parse lands asynchronously, so we re-baseline on
    // the first onChange it triggers: that is Editor.js' own "changes settled".
    let undo: Undo | null = null
    let undoAwaitsParsedBaseline = false
    let historyExport: ReturnType<typeof setTimeout> | undefined

    config.onChange = async (_api, events) => {
      // Blocks dragged into or out of a group change membership without any
      // group marker firing a lifecycle hook, so retag on structural changes.
      if (!events || [events].flat().some((event) => event.type !== 'block-changed'))
        GroupRegistry.decorateSoon()
      outline?.scheduleRefresh()

      clearTimeout(historyExport)
      let outputData: OutputData | null
      try {
        outputData = await this.editorjsSave(config.holder!)
      } catch {
        return // editorjsSave already reported the failure; keep the current field value.
      }

      if (undoAwaitsParsedBaseline && undo !== null && outputData !== null) {
        undoAwaitsParsedBaseline = false
        await undo.initialize(outputData)
        this.announceParsedBaseline(config.holder!)
      }
    }

    // Parse content upfront: pass JSON data directly to constructor to avoid double-render
    let markdownContent: string | null = null
    const pageContent = boundInputOf(config.holder)?.value
    if (pageContent) {
      try {
        config.data = JSON.parse(pageContent)
      } catch {
        markdownContent = pageContent
      }
    }

    // The markdown we export is a normalisation, not the byte-for-byte source,
    // so the field stops holding what the server rendered the moment the parse
    // writes back. Unsaved-changes recovery (pushword/admin) baselines the form
    // on window load, either side of that: it waits on this flag and on the
    // event that clears it. Content that came as JSON never fires onChange, so
    // nothing rewrites the field and there is nothing to wait for.
    if (markdownContent !== null) {
      boundInputOf(config.holder!)?.setAttribute('data-pw-baseline-pending', '1')
    }

    const editor = new EditorJS(
      Object.assign(config, {
        onReady: () => {
          new DragDrop(editor)
          undo = new Undo({
            editor,
            ...undoConfig,
            // An applied snapshot bypasses onChange, so undo and redo write the
            // bound field (and refresh the outline) through this hook instead.
            onApply: () => {
              GroupRegistry.decorateSoon()
              outline?.scheduleRefresh()
              // Markdown conversion is expensive on long pages; keep it outside keyboard transactions.
              clearTimeout(historyExport)
              historyExport = setTimeout(() => {
                void this.editorjsSave(config.holder!)
              }, 150)
            },
          })
          const destroy = editor.destroy.bind(editor)
          editor.destroy = () => {
            clearTimeout(historyExport)
            undo?.destroy()
            diagnostics?.destroy()
            editorListeners.abort()
            destroy()
          }
          new PasteLink({ editor })
          new ClipboardManager({ editor })
          setupBlockCut(editor, document.getElementById(config.holder!)!, editorListeners.signal)

          // Markdown content must be parsed after editor is ready (needs tool instances)
          if (markdownContent) {
            undoAwaitsParsedBaseline = true
            // @ts-ignore
            void new window.EditorJsParseMarkdown(editor, markdownContent).parseMarkdown()
          }

          // Content loaded from JSON data never fires onChange, so seed the panel here.
          outline?.scheduleRefresh()
        },
      }),
    )

    this.editors[config.holder!] = editor
    const holder = document.getElementById(config.holder!)
    if (holder) setupBlockSelection(editor, holder, editorListeners.signal)
    if (holder && diagnosticConfig) diagnostics = new EditorDiagnostics(holder, editor, {
      ...diagnosticConfig,
      editorjsVersion: EditorJS.version,
    })

    // Uniform seam for writing the body back from outside (unsaved changes
    // recovery): the field this editor feeds is a plain textarea, so setting
    // its value would leave the rendered blocks showing the old content.
    const boundInput = boundInputOf(config.holder!)
    if (boundInput) {
      this.saveBeforeSubmit(boundInput.form)
      boundInput.pwEditor = {
        setValue: (markdown: string) => {
          // @ts-ignore same window global the initial parse above goes through
          void new window.EditorJsParseMarkdown(editor, markdown).parseMarkdown()
        },
      }
    }

    editorJsHelper.setModeManager(config.holder!, new EditorModeManager(config.holder!))

    if (outlineConfig !== undefined && document.querySelector('.h1Field') !== null) {
      const holderId = config.holder!
      const editorApi = editor as unknown as API
      const monacoContext: MonacoContext = {
        monaco: () =>
          editorJsHelper.getModeManager(holderId)?.getMonacoInstance() ?? null,
        input: () => boundInputOf(holderId),
      }
      outline = new OutlinePanel({
        holderId,
        source: new EditorJsOutlineSource(editorApi),
        monacoSource: (mode) =>
          mode === 'markdown'
            ? new MarkdownMonacoSource(monacoContext, editorApi)
            : mode === 'json'
              ? new JsonMonacoSource(monacoContext)
              : null,
        labels: outlineConfig.labels,
        toolMeta: (type) => this.toolMetaOf(config, type),
      })
    }
  }

  destroyEditor(holderId: string, input: HTMLTextAreaElement | null): void {
    this.editors[holderId]?.destroy()
    editorJsHelper.getModeManager(holderId)?.destroy(input)
    delete this.editors[holderId]
    delete window.editorJsHelper.modeManagers[holderId]
    this.pendingSaves.delete(holderId)
  }

  private saveBeforeSubmit(form: HTMLFormElement | null): void {
    if (!form || this.forms.has(form)) return
    this.forms.add(form)
    let submitting = false

    form.addEventListener(
      'submit',
      async (event) => {
        if (submitting) return
        const holders = Object.keys(this.editors).filter((holderId) => {
          const input = boundInputOf(holderId)
          return input?.form === form && !input.getAttribute('data-editor')
        })
        if (!holders.length) return

        // Editor.js debounces onChange; a quick Save must include the latest edit.
        event.preventDefault()
        event.stopImmediatePropagation()
        try {
          await Promise.all(holders.map((holderId) => this.editorjsSave(holderId)))
        } catch {
          return // Leave the form open and let the author retry after the visible error.
        }
        submitting = true
        // A submission restarted in the same task can be ignored by the browser
        // while the original (prevented) submission is still in progress.
        window.setTimeout(() => {
          if (event.submitter) {
            form.requestSubmit(event.submitter)
          } else {
            form.requestSubmit()
          }
          submitting = false
        }, 0)
      },
      { capture: true },
    )
  }

  /** Toolbox title and icon of a tool, the title translated through the editor's own dictionary. */
  private toolMetaOf(config: EditorJSConfig, type: string): OutlineToolMeta {
    const rawToolbox = (config.tools?.[type] as EditorJSTool | undefined)?.class?.toolbox
    const toolbox = Array.isArray(rawToolbox) ? rawToolbox[0] : rawToolbox
    const title: string = toolbox?.title ?? type

    return {
      title: config.i18n?.messages?.toolNames?.[title] ?? title,
      icon: toolbox?.icon ?? '',
    }
  }

  /**
   * Tells the form the field now carries the parse's own markdown rather than
   * the value the server rendered — the point from which a change is the user's.
   */
  private announceParsedBaseline(holderId: string): void {
    const input = boundInputOf(holderId)
    if (!input) return

    input.removeAttribute('data-pw-baseline-pending')
    input.dispatchEvent(new CustomEvent('pw:baseline-ready', { bubbles: true }))
  }

  async editorjsSave(holderId: string): Promise<OutputData | null> {
    const editorInput = boundInputOf(holderId)
    const editor = this.editors[holderId]

    if (!editorInput || !editor) return null

    const save = Promise.resolve().then(async (): Promise<OutputData | null> => {
      const outputData = await editor.saver.save()
      const markdown = await new EditorJsExportMarkdown(
        editor as unknown as API,
        outputData,
      ).exportToMarkdown()
      const latest = this.pendingSaves.get(holderId)!
      // A submission awaiting an older conversion must await the newest field value too.
      if (latest !== save) return latest
      editorInput.value = markdown
      editorInput.dispatchEvent(new Event('input', { bubbles: true }))
      return outputData
    }).catch((error: unknown) => {
      reportEditorError(document.getElementById(holderId), 'save', error)
      throw error
    })
    this.pendingSaves.set(holderId, save)
    return save
  }
}
