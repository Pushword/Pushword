import Icon from './icon.svg?raw'
import make from '../utils/make'
import Raw, { RawData } from './../Raw/Raw'
import { API } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import { MarkdownUtils } from '../utils/MarkdownUtils'

export interface CodeBlockData extends RawData {
  html: string
  language?: string
}

/**
 * The code is contains in html, but it could be whatever you want
 */
export default class CodeBlock extends Raw {
  public data: { html: string; language: string }

  //public static readonly toolName = 'codeBlock'

  constructor({
    data,
    api,
    readOnly,
  }: {
    data: CodeBlockData
    api: API
    readOnly: boolean
  }) {
    super({ data, api, readOnly })
    this.data = {
      html: data.html || '',
      language: data.language || 'html',
    }
  }

  render(): HTMLElement {
    const wrapper = super.render()

    const select = make.element('select', this.api.styles.input, {
      style:
        'max-width: 100px;padding: 5px 6px;margin: auto; position: absolute; right: 5px; z-index: 5; background: white',
    }) as HTMLSelectElement
    make.options(select, ['html', 'twig', 'javascript', 'php', 'json', 'yaml'])
    select.value = this.data.language
    select.addEventListener('change', (event: Event) => {
      const target = event.target as HTMLSelectElement
      this.data.language = target.value
      // @ts-ignore
      this.editorInstance.getModel().setLanguage(this.data.language)
    })

    //wrapper.appendChild(select)

    const editorWrapper = wrapper.firstChild
    wrapper.insertBefore(select, editorWrapper)
    wrapper.style.marginBottom = '35px'
    wrapper.style.position = 'relative'
    wrapper.classList.add('monaco-codeblock-wrapper')

    return wrapper
  }
  /**
   * Extract Tool's data from the view
   *
   * @returns {RawData} - raw HTML code
   * @public
   */
  save(): { html: string; language: string } {
    let html = ''
    try {
      // @ts-ignore
      html = this.editorInstance.getValue()
    } catch (error) {
      console.error(error)
    }

    this.data = {
      html: html,
      language: this.data.language,
    }

    return this.data
  }

  static get toolbox() {
    return {
      icon: Icon,
      title: 'Code',
    }
  }

  /**
   * Export block data to Markdown
   * @param {CodeBlockData} data - Block data
   * @param {BlockTuneData} tunes - Block tunes
   * @returns {string} Markdown representation
   */
  // @ts-ignore
  static exportToMarkdown(data: CodeBlockData, tunes?: BlockTuneData): string {
    if (!data || !data.html) {
      return ''
    }

    const language = data.language || ''
    //data.html = data.html.replace(/\n{2,}/g, '\n')
    return `\`\`\`${language}\n${data.html}\n\`\`\``
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    const lines = markdown.split('\n')
    let i = 0
    let tunes: BlockTuneData = {}
    let language = ''
    let html = ''
    let firstLineHasAttributes = false

    for (const line of lines) {
      if (i === 0 && MarkdownUtils.startWithAttribute(line)) {
        tunes = MarkdownUtils.parseAttributes(line)
        firstLineHasAttributes = true
        i++
        continue
      } else if (i === 0 || (i === 1 && firstLineHasAttributes)) {
        language = line.replace('```', '').trim()
        i++
        continue
      }

      if (i === lines.length - 1) {
        break
      }

      html += lines[i] + '\n'
      i++
    }

    const block = editor.blocks.insert('codeBlock')
    editor.blocks.update(
      block.id,
      {
        html: html.trim(),
        language: language || 'html',
      },
      tunes,
    )
  }

  static isItMarkdownExported(markdown: string): boolean {
    return markdown.trim().startsWith('```') && markdown.trim().endsWith('```')
  }
}
