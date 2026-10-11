import { BlockToolData, API } from '@editorjs/editorjs'
import Icon from './icon.svg?raw'
import './Raw-monaco.css'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import { BaseTool } from '../Abstract/BaseTool'
import type { editor } from 'monaco-editor'
import { embeddedEditors } from '../utils/Undo/Selection'
import { loadMonaco } from '../utils/loadScript'

export interface RawData extends BlockToolData {
  html: string
}

export default class Raw extends BaseTool {
  public static enableLineBreaks = true

  api: API
  wrapper?: HTMLElement
  editorInstance?: editor.IStandaloneCodeEditor
  private visibility?: IntersectionObserver

  static get toolbox() {
    return {
      icon: Icon,
      title: 'Raw',
    }
  }

  constructor({ data, api, readOnly }: { api: API; data: RawData; readOnly: boolean }) {
    super({ data, api, readOnly })
    this.api = api
    this.data = { html: data?.html || '' }
  }

  instantiateEditor(editorElem: HTMLElement): editor.IStandaloneCodeEditor {
    const monaco = window.monaco
    const monacoHelper = window.monacoHelper

    if (!monaco || !monacoHelper) {
      throw new Error('monaco is not defined')
    }

    return monaco.editor.create(
      editorElem,
      // @ts-ignore
      {
        value: this.data.html,
        language: 'twig',
        ...monacoHelper.defaultSettings,
      },
    )
  }

  render(): HTMLElement {
    this.wrapper = document.createElement('div')
    this.wrapper.classList.add('editorjs-monaco-wrapper')

    // Create Monaco editor container
    const editorElem = document.createElement('div')
    editorElem.classList.add('editorjs-monaco-editor')
    editorElem.style.height = '100%'
    const preview = document.createElement('textarea')
    preview.className = 'editorjs-monaco-preview'
    preview.value = this.data.html
    preview.readOnly = this.readOnly
    preview.rows = Math.min(20, Math.max(2, this.data.html.split('\n').length))
    preview.addEventListener('input', () => {
      this.data.html = preview.value
    })
    editorElem.appendChild(preview)
    this.wrapper.appendChild(editorElem)

    let initialization: Promise<void> | undefined
    const activate = (): Promise<void> => {
      this.visibility?.disconnect()
      return (initialization ??= this.initializeMonaco(editorElem).then(() => {
        embedded.instance = this.editorInstance
      }))
    }
    const embedded = {
      instance: undefined as editor.IStandaloneCodeEditor | undefined,
      activate,
    }
    embeddedEditors.set(this.wrapper, embedded)
    preview.addEventListener('focus', () => {
      void activate()
    })
    if (typeof IntersectionObserver === 'undefined') {
      void activate()
    } else {
      this.visibility = new IntersectionObserver(
        (entries) => {
          if (entries.some((entry) => entry.isIntersecting)) void activate()
        },
        { rootMargin: '400px' },
      )
      this.visibility.observe(this.wrapper)
    }

    return this.wrapper
  }

  private initializeMonaco(editorElem: HTMLElement): Promise<void> {
    return loadMonaco().then((ready) => {
      if (!ready || !this.wrapper) {
        return
      }

      try {
        const focused = editorElem.contains(document.activeElement)
        const preview = editorElem.querySelector('textarea')
        editorElem.replaceChildren()
        this.editorInstance = this.instantiateEditor(editorElem)
        const monacoHelperInstance = new window.monacoHelper!(this.editorInstance)

        monacoHelperInstance.updateHeight(this.wrapper)
        this.editorInstance.onDidContentSizeChange(() => {
          monacoHelperInstance.updateHeight(this.wrapper!)
        })
        this.editorInstance.onDidChangeModelContent(() => {
          monacoHelperInstance.autocloseTag()
          this.wrapper?.dispatchEvent(
            new CustomEvent('pw:history-change', { bubbles: true }),
          )
        })
        if (focused) {
          const model = this.editorInstance.getModel()!
          if (preview) {
            const backward = preview.selectionDirection === 'backward'
            const start = model.getPositionAt(
              backward ? preview.selectionEnd : preview.selectionStart,
            )
            const end = model.getPositionAt(
              backward ? preview.selectionStart : preview.selectionEnd,
            )
            this.editorInstance.setSelection({
              selectionStartLineNumber: start.lineNumber,
              selectionStartColumn: start.column,
              positionLineNumber: end.lineNumber,
              positionColumn: end.column,
            })
          }
          this.editorInstance.focus()
        }
      } catch (error) {
        console.error('Unable to initialize Monaco editor', error)
      }
    })
  }

  destroy(): void {
    this.visibility?.disconnect()
    if (this.wrapper) embeddedEditors.delete(this.wrapper)
    const model = this.editorInstance?.getModel()
    this.editorInstance?.dispose()
    model?.dispose()
    delete this.editorInstance
    delete this.wrapper
  }

  save(): RawData {
    if (this.editorInstance) {
      this.data.html = this.editorInstance.getValue()
    }

    return this.data
  }

  static get conversionConfig() {
    return {
      export: 'html', // this property of tool data will be used as string to pass to other tool
      import: 'html', // to this property imported string will be passed
    }
  }

  // @ts-ignore
  static exportToMarkdown(data: RawData, _tunes?: BlockTuneData): string {
    if (!data || !data.html) {
      return ''
    }

    // permit the go back to editorjs
    return data.html
      .replace(/\r\n/g, '\n')
      .replace(/\n[ \t]+\n/g, '\n')
      .replace(/\n{2,}/g, '\n')
      .trim()
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    const block = editor.blocks.insert('raw')
    editor.blocks.update(
      block.id,
      {
        html: markdown,
      },
      {},
    )
  }

  // @ts-ignore
  static isItMarkdownExported(_markdown: string): boolean {
    return true // markdown.trim().match(/^[<{]/) !== null
  }
}
