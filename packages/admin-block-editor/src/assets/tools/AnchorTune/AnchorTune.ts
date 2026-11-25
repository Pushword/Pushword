/**
 * Original author Volgador
 * https://github.com/VolgaIgor/editorjs-anchor
 */

import './Anchor.css'
import HashIcon from './Hash.svg?raw'
import { MarkdownUtils } from '../utils/MarkdownUtils'
import TextTune, { TextTuneOptions } from '../utils/TextTune'
import { textFieldAt, textFields } from '../utils/TextSelectionState'

function confirmAnchor({ api, block }: TextTuneOptions): void {
  api.toolbar.toggleBlockSettings(false)
  const holder = block.holder.closest<HTMLElement>('.codex-editor')
  if (!holder) return

  // The native close restores the text selection saved when the settings opened.
  const selection = window.getSelection()
  if (selection?.anchorNode && selection.focusNode) {
    const { anchorNode, anchorOffset, focusNode, focusOffset } = selection
    const field = textFieldAt(focusNode, holder)
    if (field?.contains(anchorNode)) {
      const origin = api.blocks.getBlockByElement(field)
      if (origin && origin.id !== block.id) api.caret.setToBlock(origin)
      field.focus({ preventScroll: true })
      selection.setBaseAndExtent(anchorNode, anchorOffset, focusNode, focusOffset)
      return
    }
  }

  if (textFields(block.holder).length) {
    api.caret.setToBlock(block.id, 'end')
    return
  }

  const trigger = holder.querySelector<HTMLElement>('.ce-toolbar__settings-btn') ?? block.holder
  if (!trigger.hasAttribute('tabindex')) trigger.tabIndex = -1
  trigger.focus({ preventScroll: true })
}

export default class AnchorTune extends TextTune {
  // An anchor never set saves as '', where a class saves as nothing at all.
  constructor({ api, data = '', block }: TextTuneOptions) {
    super(
      { api, data, block },
      {
        tag: 'input',
        icon: HashIcon,
        placeholder: 'Anchor',
        clean: MarkdownUtils.sanitizeAnchor,
        confirm: () => confirmAnchor({ api, block }),
      },
    )
  }
}
