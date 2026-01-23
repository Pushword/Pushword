import { API, BlockAPI } from '@editorjs/editorjs'
import make from '../utils/make'
import ClassIcon from './Class.svg?raw'

export default class ClassTune {
  private api: API
  private data: string
  private block: BlockAPI

  public static get isTune(): boolean {
    return true
  }

  private get class(): string {
    return this.data || ''
  }

  constructor({ api, data, block }: { api: API; data: string; block: BlockAPI }) {
    this.api = api
    this.data = data
    this.block = block
  }

  public render(value: string | null = null): HTMLElement {
    const wrapper = document.createElement('div')
    wrapper.classList.add('cdx-anchor-tune-wrapper')

    const wrapperIcon = document.createElement('div')
    wrapperIcon.classList.add('cdx-anchor-tune-icon')
    wrapperIcon.innerHTML = ClassIcon

    const wrapperInput = make.element('textarea', 'cdx-anchor-tune-input', {
      placeholder: this.api.i18n.t('Class'),
    }) as HTMLTextAreaElement
    wrapperInput.value = this.class

    wrapperInput.addEventListener('input', (event: Event) => {
      const target = event.target as HTMLTextAreaElement
      this.data = target.value || ''
      this.block.dispatchChange()
    })

    wrapper.appendChild(wrapperIcon)
    wrapper.appendChild(wrapperInput)

    return wrapper
  }

  public save(): string {
    return this.data
  }
}
