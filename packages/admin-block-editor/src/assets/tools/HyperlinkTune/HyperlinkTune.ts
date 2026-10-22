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

export interface HyperlinkTuneNodes extends Record<string, HTMLElement | null | undefined> {
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

  public render(_value: unknown = null): HTMLElement {
    const wrapper = document.createElement('div')
    wrapper.classList.add('cdx-anchor-tune-wrapper')

    const label = make.element('div', 'cdx-anchor-tune-label', {
      id: make.uniqueId('image-link-label'),
    })
    label.appendChild(make.element('span', 'cdx-anchor-tune-icon', { 'aria-hidden': 'true' }, IconLink))
    const title = make.element('span')
    title.textContent = this.api.i18n.t('Link')
    label.appendChild(title)
    wrapper.appendChild(label)

    this.nodes.url = make.input(
      this as any,
      ['cdx-anchor-tune-input', 'cdx-input-full'],
      'https://…',
      this.data.url,
    )
    this.nodes.url.setAttribute('role', 'textbox')
    this.nodes.url.setAttribute('aria-labelledby', label.id)

    this.nodes.hideForBot = make.switchInput(
      'hideForBot',
      this.api.i18n.t('Obfuscate'),
      this.data.hideForBot || false,
    )
    this.nodes.targetBlank = make.switchInput(
      'targetBlank',
      this.api.i18n.t('New tab'),
      this.data.targetBlank || false,
    )

    wrapper.appendChild(this.nodes.url)
    wrapper.appendChild(this.nodes.hideForBot)
    const help = make.element('small', 'cdx-anchor-tune-help', {
      id: make.uniqueId('image-link-help'),
    })
    help.textContent = this.api.i18n.t('Mask the link in the page source.')
    this.nodes.hideForBot.querySelector('input')!.setAttribute('aria-describedby', help.id)
    wrapper.appendChild(help)
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

    this.block?.dispatchChange()
    this.api.saver.save()

    return this.data
  }
}
