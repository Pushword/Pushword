import * as Y from 'yjs'

const REMOTE = Symbol('remote')

export function textChange(previous, next) {
  let from = 0
  while (from < previous.length && from < next.length && previous[from] === next[from]) from++
  // A text position should never split a surrogate pair.
  if (from > 0 && /[\uD800-\uDBFF]/.test(previous[from - 1])) from--
  let end = previous.length
  let nextEnd = next.length
  while (end > from && nextEnd > from && previous[end - 1] === next[nextEnd - 1]) {
    end--
    nextEnd--
  }
  if (/[\uDC00-\uDFFF]/.test(previous[end] ?? '')) {
    end++
    nextEnd++
  }

  return { from, to: end, insert: next.slice(from, nextEnd) }
}

export function writeText(text, next) {
  const previous = text.toString()
  if (previous === next) return
  const { from, to, insert } = textChange(previous, next)
  if (from !== to) text.delete(from, to - from)
  if (insert) text.insert(from, insert)
}

const encode = (bytes) => btoa(Array.from(bytes, (byte) => String.fromCharCode(byte)).join(''))
const decode = (update) => Uint8Array.from(atob(update), (character) => character.charCodeAt(0))

export function seedDraft(doc, model, baseline) {
  // All participants must import identical baseline IDs for the same revision.
  const seed = new Y.Doc()
  seed.clientID = 1
  seed.transact(() => model.seed(seed, baseline))
  Y.applyUpdate(doc, Y.encodeStateAsUpdate(seed), REMOTE)
  seed.destroy()
}

export function openDraft({ url, revision, token, authorization, model, view, status, initial = view.read() }) {
  const doc = new Y.Doc()
  const pending = []
  let cursor = 0
  let seeded = false
  let rendered = initial
  let active = true
  let request = null
  let applying = false
  let mustRender = false

  doc.on('update', (update, origin) => {
    if (origin !== REMOTE) pending.push(update)
  })

  function capture() {
    if (!active || !seeded || applying) return
    const current = view.read()
    if (current === undefined || view.equal(rendered, current)) return
    doc.transact(() => model.write(doc, rendered, current))
    rendered = current
    status('pending')
  }

  async function exchange(force) {
    if (!active || (!force && (view.wait?.() || document.hidden))) return false
    capture()
    const sent = pending.length
    const response = await fetch(url, {
      method: 'POST',
      redirect: 'error',
      signal: AbortSignal.timeout(3500),
      headers: {
        'Content-Type': 'application/json',
        ...(revision ? { 'If-Match': revision } : {}),
        ...(token ? { 'X-Collaboration-Token': token } : {}),
        ...(authorization ? { Authorization: authorization } : {}),
      },
      body: JSON.stringify({ after: cursor, update: sent ? encode(Y.mergeUpdates(pending)) : null }),
    })
    if (!active) return false
    if ([404, 409].includes(response.status)) {
      active = false
      clearInterval(timer)
      status('stale')
      return false
    }
    if (!response.ok) throw new Error('Draft sync failed')

    const result = await response.json()
    if (!active || (!force && view.wait?.()) || view.read() === undefined) return false
    revision = result.revision
    // Capture anything typed during the request before integrating its response.
    if (!seeded) {
      seedDraft(doc, model, result.baseline)
      seeded = true
    }
    if (result.updates.length && view.prepare) await view.prepare()
    if (!active || (!force && view.wait?.()) || view.read() === undefined) return false
    capture()
    const before = model.read(doc)
    doc.transact(() => {
      for (const update of result.updates) Y.applyUpdate(doc, decode(update), REMOTE)
    }, REMOTE)
    const merged = model.read(doc)
    const changed = !view.equal(before, merged)
    mustRender ||= changed || (result.updates.length > 0 && cursor === 0)
    if (mustRender) {
      applying = true
      try {
        await view.apply(merged)
        rendered = view.read()
        mustRender = false
      } finally {
        applying = false
      }
    }
    pending.splice(0, sent)
    cursor = result.after
    status(pending.length ? 'pending' : changed ? 'updated' : 'connected')
    return !result.more && pending.length === 0
  }

  function sync(force = false) {
    if (request) return request
    request = exchange(force).catch(() => {
      if (active) status('offline')
      return false
    }).finally(() => { request = null })
    return request
  }

  const timer = setInterval(() => void sync(), 350)
  status('pending')
  void sync()

  return {
    capture,
    async flush() {
      if (await sync(true)) return true
      return active && sync(true)
    },
    close() {
      active = false
      clearInterval(timer)
      doc.destroy()
    },
  }
}

export const markdownModel = {
  seed: (doc, baseline) => writeText(doc.getText('body'), baseline),
  read: (doc) => doc.getText('body').toString(),
  write: (doc, _previous, next) => writeText(doc.getText('body'), next),
}

