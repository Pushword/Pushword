import './Header.css'

import { IconHeading } from '@codexteam/icons'
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

/** The levels markdown holds, `##` to `######`: H1 is the page title. */
const HEADING_LEVELS = [2, 3, 4, 5, 6]

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
  private readonly placeholder: string
  private readonly levelSelectLabel: string
  private readonly levels: number[]
  private readonly defaultLevel: number

  constructor({ data, config, api }: ConstructorArgs) {
    this.placeholder = config?.placeholder ?? ''
    this.levelSelectLabel = api.i18n.t('Heading level')
    this.levels = config?.levels ?? HEADING_LEVELS
    this.defaultLevel = config?.defaultLevel ?? 2
    this._data = this.normalizeData(data)
    this._element = this.getTag()
  }

  private normalizeData(data: HeaderDataToNormalize): HeaderData {
    return {
      text: data.text || '',
      level: parseInt((data.level || this.defaultLevel).toString()),
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
      level: this.currentLevel,
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
    this._data.level = this.currentLevel

    return this._data
  }

  set data(data: HeaderData) {
    this._data = this.normalizeData(data)

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

      // Update the level label
      const levelLabel = this._element.querySelector('.ce-header-level-label') as HTMLElement
      if (levelLabel) {
        levelLabel.dataset.level = `H${this._data.level}`
      }
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

    // Create a wrapper for the level selector, set before the heading text
    const levelWrapper = document.createElement('div')
    levelWrapper.classList.add('ce-header-level-wrapper')
    levelWrapper.contentEditable = 'false'

    // Create label element to display current level (uses data attribute to avoid copy issues)
    const levelLabel = document.createElement('span')
    levelLabel.classList.add('ce-header-level-label')
    levelLabel.dataset.level = `H${this.currentLevel}`

    // Create select dropdown for level selection (hidden, no text content)
    const levelSelect = document.createElement('select')
    levelSelect.classList.add('ce-header-level-select')
    levelSelect.contentEditable = 'false'
    levelSelect.title = this.levelSelectLabel
    levelSelect.setAttribute('aria-label', this.levelSelectLabel)

    // The block's own level stays offered, so one the config no longer lists is kept.
    const levels = this.levels.includes(this.currentLevel)
      ? this.levels
      : [...this.levels, this.currentLevel].sort((a, b) => a - b)
    levels.forEach((level) => {
      const option = document.createElement('option')
      option.value = level.toString()
      option.textContent = `H${level}`
      levelSelect.appendChild(option)
    })
    levelSelect.value = this.currentLevel.toString()

    levelSelect.addEventListener('mousedown', (e) => {
      e.stopPropagation()
    })

    levelSelect.addEventListener('change', (e) => {
      e.preventDefault()
      e.stopPropagation()
      const newLevel = parseInt((e.target as HTMLSelectElement).value)
      levelLabel.dataset.level = `H${newLevel}`
      this.setLevel(newLevel)
    })

    this._levelSelect = levelSelect

    levelWrapper.appendChild(levelLabel)
    levelWrapper.appendChild(levelSelect)

    const tag = document.createElement(`H${this.currentLevel}`) as HTMLHeadingElement
    tag.innerHTML = this._data.text || ''
    tag.classList.add('ce-header')
    tag.contentEditable = 'true'
    tag.dataset.placeholder = this.placeholder

    container.appendChild(levelWrapper)
    container.appendChild(tag)

    return container
  }

  /** The block's level, or the default one when its data holds a level no heading can have. */
  private get currentLevel(): number {
    return HEADING_LEVELS.includes(this._data.level)
      ? this._data.level
      : this.defaultLevel
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

  static async exportToMarkdown(
    data: HeaderData,
    tunes?: BlockTuneData,
  ): Promise<string> {
    if (!data || !data.text) {
      return ''
    }

    const level = data.level || 2
    const hashes = '#'.repeat(level)
    let markdown = `${hashes} ${data.text}`
    markdown = MarkdownUtils.convertInlineHtmlToMarkdown(markdown)
    const formattedMarkdown = await MarkdownUtils.formatMarkdownWithPrettier(markdown)
    return MarkdownUtils.addInlineAttributes(formattedMarkdown, tunes)
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    let tunes: BlockTuneData
    let markdownWithoutTunes = markdown.trim()

    const inlineAttrMatch = markdownWithoutTunes.match(/^(#{2,6}\s.+?)\s+\{([^}]+)\}\s*$/)
    if (inlineAttrMatch) {
      tunes = MarkdownUtils.parseAttributes(inlineAttrMatch[2]!)
      markdownWithoutTunes = inlineAttrMatch[1]!
    } else {
      const result = MarkdownUtils.parseTunesFromMarkdown(markdown)
      tunes = result.tunes
      markdownWithoutTunes = result.markdown
    }

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
