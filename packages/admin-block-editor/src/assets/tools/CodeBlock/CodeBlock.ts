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
  private readonly mermaidUrl: string
  private languageSelect?: HTMLSelectElement
  private element?: HTMLElement
  private sourcePanel?: HTMLElement
  private editButton?: HTMLButtonElement
  private fullscreenButton?: HTMLButtonElement
  private fullscreenDialog?: HTMLDialogElement
  private previewStatus?: HTMLElement
  private previewError?: HTMLElement
  private editing = false
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
    this.data.language = data?.language || 'html'
  }

  render(): HTMLElement {
    const code = super.render()
    this.element = make.element('div', 'pw-code-block')
    this.sourcePanel = make.element('div', 'pw-code-source', {
      id: make.uniqueId('pw-mermaid-source'),
    })
    const label = make.element('label', 'pw-code-language')
    label.append(this.api.i18n.t('Language'))
    const select = make.element('select', this.api.styles.input) as HTMLSelectElement
    make.options(select, ['html', 'twig', 'javascript', 'php', 'json', 'yaml', 'mermaid'])
    this.languageSelect = select
    select.disabled = this.readOnly
    select.addEventListener('change', () => {
      this.data.language = select.value
      this.editing = this.data.language === 'mermaid'
      this.updateLanguage()
      this.schedulePreview()
    })
    label.append(select)
    this.previewError = make.element('div', 'pw-mermaid-error', {
      id: make.uniqueId('pw-mermaid-error'),
      role: 'alert',
    })
    this.previewError.hidden = true
    code.setAttribute('aria-describedby', this.previewError.id)
    this.sourcePanel.append(label, code, this.previewError)
    this.sourcePanel.addEventListener('input', (event) => {
      if (event.target instanceof HTMLTextAreaElement && !this.editorInstance) {
        this.schedulePreview()
      }
    })
    this.element.addEventListener('keydown', (event) => {
      if (event.isComposing || this.readOnly) return
      if (this.fullscreenDialog?.open && event.key === 'Enter' &&
        (event.ctrlKey || event.metaKey) && !event.altKey) this.closeFullscreen()
      if (event.key !== 'Escape' || !this.editing) return
      event.preventDefault()
      event.stopPropagation()
      if (this.fullscreenDialog?.open) this.closeFullscreen(true)
      else this.setEditing(false, true)
    }, { capture: true })

    const toolbar = make.element('div', 'pw-mermaid-toolbar')
    const title = make.element('span', 'pw-mermaid-title')
    title.textContent = 'Mermaid'
    this.previewStatus = make.element('span', 'pw-mermaid-status', { role: 'status' })
    this.editButton = make.element('button', 'pw-mermaid-edit', {
      type: 'button',
      'aria-controls': this.sourcePanel.id,
    }) as HTMLButtonElement
    this.editButton.hidden = this.readOnly
    this.editButton.addEventListener('click', () => this.setEditing(!this.editing, true))
    this.fullscreenDialog = make.element('dialog', 'pw-mermaid-fullscreen-dialog', {
      id: make.uniqueId('pw-mermaid-fullscreen'),
      'aria-label': this.api.i18n.t('Mermaid editor'),
    }) as HTMLDialogElement
    this.fullscreenDialog.addEventListener('cancel', (event) => {
      event.preventDefault()
      this.closeFullscreen(true)
    })
    this.fullscreenDialog.addEventListener('keydown', (event) => {
      if (event.key !== 'Tab' || event.ctrlKey || event.metaKey || event.altKey) return
      const wrapBackward = event.shiftKey && document.activeElement === this.editButton
      const wrapForward = !event.shiftKey && document.activeElement === this.preview
      if (!wrapBackward && !wrapForward) return
      event.preventDefault()
      event.stopPropagation()
      if (wrapBackward) this.preview?.focus()
      else this.editButton?.focus()
    })
    this.fullscreenButton = make.element('button', 'pw-mermaid-fullscreen', {
      type: 'button',
      'aria-controls': this.fullscreenDialog.id,
    }) as HTMLButtonElement
    this.fullscreenButton.addEventListener('click', () => this.toggleFullscreen())
    this.updateFullscreenButton()
    toolbar.append(title, this.previewStatus, this.editButton, this.fullscreenButton)
    this.preview = make.element('div', 'pw-mermaid-preview', {
      role: 'region',
      'aria-label': this.api.i18n.t('Diagram preview'),
      tabindex: '0',
    })
    this.preview.addEventListener('keydown', (event) => {
      // Preserve native diagram scrolling instead of Editor.js block navigation.
      if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'PageUp', 'PageDown'].includes(event.key)) {
        event.stopPropagation()
      }
    })
    const body = make.element('div', 'pw-code-content')
    body.append(this.sourcePanel, this.preview)
    this.element.append(toolbar, body, this.fullscreenDialog)
    this.editing = this.data.language === 'mermaid' && !this.data.html.trim() && !this.readOnly
    this.updateLanguage()
    this.schedulePreview()
    return this.element
  }

  instantiateEditor(element: HTMLElement): editor.IStandaloneCodeEditor {
    const instance = super.instantiateEditor(element)
    instance.updateOptions({ readOnly: this.readOnly })
    this.updateLanguage(instance)
    this.updateSourceAccessibility()
    this.contentListener = instance.onDidChangeModelContent(() => this.schedulePreview())
    return instance
  }

  private setEditing(editing: boolean, focus = false): void {
    this.editing = editing
    const mermaid = this.data.language === 'mermaid'
    if (!editing || !mermaid) this.closeFullscreen()
    if (this.fullscreenButton) this.fullscreenButton.hidden = !mermaid || !editing || this.readOnly
    if (this.element) {
      this.element.dataset.language = this.data.language
      this.element.dataset.editing = String(editing)
    }
    if (this.sourcePanel) this.sourcePanel.hidden = mermaid && !editing
    if (this.preview) this.preview.hidden = !mermaid
    if (this.editButton) {
      this.editButton.textContent = this.api.i18n.t(editing ? 'Done' : 'Edit diagram')
      this.editButton.setAttribute('aria-expanded', String(editing))
    }
    this.editorInstance?.layout()
    if (!focus) return
    if (!editing) {
      this.editButton?.focus()
    } else if (this.editorInstance) {
      this.editorInstance.focus()
    } else {
      this.wrapper?.querySelector('textarea')?.focus()
    }
  }

  private toggleFullscreen(): void {
    if (!this.editing || this.data.language !== 'mermaid' || this.readOnly) return
    const dialog = this.fullscreenDialog!
    if (dialog.open) {
      this.closeFullscreen(true)
      return
    }
    // Keep the workspace under its Editor.js block so save, history and shortcuts retain their owner.
    dialog.append(
      this.element!.querySelector('.pw-mermaid-toolbar')!,
      this.element!.querySelector('.pw-code-content')!,
    )
    dialog.showModal()
    this.updateFullscreenButton()
    this.editorInstance?.layout()
    if (this.editorInstance) this.editorInstance.focus()
    else this.wrapper?.querySelector('textarea')?.focus()
  }

  private closeFullscreen(focus = false): void {
    const dialog = this.fullscreenDialog
    if (!dialog?.open) return
    dialog.before(...Array.from(dialog.children))
    dialog.close()
    this.updateFullscreenButton()
    this.editorInstance?.layout()
    if (focus) this.fullscreenButton?.focus({ preventScroll: true })
  }

  private updateFullscreenButton(): void {
    const button = this.fullscreenButton!
    const fullscreen = this.fullscreenDialog!.open
    const label = this.api.i18n.t(fullscreen ? 'Exit fullscreen' : 'Enter fullscreen')
    button.setAttribute('aria-label', label)
    button.setAttribute('aria-pressed', String(fullscreen))
    button.title = label
    button.replaceChildren(make.element('i', [
      'fa-solid', fullscreen ? 'fa-compress' : 'fa-expand',
    ], { 'aria-hidden': 'true' }))
  }

  private updateLanguage(instance = this.editorInstance): void {
    if (this.wrapper) this.wrapper.dataset.language = this.data.language
    if (this.languageSelect) {
      if (
        !Array.from(this.languageSelect.options).some(
          (option) => option.value === this.data.language,
        )
      ) {
        make.option(this.languageSelect, this.data.language)
      }
      this.languageSelect.value = this.data.language
    }
    const sourceLabel = this.api.i18n.t(this.data.language === 'mermaid' ? 'Mermaid source' : 'Code')
    this.wrapper?.querySelector('.editorjs-monaco-preview')?.setAttribute('aria-label', sourceLabel)
    instance?.updateOptions({ ariaLabel: sourceLabel })
    const model = instance?.getModel()
    if (model) {
      window.monaco?.editor.setModelLanguage(model, this.data.language)
    }
    this.setEditing(this.editing)
  }

  private clearPreviewError(): void {
    if (this.previewError) {
      this.previewError.hidden = true
      this.previewError.replaceChildren()
    }
    if (this.element) delete this.element.dataset.previewError
    this.updateSourceAccessibility()
  }

  private updateSourceAccessibility(): void {
    if (!this.previewError) return
    const error = this.previewError
    this.wrapper?.querySelectorAll('textarea, [role="textbox"]').forEach((input) => {
      input.setAttribute('aria-describedby', error.id)
      if (error.hidden) input.removeAttribute('aria-invalid')
      else input.setAttribute('aria-invalid', 'true')
    })
  }

  private schedulePreview(): void {
    clearTimeout(this.previewTimer)
    const revision = ++this.previewRevision
    if (!this.preview) return
    this.preview.setAttribute('aria-busy', 'false')
    this.clearPreviewError()
    if (this.data.language !== 'mermaid') {
      this.preview.replaceChildren()
      return
    }
    const source = this.editorInstance?.getValue() ?? this.data.html
    if (!source.trim()) {
      this.preview.textContent = this.api.i18n.t(
        'Enter Mermaid code to preview the diagram.',
      )
      this.previewStatus!.textContent = ''
      return
    }
    this.preview.setAttribute('aria-busy', 'true')
    this.previewStatus!.textContent = this.api.i18n.t('Loading preview…')
    this.previewTimer = setTimeout(() => void this.renderPreview(source, revision), 300)
  }

  private async renderPreview(source: string, revision: number): Promise<void> {
    try {
      const { renderMermaid } = await import(/* @vite-ignore */ this.mermaidUrl)
      const svg: string = await renderMermaid(source)
      if (revision !== this.previewRevision || !this.preview) return
      const diagram = make.element('div', ['pw-mermaid', 'not-prose'])
      diagram.innerHTML = svg
      const viewBox = diagram.querySelector('svg')?.getAttribute('viewBox')
      const width = Number(viewBox?.trim().split(/[\s,]+/)[2])
      // Limit shrinking to 15% so the renderer's labels remain readable.
      if (width > 0) diagram.style.setProperty('--pw-mermaid-min-width', `${width * 0.85}px`)
      this.preview.replaceChildren(diagram)
      this.clearPreviewError()
      this.previewStatus!.textContent = this.api.i18n.t('Live preview')
    } catch (error) {
      if (revision !== this.previewRevision || !this.preview) return
      const message = make.element('p')
      message.textContent = this.api.i18n.t('Unable to render the Mermaid diagram.')
      const details = make.element('pre')
      details.textContent = error instanceof Error ? error.message : String(error)
      this.previewError!.replaceChildren(message, details)
      this.previewError!.hidden = false
      this.element!.dataset.previewError = 'true'
      this.updateSourceAccessibility()
      const hasPreview = Boolean(this.preview.querySelector('svg'))
      this.previewStatus!.textContent = this.api.i18n.t(
        hasPreview ? 'Last valid preview' : 'Preview unavailable',
      )
      if (!hasPreview && !this.readOnly) this.setEditing(true)
    } finally {
      if (revision === this.previewRevision) this.preview?.setAttribute('aria-busy', 'false')
    }
  }

  destroy(): void {
    this.closeFullscreen()
    ++this.previewRevision
    clearTimeout(this.previewTimer)
    this.contentListener?.dispose()
    super.destroy()
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
