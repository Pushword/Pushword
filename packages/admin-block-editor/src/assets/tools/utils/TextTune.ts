import { API, BlockAPI } from '@editorjs/editorjs'
import make from './make'

export interface TextTuneOptions {
  api: API
  data?: string
  block: BlockAPI
}

interface TextTuneField {
  /** `input` for a single word, `textarea` for a list of names that may wrap. */
  tag: 'input' | 'textarea'
  icon: string
  /** Translated in the tune's own blockTunes namespace. */
  placeholder: string
  /** What is stored from what was typed; the field keeps showing the raw text. */
  clean: (value: string) => string
}

/** A block tune holding one string, typed into a field of the block settings. */
export default abstract class TextTune {
  private readonly api: API
  private readonly block: BlockAPI
  private data: string | undefined

  static get isTune(): boolean {
    return true
  }

  protected constructor(
    { api, data, block }: TextTuneOptions,
    private readonly field: TextTuneField,
  ) {
    this.api = api
    this.data = data
    this.block = block
  }

  render(): HTMLElement {
    const wrapper = make.element('div', 'cdx-anchor-tune-wrapper')
    wrapper.appendChild(make.element('div', 'cdx-anchor-tune-icon', {}, this.field.icon))

    const input = make.element(this.field.tag, 'cdx-anchor-tune-input', {
      placeholder: this.api.i18n.t(this.field.placeholder),
    }) as HTMLInputElement | HTMLTextAreaElement
    input.value = this.data ?? ''
    input.addEventListener('input', () => {
      this.data = this.field.clean(input.value)
      this.block.dispatchChange()
    })
    wrapper.appendChild(input)

    return wrapper
  }

  save(): string | undefined {
    return this.data
  }
}
