// @vitest-environment jsdom
import * as Y from 'yjs'
import { afterEach, expect, it, vi } from 'vitest'
import { carouselModel, draftCarousel, equalJson, markdownModel, openDraft, reconcileJson, textChange } from '../../src/Resources/assets/admin.draftSession.js'

const sessions = []

afterEach(() => {
  sessions.splice(0).forEach((session) => session.close())
  vi.unstubAllGlobals()
})

function sharedRoom(baseline) {
  const updates = []
  const room = { offline: false, stale: false }
  vi.stubGlobal('fetch', vi.fn(async (_url, { body, headers }) => {
    if (room.offline) throw new Error('Connection interrupted')
    if (room.stale) return new Response(null, { status: 409 })
    expect(headers['If-Match']).toBe('1')
    const { after, update } = JSON.parse(body)
    if (update && !updates.includes(update)) updates.push(update)
    return Response.json({ revision: '1', baseline: after === 0 ? baseline : null, after: updates.length, updates: updates.slice(after), more: false })
  }))
  return room
}

function participant(initial, model = markdownModel) {
  let value = initial
  const status = vi.fn()
  const session = openDraft({
    url: '/draft', token: 'token', revision: '1', model, status,
    view: { read: () => value, equal: equalJson, apply: (next) => { value = next } },
  })
  sessions.push(session)
  return {
    ...session, status,
    get value() { return value },
    type(next) {
      value = next
      session.capture()
    },
  }
}

it('merges edits in the same sentence, keeps pending edits on disconnect, and restores a draft after reopening', async () => {
  const room = sharedRoom('Alpha middle omega.')
  const alice = participant('Alpha middle omega.')
  const bob = participant('Alpha middle omega.')
  expect(await alice.flush()).toBe(true)
  expect(await bob.flush()).toBe(true)
  room.offline = true
  alice.type('Alpha lovely middle omega.')
  bob.type('Alpha middle omega ending.')
  expect(await bob.flush()).toBe(false)
  expect(bob.value).toBe('Alpha middle omega ending.')
  expect(bob.status).toHaveBeenLastCalledWith('offline')
  room.offline = false
  expect(await alice.flush()).toBe(true)
  expect(await bob.flush()).toBe(true)
  expect(await alice.flush()).toBe(true)
  expect(alice.value).toBe('Alpha lovely middle omega ending.')
  expect(bob.value).toBe(alice.value)
  const reopened = participant('Alpha middle omega.')
  expect(await reopened.flush()).toBe(true)
  expect(reopened.value).toBe(bob.value)
  room.stale = true
  expect(await bob.flush()).toBe(false)
  expect(bob.status).toHaveBeenLastCalledWith('stale')
  expect(bob.value).toBe(alice.value)
})

it('keeps collaborative edits on the original slide after a concurrent reorder', async () => {
  const original = { slides: [{ title: 'A nice hike' }, { title: 'Another slide' }] }
  sharedRoom(JSON.stringify(original))
  const start = () => draftCarousel(structuredClone(original))
  const alice = participant(start(), carouselModel)
  const bob = participant(start(), carouselModel)
  expect(await alice.flush()).toBe(true)
  expect(await bob.flush()).toBe(true)
  const moved = start()
  moved.slides.reverse()
  alice.type(moved)
  const edited = start()
  edited.slides[0].title = 'A very nice hike'
  bob.type(edited)
  expect(await alice.flush()).toBe(true)
  expect(await bob.flush()).toBe(true)
  expect(await alice.flush()).toBe(true)
  expect(alice.value.slides.map((slide) => slide.title)).toEqual(['Another slide', 'A very nice hike'])
  expect(bob.value).toEqual(alice.value)
})

it('edits Unicode without splitting a surrogate pair', () => {
  expect(textChange('A 🥾!', 'A 🧭!')).toEqual({ from: 2, to: 4, insert: '🧭' })
  expect(textChange('A 🥾!', 'A 🥾 good hike!')).toEqual({ from: 4, to: 4, insert: ' good hike' })
})

it('reconciles remote JSON in place and ignores inherited property keys', () => {
  const slide = { title: 'A' }
  const target = { slides: [slide, { title: 'B' }], video: '' }
  reconcileJson(target, JSON.parse('{"slides":[{"title":"Changed"}],"__proto__":{"polluted":true}}'))
  expect(target).toEqual({ slides: [{ title: 'Changed' }] })
  expect(target.slides[0]).toBe(slide)
  expect(Object.getPrototypeOf(target)).toBe(Object.prototype)
})

it('keeps empty title text mergeable instead of replacing the slide field', () => {
  const doc = new Y.Doc()
  const original = draftCarousel({ slides: [{}] })
  carouselModel.seed(doc, JSON.stringify(original))
  const next = draftCarousel({ slides: [{ title: 'New' }] })
  carouselModel.write(doc, original, next)
  carouselModel.write(doc, next, original)
  expect(carouselModel.read(doc)).toEqual(original)
})
