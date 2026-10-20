import { describe, it, expect, vi } from 'vitest'
import { EditorJsOutlineSource } from './EditorJsOutlineSource'

function fakeApi(): {
  source: EditorJsOutlineSource
  moves: [number, number][]
  deletes: number[]
} {
  const moves: [number, number][] = []
  const deletes: number[] = []
  const api = {
    blocks: {
      move: (to: number, from: number) => moves.push([to, from]),
      delete: (index: number) => deletes.push(index),
    },
  } as never
  return { source: new EditorJsOutlineSource(api), moves, deletes }
}

describe('EditorJsOutlineSource.moveSpan', () => {
  it('moves a span down, one block at a time, keeping its order', () => {
    // [A B C D E] with B..C dropped before E → [A D B C E]
    const { source, moves } = fakeApi()

    source.moveSpan(1, 2, 4)

    expect(moves).toEqual([
      [3, 1],
      [3, 1],
    ])
  })

  it('moves a span up keeping its order', () => {
    // [A B C D E] with C..D dropped at the top → [C D A B E]
    const { source, moves } = fakeApi()

    source.moveSpan(2, 3, 0)

    expect(moves).toEqual([
      [0, 2],
      [1, 3],
    ])
  })

  it('does nothing for a drop into or right around the span', () => {
    const { source, moves } = fakeApi()

    source.moveSpan(1, 2, 1)
    source.moveSpan(1, 2, 2)
    source.moveSpan(1, 2, 3)

    expect(moves).toEqual([])
  })
})

describe('EditorJsOutlineSource.deleteSpan', () => {
  it('deletes from the end so indices never shift', () => {
    const { source, deletes } = fakeApi()

    source.deleteSpan(1, 3)

    expect(deletes).toEqual([3, 2, 1])
  })
})

it.each([false, true])('respects reduced motion (%s) when navigating to a block', (reduced) => {
  const holder = document.createElement('div')
  holder.scrollIntoView = vi.fn()
  const media = vi.spyOn(window, 'matchMedia').mockReturnValue({ matches: reduced } as MediaQueryList)
  const source = new EditorJsOutlineSource({
    blocks: { getBlockByIndex: () => ({ holder }) },
    caret: { setToBlock: vi.fn() },
  } as never)
  source.navigateTo({ index: 0, type: 'paragraph', label: 'Text', level: null })
  expect(holder.scrollIntoView).toHaveBeenCalledWith({ behavior: reduced ? 'auto' : 'smooth', block: 'center' })
  media.mockRestore()
})
