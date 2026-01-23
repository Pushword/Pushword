/**
 * Original author Volgador
 * https://github.com/VolgaIgor/editorjs-anchor
 */

import { API, BlockAPI, BlockTool } from '@editorjs/editorjs'
import './Anchor.css'
import HashIcon from './Hash.svg?raw'
import make from '../utils/make'

export default class AnchorTune implements BlockTool {
  private api: API
  private data: string
  private block: BlockAPI
  private input?: HTMLInputElement

  static get isTune(): boolean {
    return true
  }

  constructor({ api, data = '', block }: { api: API; data?: string; block: BlockAPI }) {
    this.api = api
    this.data = data
    this.block = block
  }

  render(value: string | null = null): HTMLElement {
    const wrapper = document.createElement('div')
    wrapper.classList.add('cdx-anchor-tune-wrapper')

    const wrapperIcon = make.element('div', 'cdx-anchor-tune-icon', {}, HashIcon)

    this.input = make.element('input', 'cdx-anchor-tune-input', {
      placeholder: this.api.i18n.t('Anchor'),
      value: value ? value : this.data || '',
    }) as HTMLInputElement

    this.input.addEventListener('input', (event: Event) => {
      const target = event.target as HTMLInputElement
      let value = target.value.replace(/[^a-z0-9_-]/gi, '')

      if (value.length > 0) {
        this.data = value
      } else {
        this.data = ''
      }
      this.block?.dispatchChange()
    })

    wrapper.appendChild(wrapperIcon)
    wrapper.appendChild(this.input)

    return wrapper
  }

  save(): string {
    return this.data
  }
}
