import './HyperlinkTune.css'
import make from '../utils/make'
import { IconLink } from '@codexteam/icons'
import { API, BlockAPI, BlockToolData } from '@editorjs/editorjs'
import { BaseTool } from '../Abstract/BaseTool'

export interface HyperlinkTuneData extends BlockToolData {
  url?: string
  hideForBot?: boolean
  targetBlank?: boolean
}

export interface HyperlinkTuneNodes extends Record<string, HTMLElement | null> {
  url?: HTMLElement
  hideForBot?: HTMLElement
  targetBlank?: HTMLElement
}

export default class HyperlinkTune extends BaseTool {
  protected data: HyperlinkTuneData
  private block: BlockAPI
  protected nodes: HyperlinkTuneNodes

  public static get isTune(): boolean {
    return true
  }

  constructor({
    api,
    data,
    block,
    readOnly,
  }: {
    api: API
    data: HyperlinkTuneData
    block: BlockAPI
    readOnly: boolean
  }) {
    super({ api, data, readOnly })
    this.data = {
      url: data?.url || '',
      hideForBot: data?.hideForBot || false,
      targetBlank: data?.targetBlank || false,
    }
    this.block = block
    this.nodes = {}
  }

  public render(value: any = null): HTMLElement {
    console.log(this.data, value)
    const wrapper = document.createElement('div')
    wrapper.classList.add('cdx-anchor-tune-wrapper')
    wrapper.style.display = 'block'
    wrapper.style.position = 'relative'

    const wrapperIcon = document.createElement('div')
    wrapperIcon.classList.add('cdx-anchor-tune-icon')
    wrapperIcon.style.position = 'absolute'
    wrapperIcon.style.left = '-2px'
    wrapperIcon.style.width = '25px'
    wrapperIcon.style.opacity = '0.9'
    wrapperIcon.style.height = '25px'
    wrapperIcon.innerHTML = IconLink
    wrapper.appendChild(wrapperIcon)

    this.nodes.url = make.input(
      this as any,
      ['cdx-input-labeled', 'cdx-input-full'],
      ':self OR /url',
      this.data.url,
    )
    this.nodes.url.style.backgroundColor = 'white'
    this.nodes.url.style.borderRadius = '6px'
    this.nodes.url.style.padding = '4px'
    this.nodes.url.style.paddingLeft = '22px'
    this.nodes.url.style.fontSize = '14px'

    this.nodes.hideForBot = make.switchInput(
      'hideForBot',
      this.api.i18n.t('Obfusquer'),
      this.data.hideForBot || false,
    )
    this.nodes.targetBlank = make.switchInput(
      'targetBlank',
      this.api.i18n.t('Nouvel onglet'),
      this.data.targetBlank || false,
    )

    wrapper.appendChild(this.nodes.url)
    wrapper.appendChild(this.nodes.hideForBot)
    wrapper.appendChild(this.nodes.targetBlank)

    // on change, save
    // Using multiple events for contentEditable elements
    const urlChangeHandler = () => {
      this._updateData()
    }

    this.nodes.url.addEventListener('input', urlChangeHandler)
    this.nodes.url.addEventListener('blur', urlChangeHandler)
    this.nodes.url.addEventListener('keyup', urlChangeHandler)
    this.nodes.hideForBot.addEventListener('change', () => {
      this._updateData()
    })
    this.nodes.targetBlank.addEventListener('change', () => {
      this._updateData()
    })

    return wrapper
  }

  /**
   * Return tool's data
   * @returns {*}
   */
  public save(): HyperlinkTuneData {
    return {
      url: this.data.url ?? '',
      hideForBot: this.data.hideForBot ?? true,
      targetBlank: this.data.targetBlank ?? false,
    }
  }

  private _updateData(): HyperlinkTuneData {
    this.data.url = this.nodes.url?.textContent || ''
    this.data.hideForBot = this.nodes.hideForBot?.querySelector('input')?.checked || false
    this.data.targetBlank =
      this.nodes.targetBlank?.querySelector('input')?.checked || false

    console.log(this.block)
    this.block?.dispatchChange()
    console.log(this.api)
    this.api.saver.save()

    return this.data
  }
}
