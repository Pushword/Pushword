import type { API, OutputBlockData } from '@editorjs/editorjs'
import { describe, expect, it, vi } from 'vitest'
import EditorJsExportMarkdown from './EditorJsExportMarkdown'
import { BlockSources } from './BlockSources'

const block = (id: string, text: string, tunes = {}) => ({
  id,
  type: 'paragraph',
  data: { text },
  tunes,
})
function fixture() {
  const tool = {
    exportToMarkdown: vi.fn(
      async (data: any, tunes: any) => `${data.text}${tunes?.anchor ?? ''}`,
    ),
  }
  const api = {
    tools: { getBlockTools: () => [{ name: 'paragraph', constructable: tool }] },
  } as unknown as API
  const exportBlocks = (blocks: OutputBlockData[]) =>
    new EditorJsExportMarkdown(api, { blocks }).exportToMarkdown()
  return { api, tool, exportBlocks }
}

describe('incremental Markdown export', () => {
  it('reformats only edited blocks, including data and tune changes', async () => {
    const f = fixture()
    const blocks = [block('a', 'First'), block('b', 'Second')]
    expect(await f.exportBlocks(blocks)).toBe('First\n\nSecond')
    expect(await f.exportBlocks(structuredClone(blocks))).toBe('First\n\nSecond')
    expect(f.tool.exportToMarkdown).toHaveBeenCalledTimes(2)

    blocks[0]!.data.text = 'Changed'
    expect(await f.exportBlocks(blocks)).toBe('Changed\n\nSecond')
    expect(f.tool.exportToMarkdown).toHaveBeenCalledTimes(3)
    blocks[1]!.tunes = { anchor: '#second' }
    expect(await f.exportBlocks(blocks)).toBe('Changed\n\nSecond#second')
    expect(f.tool.exportToMarkdown).toHaveBeenCalledTimes(4)
  })

  it('keeps moves, deletions, empty blocks and reinsertions correct', async () => {
    const f = fixture()
    const blocks = [block('a', 'First'), block('b', 'Second'), block('c', '')]
    await f.exportBlocks(blocks)
    expect(await f.exportBlocks([blocks[1]!, blocks[0]!, blocks[2]!])).toBe(
      'Second\n\nFirst',
    )
    expect(f.tool.exportToMarkdown).toHaveBeenCalledTimes(3)
    expect(await f.exportBlocks([blocks[0]!])).toBe('First')
    expect(await f.exportBlocks(blocks)).toBe('First\n\nSecond')
    expect(f.tool.exportToMarkdown).toHaveBeenCalledTimes(5)
    expect(await f.exportBlocks([])).toBe('')
    await f.exportBlocks(blocks)
    expect(f.tool.exportToMarkdown).toHaveBeenCalledTimes(8)
  })

  it('preserves the source through edits, undo and a new parse', async () => {
    const f = fixture()
    BlockSources.reset(f.api).record('a', '* original spacing')
    expect(await f.exportBlocks([block('a', '- original spacing')])).toBe(
      '* original spacing',
    )
    expect(await f.exportBlocks([block('a', '- changed')])).toBe('- changed')
    expect(await f.exportBlocks([block('a', '- original spacing')])).toBe(
      '* original spacing',
    )
    BlockSources.reset(f.api).record('a', '* new source spacing')
    expect(await f.exportBlocks([block('a', '- original spacing')])).toBe(
      '* new source spacing',
    )
    expect(f.tool.exportToMarkdown).toHaveBeenCalledTimes(3)
  })

  it('shares pending conversions but never confuses overlapping revisions', async () => {
    const f = fixture()
    let finish!: (text: string) => void
    f.tool.exportToMarkdown.mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          finish = resolve
        }),
    )
    const first = f.exportBlocks([block('a', 'First')])
    const same = f.exportBlocks([block('a', 'First')])
    expect(f.tool.exportToMarkdown).toHaveBeenCalledOnce()
    expect(await f.exportBlocks([block('a', 'Newer')])).toBe('Newer')
    finish('First')
    expect(await first).toBe('First')
    expect(await same).toBe('First')
    expect(await f.exportBlocks([block('a', 'Newer')])).toBe('Newer')
    expect(f.tool.exportToMarkdown).toHaveBeenCalledTimes(2)
  })

  it('retries failed conversions and keeps caches isolated between editors', async () => {
    const f = fixture()
    f.tool.exportToMarkdown.mockRejectedValueOnce(new Error('Conversion failed'))
    await expect(f.exportBlocks([block('a', 'First')])).rejects.toThrow(
      'Conversion failed',
    )
    expect(await f.exportBlocks([block('a', 'First')])).toBe('First')
    const other = fixture()
    expect(await other.exportBlocks([block('a', 'Other')])).toBe('Other')
    expect(await f.exportBlocks([block('a', 'First')])).toBe('First')
    expect(f.tool.exportToMarkdown).toHaveBeenCalledTimes(2)
    expect(other.tool.exportToMarkdown).toHaveBeenCalledOnce()
  })

  it('supports id-less converter data and invalidates a replaced tool', async () => {
    const f = fixture()
    const data = { type: 'paragraph', data: { text: 'No id' } }
    expect(await f.exportBlocks([data, { ...data, data: { text: 'Another' } }])).toBe(
      'No id\n\nAnother',
    )
    await f.exportBlocks([block('a', 'First')])
    const replacement = { exportToMarkdown: async () => 'Replacement' }
    f.api.tools.getBlockTools = () =>
      [{ name: 'paragraph', constructable: replacement }] as any
    expect(await f.exportBlocks([block('a', 'First')])).toBe('Replacement')
  })
})

it('does not discard a newer cached export when an older conversion fails', async () => {
  const f = fixture()
  let fail!: (error: Error) => void
  f.tool.exportToMarkdown.mockImplementationOnce(
    () =>
      new Promise((_resolve, reject) => {
        fail = reject
      }),
  )
  const older = f.exportBlocks([block('a', 'Older')])
  expect(await f.exportBlocks([block('a', 'Newer')])).toBe('Newer')
  fail(new Error('Old conversion failed'))
  await expect(older).rejects.toThrow('Old conversion failed')
  expect(await f.exportBlocks([block('a', 'Newer')])).toBe('Newer')
  expect(f.tool.exportToMarkdown).toHaveBeenCalledTimes(2)
})
