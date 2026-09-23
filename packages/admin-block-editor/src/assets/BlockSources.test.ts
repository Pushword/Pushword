import { describe, it, expect } from 'vitest'
import { BlockSources } from './BlockSources'

describe('BlockSources', () => {
  it('has nothing for an editor that was never parsed', () => {
    expect(BlockSources.of({})).toBeUndefined()
  })

  it('leaves a block with no recorded source to its export', () => {
    const sources = BlockSources.reset({})
    sources.record('b0', '* one')

    expect(sources.resolve(undefined, '- one')).toBe('- one')
    expect(sources.resolve('b1', '- two')).toBe('- two')
  })

  it('takes the first export as the baseline and writes the source while it holds', () => {
    const sources = BlockSources.reset({})
    sources.record('b0', '* one')

    expect(sources.resolve('b0', '- one')).toBe('* one')
    expect(sources.resolve('b0', '- one edited')).toBe('- one edited')
    expect(sources.resolve('b0', '- one')).toBe('* one')
  })

  it('forgets the previous parse of the same editor on reset', () => {
    const editor = {}
    const first = BlockSources.reset(editor)
    first.record('b0', '* one')

    const second = BlockSources.reset(editor)

    expect(BlockSources.of(editor)).toBe(second)
    expect(second.resolve('b0', '- one')).toBe('- one')
  })

  it('keeps each editor to its own sources', () => {
    const editor = {}
    BlockSources.reset(editor).record('b0', '* one')
    BlockSources.reset({})

    expect(BlockSources.of(editor)?.resolve('b0', '- one')).toBe('* one')
  })
})
