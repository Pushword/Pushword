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

it('preserves a pending edit between two API replacements when the participants merge', () => {
  const baseline = '🥾 Alpha middle omega'
  const beforePatch = '🥾 Alpha lovely middle omega'
  const stored = snapshot(baseline, beforePatch)
  const content = '🥾 Alpha wonderful middle last'
  const result = project({
    baseline: beforePatch,
    stored,
    patch: {
      replacements: [{ find: 'Alpha lovely', replace: 'Alpha wonderful' }, { find: 'omega', replace: 'last' }],
      content,
      normalized: content,
    },
  })
  expect(result?.content).toBe(content)

  const browser = new Y.Doc()
  Y.applyUpdate(browser, Buffer.from(stored, 'base64'))
  markdownModel.write(browser, beforePatch, '🥾 Alpha lovely edited-middle omega')
  Y.applyUpdate(browser, Buffer.from(result.snapshot, 'base64'))
  expect(markdownModel.read(browser)).toBe('🥾 Alpha wonderful edited-middle last')
  browser.destroy()
})

it('projects sequential replacements, repeated matches, deletions and the canonical body', () => {
  const baseline = '🥾 trip 🥾 trip'
  const patch = {
    replacements: [{ find: '🥾 trip', replace: '🚲 outing\r\n' }, { find: ' outing', replace: '' }],
    content: '🚲\r\n 🚲\r\n',
    normalized: '🚲\n 🚲',
  }
  const result = project({ baseline, patch })
  expect(result?.content).toBe(patch.normalized)
  expect(project({ baseline, patch: { ...patch, content: 'Unexpected text' } })).toBeNull()
  expect(project({ baseline, patch: { ...patch, normalized: 'Unexpected text' } })).toBeNull()
})

it('normalizes line endings and empty anchors without deleting text between them', () => {
  const baseline = 'Intro\n\nMiddle\n\nEnd'
  const stored = snapshot(baseline, '\v \tIntro\r\n\r\nMiddle<a id="empty"></a>\r\rEnd \v')
  const result = project({
    baseline, stored,
    patch: {
      replacements: [{ find: 'Intro', replace: 'First' }, { find: 'End', replace: 'Last' }],
      content: 'First\n\nMiddle\n\nLast',
      normalized: 'First\n\nMiddle\n\nLast',
    },
  })
  expect(result?.content).toBe('First\n\nMiddle\n\nLast')

  const browser = new Y.Doc()
  Y.applyUpdate(browser, Buffer.from(stored, 'base64'))
  const local = markdownModel.read(browser)
  markdownModel.write(browser, local, local.replace('Middle', 'Middle note'))
  Y.applyUpdate(browser, Buffer.from(result.snapshot, 'base64'))
  expect(markdownModel.read(browser)).toBe('First\n\nMiddle note\n\nLast')
  browser.destroy()
})

it('merges legacy edits into a server patch of the saved baseline', () => {
  const baseline = 'Alpha middle omega'
  const content = 'First middle omega'
  expect(project({
    baseline,
    legacy: [snapshot(baseline, 'Alpha middle omega today')],
    patch: { replacements: [{ find: 'Alpha', replace: 'First' }], content, normalized: content },
  })?.content).toBe('First middle omega today')
})
