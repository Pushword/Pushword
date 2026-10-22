/**
 * Build styles
 */
import { API, BlockAPI } from '@editorjs/editorjs'
import './AlignementTune.css'
import { IconAlignCenter, IconAlignLeft, IconAlignRight } from '@codexteam/icons'

interface AlignmentTuneConfig {
  default?: string
  blocks?: Record<string, string>
}

interface AlignmentTuneSetting {
  name: string
  icon: string
  title: string
}

// tunes.textAlign
export default class AlignmentTune {
  private api: API
  private block: any
  private settings?: AlignmentTuneConfig
  private data: string
  private alignmentSettings: AlignmentTuneSetting[]
  private blockContent?: HTMLElement
  private buttons: HTMLButtonElement[] = []

  /**
   * Default alignment
   *
   * @public
   * @returns {string}
   */
  static get DEFAULT_ALIGNMENT(): string {
    return '' // left
  }

  static get isTune(): boolean {
    return true
  }

  getAlignment(): string {
    if (
      this.settings?.blocks &&
      Object.prototype.hasOwnProperty.call(this.settings.blocks, this.block.name) &&
      typeof this.block.name === 'string'
    ) {
      return this.settings.blocks[this.block.name] ?? AlignmentTune.DEFAULT_ALIGNMENT
    }
    if (this.settings?.default) {
      return this.settings.default
    }
    return AlignmentTune.DEFAULT_ALIGNMENT
  }

  constructor({
    api,
    data,
    config,
    block,
  }: {
    api: API
    data?: string | { textAlign?: string }
    config?: AlignmentTuneConfig
    block?: BlockAPI
  }) {
    this.api = api
    this.block = block
    /**
        config:{
           default: "right",
           blocks: {
             header: 'center',
             list: 'right'
           }
         },
        */
    this.settings = config ?? {}
    this.data = (typeof data === 'string' ? data : data?.textAlign) || this.getAlignment()
    this.alignmentSettings = [
      {
        name: 'left',
        icon: IconAlignLeft,
        title: this.api.i18n.t('Align left'),
      },
      {
        name: 'center',
        icon: IconAlignCenter,
        title: this.api.i18n.t('Align center'),
      },
      {
        name: 'right',
        icon: IconAlignRight,
        title: this.api.i18n.t('Align right'),
      },
    ]
  }

  wrap(blockContent: HTMLElement): HTMLElement {
    this.blockContent = blockContent
    this.blockContent.classList.toggle('text-' + this.data)
    return this.blockContent
  }

  render(): HTMLElement {
    const wrapper = document.createElement('div')
    wrapper.className = 'cdx-alignment-tune'
    this.buttons = this.alignmentSettings.map((tune: AlignmentTuneSetting) => {
      const button = document.createElement('button')
      button.type = 'button'
      button.classList.add('cdx-settings-button')
      button.innerHTML = tune.icon
      button.title = tune.title
      button.setAttribute('aria-label', tune.title)
      button.addEventListener('click', () => this.updateAlign(tune.name))

      wrapper.appendChild(button)

      return button
    })

    this.updateButtonStates()

    return wrapper
  }

  updateAlign(currentAlign: string): void {
    this.data = currentAlign === 'left' ? '' : currentAlign
    this.updateButtonStates()
    this.block?.dispatchChange()

    if (this.blockContent) {
      this.alignmentSettings.forEach((align: AlignmentTuneSetting) => {
        this.blockContent?.classList.toggle(
          'text-' + align.name,
          this.data === align.name,
        )
      })
    }
  }

  save(): string {
    return this.data
  }

  private updateButtonStates(): void {
    this.buttons.forEach((button, index) => {
      const active = this.alignmentSettings[index]?.name === (this.data || 'left')
      button.classList.toggle('cdx-settings-button--active', active)
      button.setAttribute('aria-pressed', String(active))
    })
  }
}
