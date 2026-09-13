import EditorJS from '@editorjs/editorjs'
import { isSafeLinkUrl } from './safeLinkUrl'

export default class PasteLink {
  private editor: EditorJS

  // private holder: HTMLElement

  constructor({ editor }: { editor: EditorJS }) {
    this.editor = editor

    // this.holder =
    //   typeof configuration.holder === 'string'
    //     ? document.getElementById(configuration.holder) || document.body
    //     : configuration.holder

    this.initializePasteListener()
  }

  private initializePasteListener(): void {
    document.addEventListener(
      'paste',
      (event: ClipboardEvent): void => {
        let selectedNode =
          window.getSelection()?.anchorNode || window.getSelection()?.focusNode
        if (selectedNode && selectedNode.nodeType === Node.TEXT_NODE) {
          selectedNode = selectedNode.parentNode as Node
        }

        // Do we have a text to create an anchor ?
        const textSelected = window.getSelection()?.toString() || ''
        if (!textSelected) return // TODO if clipboard content is url, transform clipboard content in link

        // Are we in editor.js instance
        if (!selectedNode || !(selectedNode as Element).closest('.ce-block__content'))
          return

        // Are we in a link ?
        // normally, it's not possible because if you select an a, it's opening the related toolbar

        const parentAnchor = this.editor.selection.findParentTag('A')
        if (parentAnchor) return

        // Do we have an URL in the clipboard to create a link ?
        const text = this.getClipboardText(event)
        if (!isSafeLinkUrl(text)) return

        event.preventDefault()
        event.stopPropagation()

        const anchor = document.createElement('a')
        anchor.href = text
        anchor.textContent = textSelected.trim()
        document.execCommand(
          'insertHTML',
          false,
          (textSelected.startsWith(' ') ? ' ' : '') +
            anchor.outerHTML +
            (textSelected.endsWith(' ') ? ' ' : ''),
        )
      },
      true,
    )
  }

  private getClipboardText(event: ClipboardEvent): string {
    // @ts-ignore windows 11 clipboardData
    return (event.clipboardData || window.clipboardData)?.getData('text') || ''

    // const text = await window.navigator.clipboard.readText()
    // return text
  }
}
