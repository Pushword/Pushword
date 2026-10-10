import ClassIcon from './Class.svg?raw'
import TextTune, { TextTuneOptions } from '../utils/TextTune'

export default class ClassTune extends TextTune {
  constructor(options: TextTuneOptions) {
    super(options, {
      tag: 'textarea',
      icon: ClassIcon,
      placeholder: 'Class',
      clean: (value) => value,
    })
  }
}
