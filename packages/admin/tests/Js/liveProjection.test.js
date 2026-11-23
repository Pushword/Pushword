import * as Y from 'yjs'
import { expect, it } from 'vitest'
import { markdownModel, seedDraft } from '../../src/Resources/assets/admin.draftSession.js'
import { project } from '../../src/Resources/assets/admin.liveProjection.js'

function snapshot(baseline, content) {
  const doc = new Y.Doc()
  seedDraft(doc, markdownModel, baseline)
  doc.transact(() => markdownModel.write(doc, baseline, content))
  const encoded = Buffer.from(Y.encodeStateAsUpdate(doc)).toString('base64')
  doc.destroy()
  return encoded
}

it('rejects undecodable state and content that differs from a decodable snapshot', () => {
  const seed = { carousel: false, baseline: 'Initial text', stored: null }
  expect(project({ ...seed, incoming: 'YWJj', content: 'Injected text' })).toBeNull()
  const incoming = snapshot(seed.baseline, 'Verified edit')
  expect(project({ ...seed, incoming, content: 'Different content' })).toBeNull()
  expect(project({ ...seed, incoming, content: 'Verified edit' })).toEqual({
    snapshot: incoming,
    content: 'Verified edit',
  })
})

it('rejects a proposed replacement that cannot be applied to the previous shared state as claimed', () => {
  const baseline = 'Alpha middle omega'
  const stored = snapshot(baseline, 'Alpha lovely middle omega')
  const incoming = snapshot(baseline, 'Alpha middle omega today')
  expect(project({ carousel: false, baseline, stored, incoming, content: 'Alpha middle omega today' })).toBeNull()
  expect(project({ carousel: false, baseline, stored, incoming, content: 'Alpha lovely middle omega today' })?.content)
    .toBe('Alpha lovely middle omega today')
})
