import type EditorJS from '@editorjs/editorjs'
import { describe, expect, it, vi } from 'vitest'
import { StateCapture, type NormalizeBlockData } from './State'

function fixture() {
  const saved = [
    { data: { text: '', meta: { transient: true } }, tunes: { anchor: 'empty' } },
    { data: { text: 'Title ', level: 2 }, tunes: {} },
  ]
  const blocks = saved.map((value, index) => ({
    id: `block-${index}`,
    holder: document.createElement('div'),
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
  return { editor, saved, blocks }
}

describe('StateCapture', () => {
  it('captures unmodified data and empty blocks when no normalizer is provided', async () => {
    const { editor, saved } = fixture()
    const state = await new StateCapture(editor).capture()
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
    const state = await new StateCapture(editor, normalizeBlockData).capture()
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

it('reuses untouched snapshots and normalizes only changed blocks', async () => {
  const { editor, saved, blocks } = fixture()
  const normalize = vi.fn<NormalizeBlockData>((data) => ({
    ...data,
    text: data.text.trimEnd(),
  }))
  const capture = new StateCapture(editor, normalize)
  const before = await capture.capture()
  saved[1]!.data.text = 'Changed '
  const after = await capture.capture(new Set(['block-1']))
  expect(after[0]).toBe(before[0])
  expect(after[1]!.data.text).toBe('Changed')
  expect(before[1]!.data.text).toBe('Title')
  expect(blocks[0]!.save).toHaveBeenCalledOnce()
  expect(blocks[1]!.save).toHaveBeenCalledTimes(2)
  expect(normalize).toHaveBeenCalledTimes(3)
})

it('tracks moves, replacements and removed ids without reusing obsolete data', async () => {
  const { editor, saved, blocks } = fixture()
  const capture = new StateCapture(editor)
  const before = await capture.capture()
  blocks.reverse()
  expect(await capture.capture(new Set())).toEqual([...before].reverse())
  expect(blocks[0]!.save).toHaveBeenCalledOnce()
  blocks[0]!.holder = document.createElement('div')
  saved[1]!.data.text = 'Replacement'
  expect((await capture.capture(new Set()))[0]!.data.text).toBe('Replacement')
  const removed = blocks.pop()!
  await capture.capture(new Set())
  blocks.push(removed)
  await capture.capture(new Set())
  expect(removed.save).toHaveBeenCalledTimes(2)
  capture.reset()
  await capture.capture(new Set())
  expect(removed.save).toHaveBeenCalledTimes(3)
})

it('shares pending snapshots without losing a later edit in a different block', async () => {
  const { editor, blocks } = fixture()
  const capture = new StateCapture(editor)
  await capture.capture()
  let finish!: (value: any) => void
  blocks[0]!.save.mockImplementationOnce(
    () =>
      new Promise((resolve) => {
        finish = resolve
      }),
  )
  const first = capture.capture(new Set(['block-0']))
  const second = capture.capture(new Set(['block-1']))
  finish({ data: { text: 'Pending' }, tunes: {} })
  expect((await first)[0]!.data.text).toBe('Pending')
  expect((await second)[0]).toBe((await first)[0])
  expect(blocks[0]!.save).toHaveBeenCalledTimes(2)
})

it('retries a failed capture on the next edit instead of retaining a rejected snapshot', async () => {
  const { editor, blocks } = fixture()
  const capture = new StateCapture(editor)
  await capture.capture()
  blocks[0]!.save.mockRejectedValueOnce(new Error('Not ready'))
  await expect(capture.capture(new Set(['block-0']))).rejects.toThrow('Not ready')
  const state = await capture.capture(new Set(['block-1']))
  expect(state[0]!.data.text).toBe('')
  expect(blocks[0]!.save).toHaveBeenCalledTimes(3)
})
