import EditorJS, { API } from '@editorjs/editorjs'
import Header from './tools/Header/Header'
import Small from './tools/Small/Small'
import List from './tools/List/List'
//import Raw from '@editorjs/raw';
import Delimiter from './tools/Delimiter/Delimiter'
import Quote from './tools/Quote/Quote'
// @ts-ignore
import Marker from '@editorjs/marker'
import InlineCode from '@editorjs/inline-code'
//import { StyleInlineTool } from "editorjs-style";
import Hyperlink from './tools/Hyperlink/Hyperlink'
import Paragraph from './tools/Paragraph/Paragraph'
import Table from './tools/Table/Table'
// @ts-ignore
import DragDrop from 'editorjs-drag-drop'
// @ts-ignore
import Undo from 'editorjs-undo'
// @ts-ignore
import Strikethrough from '@sotaproject/strikethrough'
import Attaches from './tools/Attaches/Attaches'
import Image from './tools/Image/Image'
import Embed from './tools/Embed/Embed'
import PagesList from './tools/PagesList/PagesList'
import Gallery from './tools/Gallery/Gallery'
import AlignementTune from './tools/AlignementTune/AlignementTune'
import HyperlinkTune from './tools/HyperlinkTune/HyperlinkTune'
import PasteLink from './tools/Hyperlink/PasteLink'
import Raw from './tools/Raw/Raw'
import CodeBlock from './tools/CodeBlock/CodeBlock'
import { EditorModeManager } from './EditorModeManager'
import { editorJsHelper } from './editorJsHelper'
import ClickableTune from './tools/Gallery/ClickableTune'
import AnchorTune from './tools/AnchorTune/AnchorTune'
import ClassTune from './tools/ClassTune/ClassTune'
import EditorJsExportMarkdown from './EditorJsExportMarkdown'

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
      Gallery: Gallery,
      Strikethrough: Strikethrough,
      CodeBlock: CodeBlock,
      Small: Small,
      //StyleInlineTool: StyleInlineTool,
    }

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

    // save
    const self = this
    config.onChange = async function (this: any) {
      await self.editorjsSave(this.holder)
    }

    // drag'n drop
    config.onReady = function (this: any) {
      new DragDrop(editor)
      new Undo({ editor })
    }

    const editor = new EditorJS(
      Object.assign(config, {
        onReady: () =>
          new DragDrop(editor) && new Undo({ editor }) && new PasteLink({ editor }),
      }),
    )

    if (window.pageMainContent) {
      const pageContent = window.pageMainContent
      try {
        const data = JSON.parse(pageContent)
        editor.isReady.then(() => {
          editor.blocks.render(data)
        })
      } catch {
        editor.isReady.then(() => {
          // @ts-ignore
          new window.EditorJsParseMarkdown(editor, pageContent).parseMarkdown()
        })
      }
    }

    this.editors[config.holder!] = editor

    // Créer le gestionnaire de modes pour cet éditeur
    const modeManager = new EditorModeManager(config.holder!)
    this.modeManagers[config.holder!] = modeManager

    // Enregistrer dans editorJsHelper pour l'accès global
    editorJsHelper.setModeManager(config.holder!, modeManager)
  }

  async editorjsSave(holderId: string): Promise<void> {
    const editorHolder = document.getElementById(holderId)
    const editorInput = document.getElementById(
      editorHolder?.getAttribute('data-input-id') || '',
    ) as HTMLInputElement | null
    const editor = this.editors[holderId]

    if (!editorInput || !editor) return

    const outputData = await editor.saver.save()
    //editorInput.value = JSON.stringify(outputData)

    // @ts-ignore fonctionne même si ne respecte pas le typage
    const editorApi: API = editor as API

    const markdown = await new EditorJsExportMarkdown(
      editorApi,
      outputData,
    ).exportToMarkdown()
    editorInput.value = markdown
  }
}
