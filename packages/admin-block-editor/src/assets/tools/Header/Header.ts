import './Header.css'

import { IconH2, IconH3, IconH4, IconH5, IconH6, IconHeading } from '@codexteam/icons'
import { API, PasteEvent } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import { MarkdownUtils } from '../utils/MarkdownUtils'

export interface HeaderData {
  text: string
  level: number
}

interface HeaderDataToNormalize {
  text?: string
  level?: number
}

export interface HeaderConfig {
  placeholder?: string
  levels?: number[]
  defaultLevel?: number
}

interface Level {
  number: number
  tag: string
  svg: string
}

interface ConstructorArgs {
  data: HeaderDataToNormalize
  config: HeaderConfig
  api: API
  readOnly: boolean
}

export default class Header {
  private _element: HTMLElement
  private _levelSelect: HTMLSelectElement | null = null
  private _data: HeaderData
  private api: API

  constructor({ data, api }: ConstructorArgs) {
    this.api = api
    this._data = Header.normalizeData(data)
    this._element = this.getTag()
  }

  static normalizeData(data: HeaderDataToNormalize): HeaderData {
    return {
      text: data.text || '',
      level: parseInt((data.level || 2).toString()),
    }
  }

  render(): HTMLElement {
    return this._element
  }

  setLevel(level: number): void {
    this.data = {
      level: level,
      text: this.data.text,
    }

    if (this._levelSelect) {
      this._levelSelect.value = level.toString()
    }
  }

  merge(data: HeaderData): void {
    const headerElement = this.getHeaderElement()
    if (headerElement) {
      headerElement.insertAdjacentHTML('beforeend', data.text)
    }
  }

  validate(blockData: HeaderData): boolean {
    return blockData.text.trim() !== ''
  }

  save(toolsContent: HTMLElement): HeaderData {
    const headerElement = this.getHeaderElement()

    return {
      text: headerElement ? headerElement.innerHTML : toolsContent.innerHTML,
      level: this.currentLevel.number,
    }
  }

  static get conversionConfig() {
    return {
      export: 'text',
      import: 'text',
    }
  }

  static get sanitize() {
    return {
      level: false,
      text: {
        br: true,
        small: true,
        a: true,
        u: true,
        i: true,
        b: true,
        s: true,
        sup: true,
        sub: true,
      },
    }
  }

  get data(): HeaderData {
    const headerElement = this.getHeaderElement()
    if (!headerElement) {
      return this._data
    }

    this._data.text = headerElement.innerHTML
    this._data.level = this.currentLevel.number

    return this._data
  }

  set data(data: HeaderData) {
    this._data = Header.normalizeData(data)

    if (data.level !== undefined && this._element.parentNode) {
      const newHeader = this.getTag()
      const newHeaderElement = this.getHeaderElement(newHeader)
      const oldHeaderElement = this.getHeaderElement()

      if (newHeaderElement && oldHeaderElement) {
        newHeaderElement.innerHTML = oldHeaderElement.innerHTML
      }

      this._element.parentNode.replaceChild(newHeader, this._element)
      this._element = newHeader
      this._levelSelect = this._element.querySelector('.ce-header-level-select')
    }

    if (data.text !== undefined) {
      const headerElement = this.getHeaderElement()
      if (headerElement) {
        headerElement.innerHTML = data.text || ''
      }
    }
  }

  private getHeaderElement(element?: HTMLElement): HTMLHeadingElement | null {
    const target = element || this._element
    if (!target) return null

    const header = target.querySelector('h1, h2, h3, h4, h5, h6') as HTMLHeadingElement
    if (header) return header

    if (target.tagName.match(/^H[1-6]$/)) {
      return target as HTMLHeadingElement
    }

    return null
  }

  private getTag(): HTMLElement {
    const container = document.createElement('div')
    container.classList.add('ce-header-container')

    // Create select dropdown for level selection
    const levelSelect = document.createElement('select')
    levelSelect.classList.add('ce-header-level-select')
    levelSelect.contentEditable = 'false'
    levelSelect.title = 'Select heading level'

    this.levels.forEach((level) => {
      const option = document.createElement('option')
      option.value = level.number.toString()
      option.textContent = `H${level.number}`
      option.selected = level.number === this._data.level
      levelSelect.appendChild(option)
    })

    levelSelect.addEventListener('change', (e) => {
      e.preventDefault()
      e.stopPropagation()
      const newLevel = parseInt((e.target as HTMLSelectElement).value)
      this.setLevel(newLevel)
    })

    this._levelSelect = levelSelect

    const tag = document.createElement(this.currentLevel.tag) as HTMLHeadingElement
    tag.innerHTML = this._data.text || ''
    tag.classList.add('ce-header')
    tag.contentEditable = 'true'
    tag.dataset.placeholder = this.api.i18n.t('')

    container.appendChild(levelSelect)
    container.appendChild(tag)

    return container
  }

  get currentLevel(): Level {
    return (
      this.levels.find((levelItem) => levelItem.number === this._data.level) ||
      this.defaultLevel
    )
  }

  get defaultLevel(): Level {
    const defaultLevel = this.levels[0]
    if (!defaultLevel) {
      throw new Error('Default level not found')
    }
    return defaultLevel
  }

  get levels(): Level[] {
    return [
      { number: 2, tag: 'H2', svg: IconH2 },
      { number: 3, tag: 'H3', svg: IconH3 },
      { number: 4, tag: 'H4', svg: IconH4 },
      { number: 5, tag: 'H5', svg: IconH5 },
      { number: 6, tag: 'H6', svg: IconH6 },
    ]
  }

  onPaste(event: PasteEvent): void {
    const detail = event.detail

    if ('data' in detail) {
      const content = detail.data as HTMLElement
      const tagToLevel: Record<string, number> = {
        H2: 2,
        H3: 3,
        H4: 4,
        H5: 5,
        H6: 6,
      }

      const level = tagToLevel[content.tagName] || 2

      this.data = {
        level,
        text: content.innerHTML,
      }
    }
  }

  static get pasteConfig() {
    return {
      tags: ['H1', 'H2', 'H3', 'H4', 'H5', 'H6'],
    }
  }

  static get toolbox() {
    return {
      icon: IconHeading,
      title: 'Heading',
    }
  }

  static async exportToMarkdown(data: HeaderData, tunes: BlockTuneData): Promise<string> {
    if (!data || !data.text) {
      return ''
    }

    const level = data.level || 2
    const hashes = '#'.repeat(level)
    let markdown = `${hashes} ${data.text}`
    markdown = MarkdownUtils.convertInlineHtmlToMarkdown(markdown)
    const formattedMarkdown = await MarkdownUtils.formatMarkdownWithPrettier(markdown)
    return MarkdownUtils.addAttributes(formattedMarkdown, tunes)
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown)
    const tunes: BlockTuneData = result.tunes
    let markdownWithoutTunes = result.markdown
    markdownWithoutTunes = MarkdownUtils.convertInlineMarkdownToHtml(markdownWithoutTunes)

    const levelMatch = markdownWithoutTunes.trim().match(/^#{2,6}\s/)
    if (!levelMatch) {
      throw new Error('Invalid markdown format for header')
    }

    const data: HeaderData = {
      text: markdownWithoutTunes.replace(/^#{2,6}\s/, '').trim(),
      level: levelMatch[0].trim().length,
    }

    const block = editor.blocks.insert('header')
    editor.blocks.update(block.id, data, tunes)
  }

  static isItMarkdownExported(markdown: string): boolean {
    return /^#{2,6}\s/.test(markdown.trim())
  }
}
