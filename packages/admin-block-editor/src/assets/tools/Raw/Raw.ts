import { BlockToolData, API } from '@editorjs/editorjs'
import Icon from './icon.svg?raw'
//import css from './../../node_modules/@editorjs/raw/src/index.css'
import './Raw-monaco.css'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import { BaseTool } from '../Abstract/BaseTool'
import type { editor } from 'monaco-editor'

export interface RawData extends BlockToolData {
  html: string
}

export default class Raw extends BaseTool {
  private static monacoLoaderPromise: Promise<void> | null = null
  private static readonly MONACO_SCRIPT_URL = '/bundles/pushwordadmin/monaco/app.js'

  public static enableLineBreaks = true

  api: API
  wrapper?: HTMLElement
  editorInstance?: editor.IStandaloneCodeEditor
  private _rawData: RawData = { html: '' }

  static get toolbox() {
    return {
      icon: Icon,
      title: 'Raw',
    }
  }

  constructor({ data, api, readOnly }: { api: API; data: RawData; readOnly: boolean }) {
    super({ data, api, readOnly })
    this.api = api
    this._rawData = { html: data?.html || '' }

    // Override data property with getter/setter to update Monaco when data changes
    Object.defineProperty(this, 'data', {
      get: () => this._rawData,
      set: (newData: RawData) => {
        const html = newData?.html || ''
        this._rawData = { html }

        // Update Monaco editor if it exists
        if (this.editorInstance && this.editorInstance.getValue() !== html) {
          this.editorInstance.setValue(html)
        }
      },
      configurable: true,
      enumerable: true,
    })
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
    // const editorElem = document.createElement('textarea')
    // editorElem.value = this.data.html
    // editorElem.setAttribute('data-editor', 'twig')
    this.wrapper.appendChild(editorElem)

    this.initializeMonaco(editorElem)

    return this.wrapper
  }

  private initializeMonaco(editorElem: HTMLElement): void {
    this.ensureMonacoLoaded()
      .then((ready) => {
        if (!ready || !this.wrapper) {
          return
        }

        try {
          this.editorInstance = this.instantiateEditor(editorElem)
          const monacoHelperInstance = new window.monacoHelper!(this.editorInstance)

          monacoHelperInstance.updateHeight(this.wrapper)
          this.editorInstance.onDidChangeModelContent(() => {
            monacoHelperInstance.updateHeight(this.wrapper!)
            monacoHelperInstance.autocloseTag()
          })
        } catch (error) {
          console.error('Unable to initialize Monaco editor', error)
        }
      })
      .catch((error) => {
        console.error('Failed to load Monaco resources', error)
      })
  }

  private async ensureMonacoLoaded(): Promise<boolean> {
    if (window.monaco && window.monacoHelper) {
      return true
    }

    if (!Raw.monacoLoaderPromise) {
      Raw.monacoLoaderPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script')
        script.src = `${Raw.MONACO_SCRIPT_URL}?v=${Date.now()}`
        script.async = true
        script.defer = true

        const cleanup = (): void => {
          script.removeEventListener('load', onLoad)
          script.removeEventListener('error', onError)
        }

        const onLoad = (): void => {
          cleanup()
          resolve()
        }

        const onError = (event: Event): void => {
          cleanup()
          reject(event)
        }

        script.addEventListener('load', onLoad)
        script.addEventListener('error', onError)
        document.head.appendChild(script)
      })
    }

    try {
      await Raw.monacoLoaderPromise
    } catch (error) {
      Raw.monacoLoaderPromise = null
      console.error('Error loading Monaco script', error)

      return false
    }

    return typeof window.monaco !== 'undefined' && typeof window.monacoHelper !== 'undefined'
  }

  save(): RawData {
    this.data.html = this.editorInstance?.getValue() || ''
    return this.data
  }

  static get conversionConfig() {
    return {
      export: 'html', // this property of tool data will be used as string to pass to other tool
      import: 'html', // to this property imported string will be passed
    }
  }

  // @ts-ignore
  static exportToMarkdown(data: RawData, tunes?: BlockTuneData): string {
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
  static isItMarkdownExported(markdown: string): boolean {
    return true // markdown.trim().match(/^[<{]/) !== null
  }
}
