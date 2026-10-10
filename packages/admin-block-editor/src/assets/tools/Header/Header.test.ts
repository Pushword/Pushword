import { describe, it, expect } from 'vitest'
import { API, PasteEvent } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import Header, { HeaderConfig, HeaderData } from './Header'

function importHeader(markdown: string): { data: HeaderData; tunes: BlockTuneData } {
  let captured: { data: HeaderData; tunes: BlockTuneData } | null = null
  const editor = {
    blocks: {
      insert: () => ({ id: 'block-id' }),
      update: (_id: string, data: HeaderData, tunes: BlockTuneData) => {
        captured = { data, tunes }
      },
    },
  } as unknown as API

  Header.importFromMarkdown(editor, markdown)

  if (captured === null) {
    throw new Error('header block was never updated')
  }

  return captured
}

describe('Header.importFromMarkdown', () => {
  it('reads tunes from inline attributes after the title', () => {
    expect(importHeader('## Title {#anchor .text-center}')).toEqual({
      data: { text: 'Title', level: 2 },
      tunes: { anchor: 'anchor', textAlign: 'center' },
    })
  })

  it('reads tunes from an attribute line above the title', () => {
    expect(importHeader('{#intro}\n### Intro')).toEqual({
      data: { text: 'Intro', level: 3 },
      tunes: { anchor: 'intro' },
    })
  })

  it('passes empty tunes and converts inline markdown when there are no attributes', () => {
    expect(importHeader('#### A **bold** word')).toEqual({
      data: { text: 'A <b>bold</b> word', level: 4 },
      tunes: {},
    })
  })

  it('rejects a level-one heading', () => {
    expect(() => importHeader('# Title')).toThrow('Invalid markdown format for header')
  })
})

describe('Header.isItMarkdownExported', () => {
  it('claims levels two to six only', () => {
    expect(Header.isItMarkdownExported('## Title')).toBe(true)
    expect(Header.isItMarkdownExported('###### Title')).toBe(true)
    expect(Header.isItMarkdownExported('# Title')).toBe(false)
    expect(Header.isItMarkdownExported('####### Title')).toBe(false)
  })
})

describe('Header.render', () => {
  it('puts the level badge before the heading, so the level is read first', () => {
    const api = { i18n: { t: (text: string) => text } } as unknown as API
    const container = new Header({
      data: { text: 'Title', level: 3 },
      api,
      config: {},
      readOnly: false,
    }).render()

    expect([...container.children].map((child) => child.tagName)).toEqual(['DIV', 'H3'])
    expect(
      container.firstElementChild?.classList.contains('ce-header-level-wrapper'),
    ).toBe(true)
  })
})

describe('Header placeholder', () => {
  it('shows the configured placeholder on an empty heading', () => {
    const api = { i18n: { t: (text: string) => text } } as unknown as API
    const container = new Header({
      data: { text: '', level: 2 },
      api,
      config: { placeholder: 'Heading' },
      readOnly: false,
    }).render()

    expect(container.querySelector('h2')?.dataset.placeholder).toBe('Heading')
  })
})

