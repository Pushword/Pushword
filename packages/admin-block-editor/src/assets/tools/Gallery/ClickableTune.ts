// clickable-tune.js

import { IconDirectionDownRight } from '@codexteam/icons' // Using a simple icon for demo
import { API, BlockAPI, BlockToolData } from '@editorjs/editorjs'

export interface ClickableTuneData extends BlockToolData {
  value?: boolean
}

export default class ClickableTune {
  data: ClickableTuneData
  api: API
  button: HTMLElement | null = null
  block: BlockAPI
  static get isTune() {
    return true
  }

  constructor({
    api,
    data,
    block,
  }: {
    api: API
    data: ClickableTuneData
    block: BlockAPI
  }) {
    this.api = api
    this.data = {
      value: (data && data.value) || false, // Set default value to false
    }
    this.block = block
  }

  render(): HTMLElement {
    this.button = document.createElement('div')
    this.button.classList.add(this.api.styles.inlineToolButton, this.api.styles.input)
    this.button.style.padding = '2px'
    this.button.style.fontSize = '14px'
    this.button.style.justifyContent = 'start'
    if (this.data.value) {
      this.button.classList.add(this.api.styles.inlineToolButtonActive)
    }
    this.button.innerHTML = IconDirectionDownRight // You can use any SVG icon
    this.button.appendChild(document.createTextNode('Clickable')) // Label for the button

    this.button.addEventListener('click', () => {
      this.data.value = !this.data.value
      this.button?.classList.toggle(
        this.api.styles.inlineToolButtonActive,
        this.data.value,
      )
      this.block.dispatchChange()
    })
    return this.button
  }

  wrap(blockContent: HTMLElement): HTMLElement {
    if (this.data.value) {
      blockContent.classList.add('clickableTune')
    } else {
      blockContent.classList.remove('clickableTune')
    }
    return blockContent
  }

  save(): ClickableTuneData {
    return this.data
  }
}