function fromValue(value) {
  if (typeof value === 'string') {
    const text = new Y.Text()
    text.insert(0, value)
    return text
  }
  if (Array.isArray(value)) {
    const array = new Y.Array()
    const order = new Y.Array()
    const items = new Y.Map()
    const ids = value.map((item, index) => {
      const id = String(index)
      items.set(id, fromValue(item))
      return id
    })
    order.push(ids)
    array.push([order, items])
    return array
  }
  if (value !== null && typeof value === 'object') {
    const map = new Y.Map()
    for (const [key, child] of Object.entries(value)) map.set(key, fromValue(child))
    return map
  }
  return value
}

export const equalJson = (left, right) => JSON.stringify(left) === JSON.stringify(right)

function patchMap(map, previous, next) {
  for (const key of Object.keys(previous)) {
    if (!Object.hasOwn(next, key)) map.delete(key)
  }
  for (const [key, value] of Object.entries(next)) {
    if (!equalJson(value, previous[key])) {
      const updated = patchValue(map.get(key), previous[key], value)
      if (updated !== map.get(key)) map.set(key, updated)
    }
  }
}

function arrayParts(array) {
  const [order, items] = array.toArray()
  return { order, items, ids: [...new Set(order.toArray())] }
}

function patchArray(array, previous, next) {
  const { order, items, ids } = arrayParts(array)
  const available = new Map()
  previous.forEach((value, index) => {
    const key = JSON.stringify(value)
    if (!available.has(key)) available.set(key, [])
    available.get(key).push(index)
  })
  // Object identity lives in the item map, so moving a slide leaves a
  // concurrent edit to that slide attached to the same item.
  const matches = next.map((value) => available.get(JSON.stringify(value))?.shift())
  const unmatched = [...available.values()].flat().sort((left, right) => left - right)
  const nextIds = next.map((value, index) => {
    if (matches[index] !== undefined) return ids[matches[index]]
    const matched = previous.length === next.length ? unmatched.shift() : undefined
    const id = matched === undefined ? crypto.randomUUID() : ids[matched]
    const current = items.get(id)
    const updated = patchValue(current, previous[matched], value)
    if (updated !== current) items.set(id, updated)
    return id
  })

  const stored = order.toArray()
  let start = 0
  while (start < stored.length && start < nextIds.length && stored[start] === nextIds[start]) start++
  let end = stored.length
  let nextEnd = nextIds.length
  while (end > start && nextEnd > start && stored[end - 1] === nextIds[nextEnd - 1]) {
    end--
    nextEnd--
  }
  if (end > start) order.delete(start, end - start)
  if (nextEnd > start) order.insert(start, nextIds.slice(start, nextEnd))
}

function readValue(value) {
  if (value instanceof Y.Array) {
    const { items, ids } = arrayParts(value)
    return ids.map((id) => readValue(items.get(id)))
  }
  if (value instanceof Y.Map) {
    return Object.fromEntries(Array.from(value, ([key, child]) => [key, readValue(child)]))
  }
  return value instanceof Y.Text ? value.toString() : value
}

function patchValue(shared, previous, next) {
  if (shared instanceof Y.Text && typeof next === 'string') {
    writeText(shared, next)
  } else if (shared instanceof Y.Map && previous && next && typeof next === 'object' && !Array.isArray(next)) {
    patchMap(shared, previous, next)
  } else if (shared instanceof Y.Array && Array.isArray(previous) && Array.isArray(next)) {
    patchArray(shared, previous, next)
  } else {
    return fromValue(next)
  }
  return shared
}

export function draftCarousel(spec) {
  spec.caption ??= ''
  for (const slide of spec.slides ?? []) {
    for (const key of ['title', 'tagline', 'paragraph']) slide[key] ??= ''
  }
  return spec
}

export const carouselModel = {
  seed: (doc, baseline) => patchMap(doc.getMap('carousel'), {}, draftCarousel(JSON.parse(baseline))),
  read: (doc) => readValue(doc.getMap('carousel')),
  write: (doc, previous, next) => patchMap(doc.getMap('carousel'), previous, next),
}

export function reconcileJson(target, value) {
  if (Array.isArray(target) && Array.isArray(value)) target.length = value.length
  for (const key of Object.keys(target)) {
    if (!Object.hasOwn(value, key)) delete target[key]
  }
  for (const [key, next] of Object.entries(value)) {
    if (key === '__proto__' || key === 'constructor' || key === 'prototype') continue
    const previous = Object.hasOwn(target, key) ? target[key] : undefined
    if (equalJson(previous, next)) continue
    if (previous && next && typeof previous === 'object' && typeof next === 'object' &&
      Array.isArray(previous) === Array.isArray(next)) {
      reconcileJson(previous, next)
    } else {
      target[key] = next
    }
  }
}
