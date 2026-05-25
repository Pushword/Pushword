import './Snippet.css'
import make from '../utils/make'
import ToolboxIcon from './toolbox-icon.svg?raw'
import { MarkdownUtils } from '../utils/MarkdownUtils'
import { API, BlockToolData } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import { BaseTool } from '../Abstract/BaseTool'
import { BLOCK_STATE, StateBlock, StateBlockToolInterface } from '../utils/StateBlock'

interface SnippetSchemaField {
  type?: string
  label?: string
  options?: string[] | Record<string, string>
}

interface SnippetDefinition {
  label: string
  schema: Record<string, SnippetSchemaField>
}

export interface SnippetData extends BlockToolData {
  name: string
  params: Record<string, any>
}

export interface SnippetConfig {
  definitions?: Record<string, SnippetDefinition>
}

interface SnippetNodes {
  nameSelect?: HTMLSelectElement
  fields?: HTMLElement
  // from state block
  preview?: HTMLElement
  inputs?: HTMLElement
  editBtn?: HTMLElement
  editInput?: HTMLInputElement
}

/**
 * Block for the `snippet('name', {params})` Twig function. Lists the snippets
 * declared by the server (content snippets + dev components) and renders a
 * schema-driven form for the selected one's params.
 */
export default class Snippet extends BaseTool implements StateBlockToolInterface {
  declare public nodes: SnippetNodes
  declare public config: SnippetConfig
  declare public data: SnippetData

  private definitions: Record<string, SnippetDefinition>
  /** key → reader returning the current param value */
  private fieldReaders: Record<string, () => any> = {}
  /** stable suffix so this block's field ids never collide with another block's */
  private readonly uid = Math.random().toString(36).slice(2, 8)

  public static toolbox = {
    title: 'Snippet',
    icon: ToolboxIcon,
  }

  constructor({
    data,
    api,
    readOnly,
    config,
  }: {
    data: SnippetData
    api: API
    readOnly: boolean
    config: SnippetConfig
  }) {
    super({ data, api, readOnly })

    this.config = config || {}
    this.definitions = this.config.definitions || {}
    this.data = {
      name: data.name || '',
      params: data.params || {},
    }
    this.nodes = {}
  }

  public render(): HTMLElement {
    return StateBlock.render(this)
  }

  public createInputs(): HTMLElement {
    const wrapper = make.element('div', 'cdx-snippet')

    const header = make.element(
      'div',
      'cdx-snippet__header',
      {},
      ToolboxIcon + `<span>${this.api.i18n.t('Snippet')}</span>`,
    )
    wrapper.appendChild(header)

    this.nodes.nameSelect = make.element('select', 'cdx-snippet__select', {
      'aria-label': this.api.i18n.t('Snippet'),
    }) as HTMLSelectElement
    make.option(this.nodes.nameSelect, '', this.api.i18n.t('Choose a snippet…'), {
      disabled: true,
    })

    const names = Object.keys(this.definitions)
    // Keep a previously-saved name selectable even if not advertised by the server.
    if (this.data.name && !names.includes(this.data.name)) {
      names.unshift(this.data.name)
    }
    names.forEach((name) => {
      make.option(
        this.nodes.nameSelect!,
        name,
        this.definitions[name]?.label || name,
        {},
        this.data.name,
      )
    })
    this.nodes.nameSelect.value = this.data.name

    this.nodes.nameSelect.addEventListener('change', () => {
      this.data.name = this.nodes.nameSelect!.value
      this.data.params = {}
      this.buildFields()
    })

    this.nodes.fields = make.element('div', 'cdx-snippet__inputs')

    wrapper.appendChild(this.nodes.nameSelect)
    wrapper.appendChild(this.nodes.fields)

    this.buildFields()

    return wrapper
  }

  /** (Re)build the param form for the currently selected snippet. */
  private buildFields(): void {
    if (!this.nodes.fields) return

    this.nodes.fields.innerHTML = ''
    this.fieldReaders = {}

    const schema = this.definitions[this.data.name]?.schema || {}
    const keys = Object.keys(schema)

    if (keys.length === 0) {
      this.buildJsonField()
      return
    }

    keys.forEach((key) => this.buildSchemaField(key, schema[key]))
  }

  private buildSchemaField(key: string, field: SnippetSchemaField): void {
    const value = this.data.params[key]
    const type = field.type || 'string'
    const id = `cdx-snippet-${this.uid}-${key}`

    const wrapper = make.element(
      'div',
      type === 'bool' ? ['cdx-snippet__field', 'cdx-snippet__field--bool'] : 'cdx-snippet__field',
    )
    const label = make.element('label', null, { for: id }, field.label || key)

    let control: HTMLElement
    if (type === 'bool') {
      const input = make.element('input', null, { type: 'checkbox', id }) as HTMLInputElement
      input.checked = value === true || value === 'true'
      this.fieldReaders[key] = () => (input.checked ? true : undefined)
      control = input
    } else if (type === 'text') {
      const input = make.element('textarea', null, { id }) as HTMLTextAreaElement
      input.value = value ?? ''
      this.fieldReaders[key] = () => (input.value !== '' ? input.value : undefined)
      control = input
    } else if (type === 'select') {
      const select = make.element('select', null, { id }) as HTMLSelectElement
      const options = this.normalizeOptions(field.options)
      Object.keys(options).forEach((optValue) =>
        make.option(select, optValue, options[optValue], {}, value),
      )
      select.value = value ?? ''
      this.fieldReaders[key] = () => (select.value !== '' ? select.value : undefined)
      control = select
    } else if (type === 'number') {
      const input = make.element('input', null, { type: 'number', id }) as HTMLInputElement
      input.value = value ?? ''
      this.fieldReaders[key] = () => (input.value !== '' ? Number(input.value) : undefined)
      control = input
    } else {
      const input = make.element('input', null, { type: 'text', id }) as HTMLInputElement
      input.value = value ?? ''
      this.fieldReaders[key] = () => (input.value !== '' ? input.value : undefined)
      control = input
    }

    // Checkbox reads left-to-right (control then label); other fields stack label above.
    wrapper.append(...(type === 'bool' ? [control, label] : [label, control]))
    this.nodes.fields!.appendChild(wrapper)
  }

