import './index.css'
import make from '../utils/make'
import ToolboxIcon from './toolbox-icon.svg?raw'
import ajax from '@codexteam/ajax'
import { MarkdownUtils } from '../utils/MarkdownUtils'
import { API, BlockToolData } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import { Suggest } from '../../../../../admin/src/Resources/assets/suggest.js'
import { BaseTool } from '../Abstract/BaseTool'
import { BLOCK_STATE, StateBlock, StateBlockToolInterface } from '../utils/StateBlock'
import { exportPagesListToMarkdown } from './PagesListExportToMarkdown'

export interface PagesListData extends BlockToolData {
  kw: string
  display: string
  order: string
  max: string
  maxPages: string
}

export interface PagesListDataToNormalize extends BlockToolData {
  kw?: string
  display?: string
  order?: string
  max?: string
  maxPages?: string
}

export interface PagesListNodes {
  kwInput?: HTMLElement
  suggester?: HTMLElement
  displaySelect?: HTMLSelectElement
  maxInput?: HTMLElement
  maxPagesInput?: HTMLElement
  orderSelect?: HTMLSelectElement

  // from state block
  preview?: HTMLElement
  inputs?: HTMLElement
  editBtn?: HTMLElement
  editInput?: HTMLInputElement
}

export interface PagesListConfig {
  preview: string
}

export default class PagesList extends BaseTool implements StateBlockToolInterface {
  declare public nodes: PagesListNodes
  declare public config: PagesListConfig

  public static toolbox = {
    title: 'Pages',
    icon: ToolboxIcon,
  }

  public static defaultData: PagesListData = {
    kw: '',
    display: 'list',
    order: 'publishedAt ↓',
    max: '9',
    maxPages: '0',
  }

  constructor({
    data,
    api,
    readOnly,
    config,
  }: {
    data: PagesListData
    api: API
    readOnly: boolean
    config: PagesListConfig
  }) {
    super({ data, api, readOnly })

    this.config = config

    this.data = {
      kw: data.kw || PagesList.defaultData.kw,
      display: data.display || PagesList.defaultData.display,
      order: data.order || PagesList.defaultData.order,
      max: data.max || PagesList.defaultData.max,
      maxPages: data.maxPages || PagesList.defaultData.maxPages,
    }

    // Initialiser la propriété nodes pour StateBlock
    this.nodes = {}
  }

