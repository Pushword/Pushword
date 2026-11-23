import * as Y from 'yjs'

const REMOTE = Symbol('remote')
const POLL_MS = 300

const decode = (value) => Uint8Array.from(atob(value), (character) => character.charCodeAt(0))

function encode(bytes) {
  let binary = ''
  for (const byte of bytes) binary += String.fromCharCode(byte)
  return btoa(binary)
}

function applyInput(text, input) {
  const previous = Array.from(text.toString())
  const next = Array.from(input.value)
  let start = 0
  while (start < previous.length && start < next.length && previous[start] === next[start]) start++

  let previousEnd = previous.length
  let nextEnd = next.length
  while (previousEnd > start && nextEnd > start && previous[previousEnd - 1] === next[nextEnd - 1]) {
    previousEnd--
    nextEnd--
  }

  // Y.Text and textarea selections count UTF-16 units. Compare full code points
  // first so replacing an emoji does not split its surrogate pair.
  const offset = previous.slice(0, start).join('').length
  const removed = previous.slice(start, previousEnd).join('').length
  const inserted = next.slice(start, nextEnd).join('')
  text.doc.transact(() => {
    if (removed) text.delete(offset, removed)
    if (inserted) text.insert(offset, inserted)
  })
}

export function bindCollaboration(input, exchange, showStatus) {
  const doc = new Y.Doc()
  const text = doc.getText('body')
  const pending = []
  let after = 0
  let busy = false
  let composing = false
  let paused = false

  doc.on('update', (update, origin) => {
    if (origin !== REMOTE) pending.push(update)
  })
  input.addEventListener('compositionstart', () => { composing = true })
  input.addEventListener('compositionend', () => {
    composing = false
    applyInput(text, input)
  })
  input.addEventListener('input', () => {
    if (!composing) applyInput(text, input)
  })

  function receive(updates) {
    const start = Y.createRelativePositionFromTypeIndex(text, input.selectionStart)
    const end = Y.createRelativePositionFromTypeIndex(text, input.selectionEnd)
    doc.transact(() => {
      for (const update of updates) Y.applyUpdate(doc, decode(update), REMOTE)
    }, REMOTE)
    const latest = text.toString()
    if (input.value === latest) return

    const direction = input.selectionDirection
    input.value = latest
    input.setSelectionRange(
      Y.createAbsolutePositionFromRelativePosition(start, doc).index,
      Y.createAbsolutePositionFromRelativePosition(end, doc).index,
      direction,
    )
  }

  async function sync() {
    if (busy || paused || composing) return
    busy = true
    const sent = pending.length
    try {
      const update = sent ? encode(Y.mergeUpdates(pending)) : null
      const response = await exchange({ after, update })
      if (paused || composing) return
      receive(response.updates)
      pending.splice(0, sent)
      after = response.after
      showStatus('connected')
    } catch {
      if (!paused) showStatus('offline')
    } finally {
      busy = false
    }
  }

  return {
    sync,
    setPaused(value) {
      paused = value
      if (paused) showStatus('paused')
      else void sync()
    },
  }
}

export function startCollaboration(editor) {
  const input = editor.querySelector('textarea')
  const status = editor.querySelector('[role="status"]')
  const pause = editor.querySelector('[data-pause]')
  const messages = {
    connected: editor.dataset.statusConnected,
    offline: editor.dataset.statusOffline,
    paused: editor.dataset.statusPaused,
  }
  const exchange = async (message) => {
    const response = await fetch(editor.dataset.syncUrl, {
      method: 'POST',
      redirect: 'error',
      signal: AbortSignal.timeout(3000),
      headers: {
        'Content-Type': 'application/json',
        'X-Collaboration-Token': editor.dataset.syncToken,
      },
      body: JSON.stringify(message),
    })
    if (!response.ok) throw new Error('Sync failed')
    return response.json()
  }

  const session = bindCollaboration(input, exchange, (state) => {
    const message = messages[state]
    if (status.textContent !== message) status.textContent = message
    if (state === 'connected') {
      input.disabled = false
      pause.disabled = false
    }
  })
  pause.addEventListener('click', () => {
    const paused = pause.getAttribute('aria-pressed') !== 'true'
    pause.setAttribute('aria-pressed', String(paused))
    session.setPaused(paused)
  })
  void session.sync()
  window.setInterval(session.sync, POLL_MS)
}

const editor = document.querySelector('[data-collaboration-poc]')
if (editor) startCollaboration(editor)
