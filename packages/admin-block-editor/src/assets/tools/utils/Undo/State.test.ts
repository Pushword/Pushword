import type EditorJS from '@editorjs/editorjs'
import { describe, expect, it, vi } from 'vitest'
import { captureState, type NormalizeBlockData } from './State'

function fixture() {
  const saved = [
    { data: { text: '', meta: { transient: true } }, tunes: { anchor: 'empty' } },
    { data: { text: 'Title ', level: 2 }, tunes: {} },
  ]
  const blocks = saved.map((value, index) => ({
    id: `block-${index}`,
    name: index === 0 ? 'paragraph' : 'header',
    save: vi.fn(async () => value),
  }))
  const editor = {
    blocks: {
      getBlocksCount: () => blocks.length,
      getBlockByIndex: (index: number) => blocks[index],
    },
    saver: { save: vi.fn() },
  } as unknown as EditorJS
  return { editor, saved }
}

describe('captureState', () => {
  it('captures unmodified data and empty blocks when no normalizer is provided', async () => {
    const { editor, saved } = fixture()
    const state = await captureState(editor)
    expect(state).toEqual([
      { id: 'block-0', type: 'paragraph', ...saved[0] },
      { id: 'block-1', type: 'header', ...saved[1] },
    ])
    saved[0]!.data.meta!.transient = false
    expect(state[0]!.data.meta.transient).toBe(true)
    expect(editor.saver.save).not.toHaveBeenCalled()
  })

  it('normalizes every block on a detached copy and detaches the returned data', async () => {
    const { editor, saved } = fixture()
    let returnedData: Record<string, unknown> = {}
    const normalizeBlockData = vi.fn<NormalizeBlockData>((data, block) => {
      if (block.type === 'paragraph') {
        data.meta.transient = false
        return data
      }
      returnedData = { ...data, text: data.text.trimEnd() }
      return returnedData
    })
    const state = await captureState(editor, normalizeBlockData)
    expect(normalizeBlockData.mock.calls.map(([, block]) => block)).toEqual([
      { id: 'block-0', type: 'paragraph' },
      { id: 'block-1', type: 'header' },
    ])
    expect(state).toEqual([
      {
        id: 'block-0',
        type: 'paragraph',
        data: { text: '', meta: { transient: false } },
        tunes: { anchor: 'empty' },
      },
      { id: 'block-1', type: 'header', data: { text: 'Title', level: 2 }, tunes: {} },
    ])
    expect(saved[0]!.data.meta!.transient).toBe(true)
    expect(saved[1]!.data.text).toBe('Title ')
    returnedData.text = 'Changed after capture'
    expect(state[1]!.data.text).toBe('Title')
    expect(editor.saver.save).not.toHaveBeenCalled()
  })
})
