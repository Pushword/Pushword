import EditorJS, { API, OutputData } from '@editorjs/editorjs'
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
import Undo from './tools/utils/Undo/Undo'
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
import ClipboardManager from './tools/utils/ClipboardManager'
import { setupPopoverTitles } from './tools/utils/popoverTitles'
import Raw from './tools/Raw/Raw'
import CodeBlock from './tools/CodeBlock/CodeBlock'
import { EditorModeManager } from './EditorModeManager'
import { editorJsHelper } from './editorJsHelper'
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

interface EditorJSConfig {
  holder?: string
  tools?: Record<string, any>
  onChange?: () => void
  onReady?: () => void
  [key: string]: any
}

interface EditorJSTool {
  className?: string
  class?: any
  [key: string]: any
}

/** Was initially design to permit multiple editor.js in one page */
export class editorJs {
  private editors: Record<string, EditorJS> = {}
  private editorjsTools: Record<string, any> = {}
  private modeManagers: Record<string, EditorModeManager> = {}

  constructor() {
    if (!window.editorjsConfig) return

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

    setupPopoverTitles()

    this.initEditor((window as any).editorjsConfig)
  }

  getEditors(): Record<string, EditorJS> {
    return this.editors
  }

  getTools(): Record<string, any> {
    return this.editorjsTools
  }

  getModeManager(holderId: string): EditorModeManager | undefined {
    return this.modeManagers[holderId]
  }

  initEditor(config: EditorJSConfig): void {
    if (typeof config.holder === 'undefined') {
      return
    }
    if (typeof config.tools !== 'undefined') {
      // set tool classes
      Object.keys(config.tools).forEach((toolName) => {
        const tool = config.tools![toolName] as EditorJSTool
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

    // The outline panel's config is ours, not EditorJS's: strip it before construction.
    const outlineConfig = config.outline as { labels: OutlineLabels } | undefined
    delete config.outline
    let outline: OutlinePanel | null = null

    // Undo takes its baseline when it is built, at which point an editor whose
    // content is markdown is still empty — it would then count the parse as an
    // edit, and one Ctrl+Z before typing anything would empty the page (and the
    // field Save submits). The parse lands asynchronously, so we re-baseline on
    // the first onChange it triggers: that is Editor.js' own "changes settled".
    let undo: Undo | null = null
    let undoAwaitsParsedBaseline = false

    // save
    // eslint-disable-next-line @typescript-eslint/no-this-alias -- onChange's own `this` (the Editor.js instance, for `this.holder`) shadows the class instance, so we keep a reference to it.
    const self = this
    config.onChange = async function (this: any) {
      // Blocks dragged into or out of a group change membership without any
      // group marker firing a lifecycle hook, so retag on every change.
      GroupRegistry.decorateSoon()
      outline?.scheduleRefresh()

      const outputData = await self.editorjsSave(this.holder)

      if (undoAwaitsParsedBaseline && undo !== null && outputData !== null) {
        undoAwaitsParsedBaseline = false
        undo.initialize(outputData)
        self.announceParsedBaseline(this.holder)
      }
    }

    // Parse content upfront: pass JSON data directly to constructor to avoid double-render
    let markdownContent: string | null = null
    if (window.pageMainContent) {
      const pageContent = window.pageMainContent
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
      this.boundInputOf(config.holder!)?.setAttribute('data-pw-baseline-pending', '1')
    }

    const editor = new EditorJS(
      Object.assign(config, {
        onReady: () => {
          new DragDrop(editor)
          undo = new Undo({
            editor,
            // An applied snapshot bypasses onChange, so undo and redo write the
            // bound field (and refresh the outline) through this hook instead.
            onApply: () => {
              outline?.scheduleRefresh()
              void self.editorjsSave(config.holder!)
            },
          })
          new PasteLink({ editor })
          new ClipboardManager({ editor })

          // Markdown content must be parsed after editor is ready (needs tool instances)
          if (markdownContent) {
            undoAwaitsParsedBaseline = true
            // @ts-ignore
            new window.EditorJsParseMarkdown(editor, markdownContent).parseMarkdown()
          }

          // Content loaded from JSON data never fires onChange, so seed the panel here.
          outline?.scheduleRefresh()
        },
      }),
    )

    this.editors[config.holder!] = editor

    // Uniform seam for writing the body back from outside (unsaved changes
    // recovery): the field this editor feeds is a plain textarea, so setting
    // its value would leave the rendered blocks showing the old content.
    const boundInput = this.boundInputOf(config.holder!)
    if (boundInput) {
      boundInput.pwEditor = {
        setValue: (markdown: string) => {
          // @ts-ignore same window global the initial parse above goes through
          new window.EditorJsParseMarkdown(editor, markdown).parseMarkdown()
        },
      }
    }

    // Créer le gestionnaire de modes pour cet éditeur
    const modeManager = new EditorModeManager(config.holder!)
    this.modeManagers[config.holder!] = modeManager

    // Enregistrer dans editorJsHelper pour l'accès global
    editorJsHelper.setModeManager(config.holder!, modeManager)

    if (outlineConfig !== undefined) {
      const holderId = config.holder!
      const editorApi = editor as unknown as API
      const monacoContext: MonacoContext = {
        monaco: () => this.modeManagers[holderId]?.getMonacoInstance() ?? null,
        input: () => this.boundInputOf(holderId),
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
    const input = this.boundInputOf(holderId)
    if (!input) return

    input.removeAttribute('data-pw-baseline-pending')
    input.dispatchEvent(new CustomEvent('pw:baseline-ready', { bubbles: true }))
  }

  /** The form field a holder feeds, named by its data-input-id. */
  private boundInputOf(holderId: string): HTMLInputElement | null {
    const holder = document.getElementById(holderId)

    return document.getElementById(
      holder?.getAttribute('data-input-id') || '',
    ) as HTMLInputElement | null
  }

  async editorjsSave(holderId: string): Promise<OutputData | null> {
    const editorInput = this.boundInputOf(holderId)
    const editor = this.editors[holderId]

    if (!editorInput || !editor) return null

    const outputData = await editor.saver.save()
    //editorInput.value = JSON.stringify(outputData)

    // @ts-ignore fonctionne même si ne respecte pas le typage
    const editorApi: API = editor as API

    const markdown = await new EditorJsExportMarkdown(
      editorApi,
      outputData,
    ).exportToMarkdown()
    editorInput.value = markdown

    // Assigning .value fires nothing, so anything watching the form (unsaved
    // changes recovery in pushword/admin) would never see the body change.
    editorInput.dispatchEvent(new Event('input', { bubbles: true }))

    return outputData
  }
}
