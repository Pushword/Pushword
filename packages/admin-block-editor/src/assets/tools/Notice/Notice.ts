import {
  API,
  BlockTool,
  BlockToolConstructorOptions,
  BlockToolData,
} from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import { IconWarning } from '@codexteam/icons'
import './Notice.css'
import make from '../utils/make'
import { MarkdownUtils } from '../utils/MarkdownUtils'

export interface NoticeData extends BlockToolData {
  /** Lowercased label — free-form, the five below are only suggestions. */
  level?: string
  title?: string
  /** Body as HTML, lines separated by `<br>` (like Quote). */
  text?: string
}

/** Labels the front styles out of the box; any other one renders neutral. */
const KNOWN_LEVELS = ['note', 'tip', 'important', 'warning', 'caution']

const MARKER = /^>[ \t]*\[!([a-zA-Z][a-zA-Z0-9_-]*)\](?:[ \t]+(.*?))?[ \t]*$/

/**
 * A notice: the blockquote CommonMark renders through the notice component,
 * opened by a `> [!label]` marker with an optional title.
 *
 * One block, not a marker pair: every body line carries the `> ` prefix, so
 * the body cannot stay made of top-level blocks the way a Group's content does.
 */
export default class Notice implements BlockTool {
  private api: API
  private data: Required<NoticeData>
  private readOnly: boolean
  private body!: HTMLElement

  static get toolbox() {
    return { icon: IconWarning, title: 'Notice' }
  }

  static get isReadOnlySupported(): boolean {
    return true
  }

  static get enableLineBreaks(): boolean {
    return true
  }

  constructor({ data, api, readOnly }: BlockToolConstructorOptions<NoticeData>) {
    this.api = api
    this.readOnly = readOnly
    this.data = {
      level: (data?.level ?? 'note').toLowerCase(),
      title: data?.title ?? '',
      text: data?.text ?? '',
    }
  }

  render(): HTMLElement {
    const wrapper = make.element('div', ['cdx-notice', `cdx-notice--${this.data.level}`])

    const header = make.element('div', 'cdx-notice__header')
    header.appendChild(this.levelInput(wrapper))
    header.appendChild(
      this.input(
        'cdx-notice__title',
        this.api.i18n.t('Title'),
        this.data.title,
        (value) => {
          this.data.title = value
        },
      ),
    )
    wrapper.appendChild(header)

    this.body = make.element(
      'div',
      'cdx-notice__body',
      {
        contenteditable: this.readOnly ? 'false' : 'true',
        'data-placeholder': this.api.i18n.t('Notice'),
      },
      this.data.text,
    )
    wrapper.appendChild(this.body)

    return wrapper
  }

  /** A datalist rather than a select: the label is free-form by design. */
  private levelInput(wrapper: HTMLElement): HTMLElement {
    const listId = 'cdx-notice-levels'
    if (document.getElementById(listId) === null) {
      const datalist = make.element('datalist', null, { id: listId })
      for (const level of KNOWN_LEVELS) {
        datalist.appendChild(make.element('option', null, { value: level }))
      }
      document.body.appendChild(datalist)
    }

    const input = this.input(
      'cdx-notice__level',
      this.api.i18n.t('Level'),
      this.data.level,
      (value) => {
        const level = value.toLowerCase().replace(/[^a-z0-9_-]/g, '')
        wrapper.classList.remove(`cdx-notice--${this.data.level}`)
        this.data.level = level
        wrapper.classList.add(`cdx-notice--${level}`)
      },
    )
    input.setAttribute('list', listId)

    return input
  }

  private input(
    className: string,
    placeholder: string,
    value: string,
    apply: (value: string) => void,
  ): HTMLInputElement {
    const input = make.element('input', ['cdx-notice__input', className], {
      placeholder,
      value,
    }) as HTMLInputElement

    if (this.readOnly) {
      input.setAttribute('readonly', 'readonly')

      return input
    }

    input.addEventListener('input', () => apply(input.value))

    return input
  }

  save(): NoticeData {
    return {
      level: this.data.level || 'note',
      title: this.data.title.trim(),
      text: this.body.innerHTML,
    }
  }

  static exportToMarkdown(data: NoticeData, tunes?: BlockTuneData): string {
    const level = (data?.level || 'note').toLowerCase()
    const title = (data?.title ?? '').trim()

    let markdown = `> [!${level}]${'' !== title ? ' ' + title : ''}`
    for (const line of (data?.text ?? '').split(/<br\s*\/?>/gi)) {
      const text = MarkdownUtils.convertInlineHtmlToMarkdown(line).trim()
      markdown += '\n' + (text === '' ? '>' : `> ${text}`)
    }

    return MarkdownUtils.addAttributes(markdown, tunes ?? {})
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown)
    const lines = result.markdown.split('\n')
    const marker = MARKER.exec(lines.shift() ?? '')
    if (marker === null) return

    const data: NoticeData = {
      level: (marker[1] ?? 'note').toLowerCase(),
      title: (marker[2] ?? '').trim(),
      text: lines
        .map((line) =>
          MarkdownUtils.convertInlineMarkdownToHtml(line.replace(/^>[ \t]?/, '')),
        )
        .join('<br>'),
    }

    const block = editor.blocks.insert('notice', data)
    editor.blocks.update(block.id, data, result.tunes)
  }

  static isItMarkdownExported(markdown: string): boolean {
    return MARKER.test(markdown.split('\n')[0] ?? '')
  }
}
