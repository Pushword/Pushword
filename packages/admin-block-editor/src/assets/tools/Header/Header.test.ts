import { describe, it, expect } from 'vitest'
import { API } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import Header, { HeaderData } from './Header'

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
})
