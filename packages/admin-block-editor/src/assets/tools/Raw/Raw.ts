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
  public static enableLineBreaks = true

  api: API
  wrapper?: HTMLElement
  editorInstance?: editor.IStandaloneCodeEditor
  data: RawData

  static get toolbox() {
    return {
      icon: Icon,
      title: 'Raw',
    }
  }

  constructor({ data, api, readOnly }: { api: API; data: RawData; readOnly: boolean }) {
    super({ data, api, readOnly })
    this.api = api
    this.data = { html: data.html || '' }
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

    // Initialize Monaco editor

    // test if window.monaco is defined
    if (typeof window.monaco === 'undefined') {
      console.log('monaco is not defined')
      return this.wrapper
    }

    //this.editorInstance = window.monacoHelper?.transformTextareaToMonaco(editorElem)
    this.editorInstance = this.instantiateEditor(editorElem)
    const monacoHelperInstance = new window.monacoHelper!(this.editorInstance)

    monacoHelperInstance.updateHeight(this.wrapper)
    this.editorInstance.onDidChangeModelContent(() => {
      monacoHelperInstance.updateHeight(this.wrapper!)
      monacoHelperInstance.autocloseTag()
    })

    return this.wrapper
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