  private getTags(): string[] {
    let tags = [
      'children',
      'sisters',
      'grandchildren',
      'related',
      'title: exampleSearchValue',
      'content:',
      'slug:',
    ]
    const dataTags = document.querySelector('[data-tags]')
    if (dataTags && dataTags instanceof HTMLElement) {
      tags = JSON.parse(dataTags.dataset.tags || '[]').concat(tags)
    }
    if (window.pagesUriList) {
      tags = tags.concat(
        window.pagesUriList.map((str: string) => str.replace(/^\//, 'slug:')),
      )
    }
    return tags
  }

  public createInputs(): HTMLElement {
    this.nodes.kwInput = make.input(
      this,
      ['cdx-input-labeled', 'cdx-input-labeled-pageslist-kw', this.api.styles.input],
      '...',
      this.data.kw,
    )
    this.nodes.suggester = make.element('div', 'textSuggester', {
      style: 'display:none',
    })

    const list = this.getTags() // this.nodes.kwInput.dataset.tags = this.getTags()
    const options = {
      highlight: true,
      dispMax: 10,
      dispAllKey: true,
      hookSearchResults: 'suggestSearchHookForPageTags',
    }
    // @ts-ignore
    new Suggest.LocalMulti(this.nodes.kwInput, this.nodes.suggester, list, options)

    this.nodes.displaySelect = document.createElement('select')
    this.nodes.displaySelect.classList.add('cdx-select')
    this.nodes.displaySelect.classList.add('mr-5px')
    make.option(this.nodes.displaySelect, '', 'format', { disabled: true })
    make.options(this.nodes.displaySelect, ['list', 'card'], this.data.display)
    this.nodes.displaySelect.value = this.data.display

    const detailsWrapper = make.element('div', ['flex'])
    detailsWrapper.style.marginBottom = '15px'

    this.nodes.maxInput = make.input(
      this,
      [
        'cdx-input-labeled',
        'cdx-input-labeled-pageslist-max',
        'text-right',
        this.api.styles.input,
      ],
      '9',
      this.data.max,
    )
    this.nodes.maxInput.title = 'max Items per Page'

    this.nodes.maxPagesInput = make.input(
      this,
      [
        'cdx-input-labeled',
        'cdx-input-labeled-pageslist-maxpages',
        'text-right',
        this.api.styles.input,
      ],
      '1',
      this.data.maxPages,
    )
    this.nodes.maxPagesInput.title = 'max Pages'

    detailsWrapper.appendChild(this.nodes.displaySelect)
    detailsWrapper.appendChild(this.createOrderSelect())
    detailsWrapper.appendChild(this.nodes.maxInput)
    detailsWrapper.appendChild(this.nodes.maxPagesInput)

    const inputsWrapper = make.element('div')
    inputsWrapper.appendChild(this.nodes.kwInput)
    inputsWrapper.appendChild(this.nodes.suggester)
    inputsWrapper.appendChild(detailsWrapper)

    return inputsWrapper
  }

  private createOrderSelect(): HTMLElement {
    const select = document.createElement('select')
    select.classList.add('cdx-select')
    make.option(select, '', 'orderBy', { disabled: true })
    make.option(select, 'publishedAt ↓', null, {}, this.data.order)
    make.option(select, 'weight ↓, publishedAt ↓', null, {}, this.data.order)
    make.option(select, 'publishedAt ↑', null, {}, this.data.order)
    select.value = this.data.order
    this.nodes.orderSelect = select
    return this.nodes.orderSelect
  }

  public show(state: number): void {
    if (state === BLOCK_STATE.VIEW) {
      if (!this.validate()) {
        this.api.notifier.show({
          message: this.api.i18n.t(
            'Something is missing to properly render the the pages list.',
          ),
          style: 'error',
        })
        StateBlock.show(this, BLOCK_STATE.EDIT)
        return
      }
      StateBlock.show(this, state)
    }
  }

  protected updateData(): void {
    this.data.kw = this.nodes?.kwInput?.textContent || this.data.kw
    this.data.display = this.nodes?.displaySelect?.value || this.data.display
    this.data.order = this.nodes?.orderSelect?.value || this.data.order
    this.data.max = this.nodes?.maxInput?.textContent || this.data.max
    this.data.maxPages = this.nodes?.maxPagesInput?.textContent || this.data.maxPages
  }

  public render(): HTMLElement {
    return StateBlock.render(this)
  }

  public save(): PagesListData {
    this.updateData()
    return this.data
  }

  public validate(): boolean {
    this.updateData()
    return !!this.data.kw
  }

  public updatePreview(): void {
    this.updateData()
    if (!this.nodes.preview) {
      console.warn('Preview node not yet created')
      return
    }
    this.getPreviewFromServer()
  }

  private getPreviewFromServer(): void {
    this.updateData()
    const self = this
    ajax
      .post({
        url: this.config.preview,
        data: this.data,
        type: ajax.contentType.JSON,
      })
      .then(function (response: any) {
        self.setPreviewContent(response.body.content)
      })
      .catch(function (error: any) {
        console.log('getPreviewFromServer error', error)
        self.setPreviewContent('An error occured (see console log for more info)')
      })
  }

  private setPreviewContent(content: string): void {
    if (this.nodes.preview) {
      this.nodes.preview.innerHTML =
        '<div class="preview-wrapper-overlay"></div>' + content
    }
  }

  public static exportToMarkdown(data: PagesListData, tunes?: BlockTuneData): string {
    return exportPagesListToMarkdown(data, tunes)
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown)
    let tunes: BlockTuneData = result.tunes
    markdown = result.markdown

    const properties = MarkdownUtils.extractTwigFunctionProperties('pages_list', markdown)
    if (!properties) return

    const data: PagesListData = {
      kw: properties[0] || PagesList.defaultData.kw,
      display: properties[3] || PagesList.defaultData.display,
      order: properties[2] || PagesList.defaultData.order,
      max: properties[1] || PagesList.defaultData.max,
      maxPages: properties[4] || PagesList.defaultData.maxPages,
    }

    tunes.class = properties[5] || ''
    tunes.anchor = properties[6] || ''

    const block = editor.blocks.insert('pages_list', data)
    editor.blocks.update(block.id, data, tunes)
  }

  static isItMarkdownExported(markdown: string): boolean {
    const properties = MarkdownUtils.extractTwigFunctionProperties('pages_list', markdown)
    return properties !== null
  }
}
