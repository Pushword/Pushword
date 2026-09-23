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
