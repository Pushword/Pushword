// @vitest-environment jsdom
import { expect, it, vi } from 'vitest'
import { bindCollaboration, startCollaboration } from '../../src/Resources/assets/admin.collaborationPoc.js'

function room() {
  const updates = []
  return async ({ after, update }) => {
    if (update) updates.push(update)
    return { after: updates.length, updates: updates.slice(after) }
  }
}

function editor(exchange) {
  const input = document.createElement('textarea')
  const status = vi.fn()
  return {
    input,
    status,
    ...bindCollaboration(input, exchange, status),
    type(value) {
      input.value = value
      input.dispatchEvent(new Event('input'))
    },
  }
}

it('merges two edits to the same paragraph while one participant is disconnected', async () => {
  const exchange = room()
  const first = editor(exchange)
  const second = editor(exchange)
  first.type('Un café et du thé.')
  await first.sync()
  await second.sync()

  second.setPaused(true)
  first.type('Un bon café et du thé.')
  second.type('Un café et du thé au citron.')
  await first.sync()
  await second.sync()
  expect(second.input.value).toBe('Un café et du thé au citron.')
  expect(second.status).toHaveBeenLastCalledWith('paused')

  second.setPaused(false)
  await vi.waitFor(() => expect(second.status).toHaveBeenLastCalledWith('connected'))
  await first.sync()
  expect(first.input.value).toBe('Un bon café et du thé au citron.')
  expect(second.input.value).toBe(first.input.value)
})

it('retries an update if the server stored it but the acknowledgement was lost', async () => {
  const exchange = room()
  const first = editor(async (message) => {
    const response = await exchange(message)
    if (first.status.mock.calls.length === 0) throw new Error('Connection interrupted')
    return response
  })
  first.type('Once only')
  await first.sync()
  expect(first.status).toHaveBeenLastCalledWith('offline')

  first.type('Once only, plus a follow-up')
  await first.sync()
  const second = editor(exchange)
  await second.sync()
  expect(second.input.value).toBe('Once only, plus a follow-up')
})

it('keeps a text selection anchored through remote edits and supports emoji replacement', async () => {
  const exchange = room()
  const first = editor(exchange)
  const second = editor(exchange)
  first.type('🥾 Summit')
  await first.sync()
  await second.sync()
  second.input.setSelectionRange(3, 9, 'backward')

  first.type('⛰️ At the Summit')
  await first.sync()
  await second.sync()
  expect(second.input.value).toBe('⛰️ At the Summit')
  expect(second.input.value.slice(second.input.selectionStart, second.input.selectionEnd)).toBe('Summit')
  expect(second.input.selectionDirection).toBe('backward')
})

it('holds remote changes until a browser text composition finishes', async () => {
  const exchange = room()
  const first = editor(exchange)
  const second = editor(exchange)
  first.type('Café.')
  await first.sync()
  await second.sync()

  second.input.dispatchEvent(new Event('compositionstart'))
  second.type('Café à Paris.')
  first.type('Un café.')
  await first.sync()
  await second.sync()
  expect(second.input.value).toBe('Café à Paris.')

  second.input.dispatchEvent(new Event('compositionend'))
  await second.sync()
  await first.sync()
  expect(first.input.value).toBe('Un café à Paris.')
  expect(second.input.value).toBe(first.input.value)
})

it('does not acknowledge edits typed while a request is in flight', async () => {
  const exchange = room()
  let release
  const delayed = vi.fn(async (message) => {
    const response = await exchange(message)
    if (!release) await new Promise((resolve) => { release = resolve })
    return response
  })
  const first = editor(delayed)
  first.type('First')
  const syncing = first.sync()
  await vi.waitFor(() => expect(release).toBeTypeOf('function'))
  first.type('First and second')
  await first.sync()
  expect(delayed).toHaveBeenCalledOnce()
  release()
  await syncing
  expect(first.input.value).toBe('First and second')
  await first.sync()

  const second = editor(exchange)
  await second.sync()
  expect(second.input.value).toBe('First and second')
})

it('pulls remote edits without echoing them back as local updates', async () => {
  const exchange = vi.fn(room())
  const first = editor(exchange)
  const second = editor(exchange)
  first.type('Shared text')
  await first.sync()
  await first.sync()
  expect(exchange).toHaveBeenLastCalledWith({ after: 1, update: null })

  await second.sync()
  await second.sync()
  expect(exchange).toHaveBeenLastCalledWith({ after: 1, update: null })
})

it('does not replace a composing input when a reply arrives mid-composition', async () => {
  const exchange = room()
  let reply
  const first = editor(exchange)
  const second = editor(async (message) => {
    const response = await exchange(message)
    await new Promise((resolve) => { reply = resolve })
    return response
  })
  first.type('Agent wrote this.')
  await first.sync()
  const syncing = second.sync()
  await vi.waitFor(() => expect(reply).toBeTypeOf('function'))
  second.input.dispatchEvent(new Event('compositionstart'))
  second.type('Étape en cours')
  reply()
  await syncing
  expect(second.input.value).toBe('Étape en cours')
})

it('initializes the screen, polls and holds edits until sync resumes', async () => {
  vi.useFakeTimers()
  const exchange = room()
  const fetch = vi.fn(async (_url, options) => ({ ok: true, json: () => exchange(JSON.parse(options.body)) }))
  vi.stubGlobal('fetch', fetch)
  const form = document.createElement('main')
  form.dataset.syncUrl = '/sync'
  form.dataset.syncToken = 'editor-room-token'
  form.dataset.statusConnected = 'Connected'
  form.dataset.statusOffline = 'Offline'
  form.dataset.statusPaused = 'Paused'
  form.innerHTML = '<textarea disabled></textarea><span role="status">Connecting</span>' +
    '<button type="button" aria-pressed="false" data-pause disabled>Pause sync</button>'
  const input = form.querySelector('textarea')
  const status = form.querySelector('[role="status"]')
  const pause = form.querySelector('button')

  try {
    fetch.mockResolvedValueOnce({ ok: false })
    startCollaboration(form)
    await vi.advanceTimersByTimeAsync(0)
    expect(fetch).toHaveBeenCalledWith('/sync', expect.objectContaining({
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Collaboration-Token': 'editor-room-token' },
      body: JSON.stringify({ after: 0, update: null }),
    }))
    expect(input.disabled).toBe(true)
    expect(pause.disabled).toBe(true)
    expect(status.textContent).toBe('Offline')
    await vi.advanceTimersByTimeAsync(299)
    expect(fetch).toHaveBeenCalledTimes(1)
    await vi.advanceTimersByTimeAsync(1)
    expect(input.disabled).toBe(false)
    expect(status.textContent).toBe('Connected')

    pause.click()
    input.value = 'Draft during pause'
    input.dispatchEvent(new Event('input'))
    await vi.advanceTimersByTimeAsync(900)
    expect(fetch).toHaveBeenCalledTimes(2)
    expect(pause.getAttribute('aria-pressed')).toBe('true')
    expect(status.textContent).toBe('Paused')

    pause.click()
    await vi.advanceTimersByTimeAsync(0)
    expect(fetch).toHaveBeenCalledTimes(3)
    expect(JSON.parse(fetch.mock.calls[2][1].body).update).toBeTruthy()
    expect(pause.getAttribute('aria-pressed')).toBe('false')
    expect(status.textContent).toBe('Connected')
  } finally {
    vi.useRealTimers()
    vi.unstubAllGlobals()
  }
})
