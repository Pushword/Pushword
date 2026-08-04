import { describe, it, expect } from 'vitest'
import { API } from '@editorjs/editorjs'
import { JsonMonacoSource, MarkdownMonacoSource } from './MonacoOutlineSource'
import Header from '../tools/Header/Header'
import GroupStart from '../tools/Group/GroupStart'
import GroupEnd from '../tools/Group/GroupEnd'
import Paragraph from '../tools/Paragraph/Paragraph'
import Raw from '../tools/Raw/Raw'

/** Real tool classes behind the same adapter shape getBlockTools() returns. */
const fakeApi = {
  tools: {
    getBlockTools: () => [
      { name: 'header', constructable: Header },
      { name: 'groupStart', constructable: GroupStart },
      { name: 'groupEnd', constructable: GroupEnd },
      { name: 'paragraph', constructable: Paragraph },
      { name: 'raw', constructable: Raw },
    ],
  },
} as unknown as API

function markdownSource(text: string): {
  source: MarkdownMonacoSource
  input: HTMLTextAreaElement
} {
  const input = document.createElement('textarea')
  input.value = text
  const source = new MarkdownMonacoSource({ monaco: () => null, input: () => input }, fakeApi)
  return { source, input }
}

describe('MarkdownMonacoSource.entries', () => {
  it('classifies chunks like the parser and derives levels and labels', () => {
    const { source } = markdownSource(
      '## Title {#anchor}\n\npara text\n\n<div id="faq" class="grid">\n\ninside\n\n</div>',
    )

    expect(source.entries()).toEqual([
      { index: 0, type: 'header', level: 2, label: 'Title' },
      { index: 1, type: 'paragraph', level: null, label: 'para text' },
      { index: 2, type: 'groupStart', level: null, label: '#faq grid' },
      { index: 3, type: 'paragraph', level: null, label: 'inside' },
      { index: 4, type: 'groupEnd', level: null, label: '' },
    ])
  })

  it('sends html-looking chunks to raw', () => {
    const { source } = markdownSource('<video src="x.mp4"></video>')

    expect(source.entries()).toEqual([
      { index: 0, type: 'raw', level: null, label: '<video src="x.mp4"></video>' },
    ])
  })
})

describe('MarkdownMonacoSource edits', () => {
  it('moves a span of chunks and rewrites the field', () => {
    const { source, input } = markdownSource('## A\n\na body\n\n## B\n\nb body')

    source.moveSpan(2, 3, 0)

    expect(input.value).toBe('## B\n\nb body\n\n## A\n\na body')
  })

  it('deletes a span of chunks', () => {
    const { source, input } = markdownSource('## A\n\na body\n\n## B\n\nb body')

    source.deleteSpan(0, 1)

    expect(input.value).toBe('## B\n\nb body')
  })

  it('ignores a move into itself', () => {
    const { source, input } = markdownSource('## A\n\na body')

    source.moveSpan(0, 1, 1)

    expect(input.value).toBe('## A\n\na body')
  })
})

describe('JsonMonacoSource', () => {
  const json = JSON.stringify(
    {
      time: 1,
      blocks: [
        { id: 'h1x', type: 'header', data: { text: 'A <b>bold</b> title', level: 3 } },
        { id: 'p1x', type: 'paragraph', data: { text: 'body' } },
        { id: 'g1x', type: 'groupStart', data: { anchor: 'faq', class: '' } },
      ],
      version: '2.31.6',
    },
    null,
    2,
  )

  function jsonSource(text: string): {
    source: JsonMonacoSource
    input: HTMLTextAreaElement
  } {
    const input = document.createElement('textarea')
    input.value = text
    return { source: new JsonMonacoSource({ monaco: () => null, input: () => input }), input }
  }

  it('lists blocks with levels and tag-free labels', () => {
    const { source } = jsonSource(json)

    expect(source.entries()).toEqual([
      { index: 0, type: 'header', level: 3, label: 'A bold title' },
      { index: 1, type: 'paragraph', level: null, label: 'body' },
      { index: 2, type: 'groupStart', level: null, label: '#faq' },
    ])
  })

  it('moves a block and keeps the JSON envelope', () => {
    const { source, input } = jsonSource(json)

    source.moveSpan(0, 0, 3)

    const parsed = JSON.parse(input.value)
    expect(parsed.blocks.map((block: { id: string }) => block.id)).toEqual([
      'p1x',
      'g1x',
      'h1x',
    ])
    expect(parsed.version).toBe('2.31.6')
  })

  it('deletes a block', () => {
    const { source, input } = jsonSource(json)

    source.deleteSpan(1, 1)

    expect(JSON.parse(input.value).blocks).toHaveLength(2)
  })

  it('yields no entries on unparsable text', () => {
    const { source } = jsonSource('{ broken')

    expect(source.entries()).toEqual([])
  })
})
