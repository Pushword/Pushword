import { describe, it, expect } from 'vitest'
import { API } from '@editorjs/editorjs'
import Notice, { NoticeData } from './Notice'

function importNotice(markdown: string): NoticeData {
  let captured: NoticeData | null = null
  const editor = {
    blocks: {
      insert: () => ({ id: 'block-id' }),
      update: (_id: string, data: NoticeData) => {
        captured = data
      },
    },
  } as unknown as API

  Notice.importFromMarkdown(editor, markdown)

  if (captured === null) {
    throw new Error('notice block was never updated')
  }

  return captured
}

describe('Notice.isItMarkdownExported', () => {
  it('claims a marker line, whatever its case', () => {
    expect(Notice.isItMarkdownExported('> [!warning] Version')).toBe(true)
    expect(Notice.isItMarkdownExported('> [!WARNING]')).toBe(true)
    expect(Notice.isItMarkdownExported('> [!sponsored] Ad')).toBe(true)
  })

  it('leaves an ordinary quote to the Quote tool', () => {
    expect(Notice.isItMarkdownExported('> Just a quote')).toBe(false)
    expect(Notice.isItMarkdownExported('> \\[!NOTE] escaped')).toBe(false)
  })

  it('does not read a quoted linked image as a marker', () => {
    expect(Notice.isItMarkdownExported('> [![alt](img.jpg)](/page)')).toBe(false)
  })
})

describe('Notice block UI', () => {
  const mount = (data: NoticeData): { tool: Notice; wrapper: HTMLElement } => {
    const tool = new Notice({
      data,
      api: { i18n: { t: (key: string) => key } },
      readOnly: false,
    } as never)

    return { tool, wrapper: tool.render() }
  }

  it('renders the stored notice and saves it back', () => {
    const { tool, wrapper } = mount({
      level: 'warning',
      title: 'Version',
      text: 'Last <b>bit</b>.',
    })

    expect(wrapper.classList.contains('cdx-notice--warning')).toBe(true)
    expect(wrapper.querySelector<HTMLInputElement>('.cdx-notice__level')?.value).toBe(
      'warning',
    )
    expect(wrapper.querySelector<HTMLInputElement>('.cdx-notice__title')?.value).toBe(
      'Version',
    )
    expect(tool.save()).toEqual({
      level: 'warning',
      title: 'Version',
      text: 'Last <b>bit</b>.',
    })
  })

  it('follows the level typed in, sanitised, on the wrapper and in the data', () => {
    const { tool, wrapper } = mount({ level: 'note', title: '', text: '' })
    const level = wrapper.querySelector<HTMLInputElement>('.cdx-notice__level')!

    level.value = 'Spon sored!'
    level.dispatchEvent(new Event('input'))

    expect(tool.save().level).toBe('sponsored')
    expect(wrapper.classList.contains('cdx-notice--sponsored')).toBe(true)
    expect(wrapper.classList.contains('cdx-notice--note')).toBe(false)
  })

  it('offers the known levels as suggestions without locking them in', () => {
    mount({ level: 'note', title: '', text: '' })

    const options = [...document.querySelectorAll('#cdx-notice-levels option')].map(
      (option) => option.getAttribute('value'),
    )
    expect(options).toEqual(['note', 'tip', 'important', 'warning', 'caution'])
  })
})

describe('Notice markdown round-trip', () => {
  it('imports level, title and body', () => {
    const data = importNotice('> [!WARNING] Version\n>\n> Last updated: **August 2026**.')

    expect(data.level).toBe('warning')
    expect(data.title).toBe('Version')
    expect(data.text).toBe('<br>Last updated: <b>August 2026</b>.')
  })

  it('exports back what it imported', () => {
    const markdown = '> [!warning] Version\n>\n> Last updated: **August 2026**.'

    expect(Notice.exportToMarkdown(importNotice(markdown))).toBe(markdown)
  })

  it('keeps a title-less marker title-less', () => {
    const markdown = '> [!note]\n> Just a remark.'

    expect(importNotice(markdown).title).toBe('')
    expect(Notice.exportToMarkdown(importNotice(markdown))).toBe(markdown)
  })

  it('keeps a label it does not know', () => {
    expect(importNotice('> [!sponsored] Ad\n> Paid.').level).toBe('sponsored')
  })

  it('carries the tunes of an attribute line', () => {
    const markdown = '{#disclosure}\n> [!note] Titled\n> body'

    expect(
      Notice.exportToMarkdown(importNotice(markdown), { anchor: 'disclosure' }),
    ).toBe(markdown)
  })

  it('round-trips a body of several paragraphs', () => {
    const markdown = '> [!tip] Shortcuts\n>\n> One.\n>\n> Two.'

    expect(Notice.exportToMarkdown(importNotice(markdown))).toBe(markdown)
  })

  it('defaults a level-less block to note', () => {
    expect(Notice.exportToMarkdown({ text: 'body' })).toBe('> [!note]\n> body')
  })
})
