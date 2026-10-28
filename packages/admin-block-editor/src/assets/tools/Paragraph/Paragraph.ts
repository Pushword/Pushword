import ParagraphTool from '@editorjs/paragraph'
import { MarkdownUtils } from '../utils/MarkdownUtils'
import { BlockToolData, API } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'

export interface ParagraphData extends BlockToolData {
  text?: string
}

export default class Paragraph extends ParagraphTool {
  render(): HTMLDivElement {
    const field = super.render()
    if (this.readOnly) return field

    field.addEventListener('keydown', event => {
      if (event.key !== 'Delete' || event.defaultPrevented || event.isComposing ||
        event.shiftKey || event.ctrlKey || event.altKey || event.metaKey ||
        /[^\s\u200b]/u.test(field.textContent ?? '')) return
      const selection = window.getSelection()
      if (!selection?.isCollapsed || !selection.rangeCount) return
      const rest = selection.getRangeAt(0).cloneRange()
      if (!field.contains(rest.endContainer)) return
      rest.setEnd(field, field.childNodes.length)
      if (/[^\t\n\r ]/.test(rest.toString())) return

      const index = this.api.blocks.getCurrentBlockIndex()
      const next = this.api.blocks.getBlockByIndex(index + 1)
      if (!next) return

      // Editor.js counts spaces as content and would merge the next block into this paragraph.
      event.preventDefault()
      event.stopPropagation()
      this.api.blocks.delete(index)
      this.api.caret.setToBlock(next.id, 'start')
    })
    return field
  }

  save(content: HTMLDivElement): { text: string } {
    const data = super.save(content)
    // Firefox leaves a caret placeholder after cutting all text. It is empty.
    if (data.text === '<br>') data.text = ''
    return data
  }

  static async exportToMarkdown(
    data: ParagraphData,
    tunes: BlockTuneData,
  ): Promise<string> {
    if (!data || !data.text) {
      return ''
    }

    // 2 spaces = <br> in markdown; a bare newline stays soft
    let markdown = MarkdownUtils.htmlLines(
      data.text.replace(/(&nbsp;| |\u00A0)+ */g, ' '),
    ).join('  \n')
    markdown = MarkdownUtils.convertInlineHtmlToMarkdown(markdown)
    const formattedMarkdown = await MarkdownUtils.formatMarkdownWithPrettier(markdown)
    return MarkdownUtils.addAttributes(formattedMarkdown, tunes)
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown)
    const tunes: BlockTuneData = result.tunes

    // A soft line break stays a newline: shown as the space the site renders,
    // and written back as the line break it was.
    const block = editor.blocks.insert('paragraph')
    editor.blocks.update(
      block.id,
      {
        text: MarkdownUtils.convertInlineMarkdownToHtml(result.markdown),
      },
      tunes,
    )
  }

  static isItMarkdownExported(markdown: string): boolean {
    const trimmed = markdown.trim()

    // These inline tools export HTML because Markdown has no equivalent.
    if (/^<(?:mark|small)>/i.test(trimmed)) return true

    // starts with '<' = probably html
    // starts with '{' = probably twig function
    // starts with '-->' = probably a hack to comment close a previously opened comment
    // starts with '#}' = same
    const isProbablyNotMarkdown = ['<', '{', '-->', '#}'].some((prefix) => trimmed.startsWith(prefix))

    // Return true only if it doesn't start with those patterns
    return !isProbablyNotMarkdown
  }
}