  /** Fallback editor for snippets without a schema (content snippets): raw JSON params. */
  private buildJsonField(): void {
    if (!this.data.name) return

    const id = `cdx-snippet-${this.uid}-json`
    const wrapper = make.element('div', 'cdx-snippet__field')
    wrapper.appendChild(
      make.element('label', null, { for: id }, this.api.i18n.t('Parameters (JSON, optional)')),
    )
    const textarea = make.element('textarea', null, { id }) as HTMLTextAreaElement
    textarea.value =
      Object.keys(this.data.params).length > 0
        ? JSON.stringify(this.data.params, null, 2)
        : ''
    textarea.placeholder = '{ "key": "value" }'
    wrapper.appendChild(textarea)

    this.fieldReaders['__json__'] = () => {
      const raw = textarea.value.trim()
      if (raw === '') return undefined
      try {
        return JSON.parse(raw)
      } catch {
        return undefined
      }
    }

    this.nodes.fields!.appendChild(wrapper)
  }

  private normalizeOptions(
    options?: string[] | Record<string, string>,
  ): Record<string, string> {
    if (!options) return {}
    if (Array.isArray(options)) {
      const result: Record<string, string> = {}
      options.forEach((opt) => (result[opt] = opt))
      return result
    }
    return options
  }

  protected updateData(): void {
    if (this.nodes.nameSelect) {
      this.data.name = this.nodes.nameSelect.value
    }

    const params: Record<string, any> = {}
    Object.keys(this.fieldReaders).forEach((key) => {
      const value = this.fieldReaders[key]()
      if (value === undefined) return
      if (key === '__json__') {
        Object.assign(params, value)
        return
      }
      params[key] = value
    })
    this.data.params = params
  }

  public validate(): boolean {
    return !!this.data.name
  }

  public save(): SnippetData {
    this.updateData()
    return this.data
  }

  public show(state: number): void {
    if (state === BLOCK_STATE.VIEW && !this.validate()) {
      this.api.notifier.show({
        message: this.api.i18n.t('Choose a snippet first.'),
        style: 'error',
      })
      StateBlock.show(this, BLOCK_STATE.EDIT)
      return
    }
    StateBlock.show(this, state)
  }

  public updatePreview(): void {
    if (!this.nodes.preview) return
    // Render from current data only. StateBlock calls this before createInputs()
    // exists, so reading the (absent) fields here would wipe the saved params.

    const label = this.definitions[this.data.name]?.label || this.data.name
    const paramKeys = Object.keys(this.data.params)
    const paramsSummary =
      paramKeys.length > 0
        ? paramKeys.map((key) => `${key}: ${this.data.params[key]}`).join(' · ')
        : this.api.i18n.t('No parameters')

    // Collapsed view of the same panel as the edit form: SNIPPET eyebrow, then
    // the snippet label and a one-line param summary. Params hold user content,
    // so escape before injecting.
    this.nodes.preview.innerHTML =
      '<div class="cdx-snippet cdx-snippet--preview">' +
      `<div class="cdx-snippet__header">${ToolboxIcon}<span>${this.api.i18n.t('Snippet')}</span></div>` +
      '<div class="cdx-snippet-preview__body">' +
      `<span class="cdx-snippet-preview__label">${Snippet.escapeHtml(label || '—')}</span>` +
      `<span class="cdx-snippet-preview__params">${Snippet.escapeHtml(paramsSummary)}</span>` +
      '</div></div>'
  }

  private static escapeHtml(value: string): string {
    return value
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
  }

  public static exportToMarkdown(data: SnippetData, tunes?: BlockTuneData): string {
    if (!data || !data.name) return ''

    const markdown = MarkdownUtils.buildSnippetCall(data.name, data.params || {})
    return MarkdownUtils.addAttributes(markdown, tunes ?? {})
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown)
    const tunes: BlockTuneData = result.tunes

    const call = MarkdownUtils.extractSnippetCall(result.markdown)
    if (!call) return

    const data: SnippetData = { name: call.name, params: call.params }
    const block = editor.blocks.insert('snippet', data)
    editor.blocks.update(block.id, data, tunes)
  }

  static isItMarkdownExported(markdown: string): boolean {
    return MarkdownUtils.isSnippetBlock(markdown)
  }
}
