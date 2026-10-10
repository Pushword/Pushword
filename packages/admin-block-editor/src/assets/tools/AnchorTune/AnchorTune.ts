/**
 * Original author Volgador
 * https://github.com/VolgaIgor/editorjs-anchor
 */

import './Anchor.css'
import HashIcon from './Hash.svg?raw'
import { MarkdownUtils } from '../utils/MarkdownUtils'
import TextTune, { TextTuneOptions } from '../utils/TextTune'

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
      },
    )
  }
}