describe('Header level select', () => {
  it('takes its label from the editor translations', () => {
    const translations: Record<string, string> = { 'Heading level': 'Niveau de titre' }
    const api = {
      i18n: { t: (text: string) => translations[text] ?? text },
    } as unknown as API
    const select = new Header({
      data: { text: 'Title', level: 2 },
      api,
      config: {},
      readOnly: false,
    })
      .render()
      .querySelector('select')

    expect(select?.getAttribute('aria-label')).toBe('Niveau de titre')
    expect(select?.title).toBe('Niveau de titre')
  })

  function chooseLevel(level: number) {
    const translations: Record<string, string> = { 'Heading level': 'Niveau de titre' }
    const api = {
      i18n: { t: (text: string) => translations[text] ?? text },
    } as unknown as API
    const header = new Header({
      data: { text: 'A <b>bold</b> title', level: 2 },
      api,
      config: {},
      readOnly: false,
    })
    // The heading is rebuilt in place, which needs a parent like Editor.js gives it.
    const holder = document.createElement('div')
    holder.appendChild(header.render())

    const select = holder.querySelector('select')
    if (!select) throw new Error('level select not rendered')
    select.value = level.toString()
    select.dispatchEvent(new Event('change'))

    const rebuilt = holder.firstElementChild
    if (!(rebuilt instanceof HTMLElement)) throw new Error('heading not rebuilt')

    return { header, holder, rebuilt }
  }

  it('rebuilds the heading at the chosen level, keeping its text', () => {
    const { header, holder, rebuilt } = chooseLevel(4)

    expect(holder.children).toHaveLength(1)
    expect(rebuilt).toBe(header.render())
    expect(rebuilt.querySelector('h4')?.innerHTML).toBe('A <b>bold</b> title')
    expect(rebuilt.querySelector('h2')).toBeNull()
    expect(rebuilt.querySelector('select')?.value).toBe('4')
    expect(
      rebuilt.querySelector<HTMLElement>('.ce-header-level-label')?.dataset.level,
    ).toBe('H4')
    expect(header.save(rebuilt)).toEqual({ text: 'A <b>bold</b> title', level: 4 })
  })

  it('keeps the badge first and the translated label on the rebuilt heading', () => {
    const { rebuilt } = chooseLevel(5)

    expect([...rebuilt.children].map((child) => child.tagName)).toEqual(['DIV', 'H5'])
    expect(rebuilt.firstElementChild?.classList.contains('ce-header-level-wrapper')).toBe(
      true,
    )
    const select = rebuilt.querySelector('select')
    expect(select?.getAttribute('aria-label')).toBe('Niveau de titre')
    expect(select?.title).toBe('Niveau de titre')
  })
})

describe('Header levels', () => {
  const api = { i18n: { t: (text: string) => text } } as unknown as API

  function render(data: { text?: string; level?: number }, config: HeaderConfig = {}) {
    const header = new Header({ data, api, config, readOnly: false })
    const container = header.render()
    const options = [...container.querySelectorAll('option')].map(
      (option) => option.value,
    )

    return { header, container, options }
  }

  it('offers H2 to H6 when the config lists no levels', () => {
    expect(render({ text: 'Title', level: 2 }).options).toEqual(['2', '3', '4', '5', '6'])
  })

  it('offers the levels the config lists', () => {
    expect(render({ text: 'Title', level: 2 }, { levels: [2, 3, 4] }).options).toEqual([
      '2',
      '3',
      '4',
    ])
  })

  it('keeps offering a level the config does not list, so the heading keeps it', () => {
    const { header, container, options } = render(
      { text: 'Title', level: 5 },
      { levels: [2, 3, 4] },
    )

    expect(options).toEqual(['2', '3', '4', '5'])
    expect(container.querySelector('select')?.value).toBe('5')
    expect(container.querySelector('h5')).not.toBeNull()
    expect(header.save(container)).toEqual({ text: 'Title', level: 5 })
  })

  it('gives a heading without a level the configured default level', () => {
    const { header, container } = render({ text: 'Title' }, { defaultLevel: 3 })

    expect(container.querySelector('h3')).not.toBeNull()
    expect(header.save(container)).toEqual({ text: 'Title', level: 3 })
  })

  it('renders a level no heading can have at the default level, badge included', () => {
    const { header, container } = render({ text: 'Title', level: 1 })

    expect(container.querySelector('h2')).not.toBeNull()
    expect(
      container.querySelector<HTMLElement>('.ce-header-level-label')?.dataset.level,
    ).toBe('H2')
    expect(header.save(container)).toEqual({ text: 'Title', level: 2 })
  })
})

describe('Header.onPaste', () => {
  it('rebuilds the heading at the pasted level, with the pasted text', () => {
    const api = { i18n: { t: (text: string) => text } } as unknown as API
    const header = new Header({ data: {}, api, config: {}, readOnly: false })
    const holder = document.createElement('div')
    holder.appendChild(header.render())

    const pasted = document.createElement('h3')
    pasted.innerHTML = 'Pasted <b>title</b>'
    header.onPaste({ detail: { data: pasted } } as unknown as PasteEvent)

    const rebuilt = holder.firstElementChild as HTMLElement
    expect(rebuilt).toBe(header.render())
    expect(rebuilt.querySelector('h3')?.innerHTML).toBe('Pasted <b>title</b>')
    expect(
      rebuilt.querySelector<HTMLElement>('.ce-header-level-label')?.dataset.level,
    ).toBe('H3')
    expect(header.save(rebuilt)).toEqual({ text: 'Pasted <b>title</b>', level: 3 })
  })
})
