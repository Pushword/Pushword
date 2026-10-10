import { IconCurlyBrackets } from '@codexteam/icons'
import make from '../utils/make'
import Raw, { RawData } from './../Raw/Raw'
import { API } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import { MarkdownUtils } from '../utils/MarkdownUtils'
import type { editor } from 'monaco-editor'
import './CodeBlock.css'
import '@pushword/js-helper/src/mermaid.css'

export interface CodeBlockData extends RawData {
  language?: string
}

export default class CodeBlock extends Raw {
  private _codeBlockData: {
    html: string
    language: string
  } = { html: '', language: 'html' }

  private readonly mermaidUrl: string
  private languageSelect?: HTMLSelectElement
  private preview?: HTMLElement
  private previewTimer?: ReturnType<typeof setTimeout>
  private previewRevision = 0
  private contentListener?: { dispose(): void }

  constructor({
    data,
    api,
    readOnly,
    config,
  }: {
    data: CodeBlockData
    api: API
    readOnly: boolean
    config: { mermaidUrl: string }
  }) {
    super({ data, api, readOnly })
    this.mermaidUrl = config.mermaidUrl
    this._codeBlockData = {
      html: data?.html || '',
      language: data?.language || 'html',
    }

    // Override data property with getter/setter to update Monaco when data changes
    Object.defineProperty(this, 'data', {
      get: () => this._codeBlockData,
      set: (newData: CodeBlockData) => {
        const html = newData?.html || ''
        const language = newData?.language || this._codeBlockData.language || 'html'
        this._codeBlockData = { html, language }

        // Update Monaco editor if it exists
        if (this.editorInstance && this.editorInstance.getValue() !== html) {
          this.editorInstance.setValue(html)
        }
        this.updateLanguage()
        this.schedulePreview()
      },
      configurable: true,
      enumerable: true,
    })
  }

  render(): HTMLElement {
    const code = super.render()
    code.classList.add('monaco-codeblock-wrapper')
    const wrapper = make.element('div', 'pw-code-block')
    const label = make.element('label', 'pw-code-language')
    label.append(this.api.i18n.t('Language'))
    const select = make.element('select', this.api.styles.input) as HTMLSelectElement
    make.options(select, ['html', 'twig', 'javascript', 'php', 'json', 'yaml', 'mermaid'])
    this.languageSelect = select
    this.updateLanguage()
    select.disabled = this.readOnly
    select.addEventListener('change', () => {
      this._codeBlockData.language = select.value
      this.updateLanguage()
      this.schedulePreview()
    })
    label.append(select)
    this.preview = make.element('div', 'pw-mermaid-preview')
    this.preview.setAttribute('aria-live', 'polite')
    wrapper.append(label, code, this.preview)
    this.schedulePreview()
    return wrapper
  }

  instantiateEditor(element: HTMLElement): editor.IStandaloneCodeEditor {
    const instance = super.instantiateEditor(element)
    instance.updateOptions({ readOnly: this.readOnly })
    this.updateLanguage(instance)
    this.contentListener = instance.onDidChangeModelContent(() => this.schedulePreview())
    return instance
  }

  private updateLanguage(instance = this.editorInstance): void {
    if (this.wrapper) this.wrapper.dataset.language = this._codeBlockData.language
    if (this.languageSelect) {
      if (
        !Array.from(this.languageSelect.options).some(
          (option) => option.value === this._codeBlockData.language,
        )
      ) {
        make.option(this.languageSelect, this._codeBlockData.language)
      }
      this.languageSelect.value = this._codeBlockData.language
    }
    const model = instance?.getModel()
    if (model) {
      window.monaco?.editor.setModelLanguage(model, this._codeBlockData.language)
    }
  }

  private schedulePreview(): void {
    clearTimeout(this.previewTimer)
    const revision = ++this.previewRevision
    if (!this.preview) return
    this.preview.hidden = this._codeBlockData.language !== 'mermaid'
    this.preview.setAttribute('aria-busy', 'false')
    if (this.preview.hidden) {
      this.preview.replaceChildren()
      return
    }
    const source = this.editorInstance?.getValue() ?? this._codeBlockData.html
    if (!source.trim()) {
      this.preview.textContent = this.api.i18n.t(
        'Enter Mermaid code to preview the diagram.',
      )
      return
    }
    this.preview.setAttribute('aria-busy', 'true')
    if (!this.preview.querySelector('svg')) {
      this.preview.textContent = this.api.i18n.t('Loading preview…')
    }
    this.previewTimer = setTimeout(() => void this.renderPreview(source, revision), 300)
  }

  private async renderPreview(source: string, revision: number): Promise<void> {
    try {
      const { renderMermaid } = await import(/* @vite-ignore */ this.mermaidUrl)
      const svg: string = await renderMermaid(source)
      if (revision !== this.previewRevision || !this.preview) return
      const diagram = make.element('div', ['pw-mermaid', 'not-prose'])
      diagram.innerHTML = svg
      this.preview.replaceChildren(diagram)
      this.preview.setAttribute('aria-busy', 'false')
    } catch (error) {
      if (revision !== this.previewRevision || !this.preview) return
      const message = make.element('p')
      message.textContent = this.api.i18n.t('Unable to render the Mermaid diagram.')
      const details = make.element('pre')
      details.textContent = error instanceof Error ? error.message : String(error)
      this.preview.replaceChildren(message, details)
      this.preview.setAttribute('aria-busy', 'false')
    }
  }

  destroy(): void {
    ++this.previewRevision
    clearTimeout(this.previewTimer)
    this.contentListener?.dispose()
    super.destroy()
  }

  save(): { html: string; language: string } {
    if (this.editorInstance) {
      this._codeBlockData.html = this.editorInstance.getValue()
    }

    return this._codeBlockData
  }

  static get toolbox() {
    return {
      icon: IconCurlyBrackets,
      title: 'Code',
    }
  }

  /**
   * Export block data to Markdown
   * @param {CodeBlockData} data - Block data
   * @param {BlockTuneData} tunes - Block tunes
   * @returns {string} Markdown representation
   */
  static exportToMarkdown(data: CodeBlockData, _tunes?: BlockTuneData): string {
    if (!data || !data.html) {
      return ''
    }

    const language = data.language || ''
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
