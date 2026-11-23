// @vitest-environment jsdom
import * as Y from 'yjs'
import { afterEach, expect, it, vi } from 'vitest'
import { markdownModel, seedDraft } from '../../src/Resources/assets/admin.draftSession.js'
import { bindPageDraft } from '../../src/Resources/assets/admin.pageDraft.js'

afterEach(() => {
  window.dispatchEvent(new Event('pagehide'))
  document.body.replaceChildren()
  vi.unstubAllGlobals()
})

function pageDraftForm() {
  document.body.innerHTML = `
    <div data-pw-page-draft data-url="/draft" data-token="csrf" data-pending="Saving"
      data-connected="Saved" data-classic="Classic mode" data-settings-pending="Save settings first">
      <button type="button">Join</button><span role="status"></span>
    </div>
    <form data-pw-page-revision-form>
      <input data-pw-page-revision value="1">
      <textarea name="Page[mainContent]">Original body</textarea>
      <input name="Page[publishedAt]" value="2026-05-01T12:00">
    </form>`
  const controls = document.querySelector('[data-pw-page-draft]')
  const form = document.querySelector('form')
  return {
    controls, form,
    body: form.querySelector('textarea'),
    revision: form.querySelector('[data-pw-page-revision]'),
    publication: form.querySelector('[name$="[publishedAt]"]'),
    button: controls.querySelector('button'),
    status: controls.querySelector('[role="status"]'),
  }
}

function confirmSave(form, revision, current) {
  revision.value = current
  form.dispatchEvent(new CustomEvent('htmx:after:request', {
    detail: { ctx: { response: new Response(null, { headers: { 'X-Pushword-Page-Revision': current } }) } },
  }))
}

it('brings a body edit undone during a classic save into collaborative mode', async () => {
  const { controls, form, body, revision, button } = pageDraftForm()
  const nextBody = body.value
  const savedBody = 'Submitted body'
  const fetch = vi.fn(async (_url, { method, body }) => {
    if (method === 'DELETE') return new Response(null, { status: 204 })
    const request = JSON.parse(body)
    return Response.json({
      room: 'room', baseline: request.room === null ? savedBody : null,
      after: request.snapshot ? 1 : 0, revision: request.snapshot ? '3' : '2',
      snapshot: null, accepted: Boolean(request.snapshot),
    })
  })
  vi.stubGlobal('fetch', fetch)
  bindPageDraft(controls)

  body.value = savedBody
  form.dispatchEvent(new Event('htmx:before:request'))
  body.value = nextBody
  confirmSave(form, revision, '2')
  button.click()

  await vi.waitFor(() => {
    const requests = fetch.mock.calls.map(([, request]) => JSON.parse(request.body))
    expect(requests).toContainEqual(expect.objectContaining({ room: 'room', content: nextBody }))
  })
})

it('pauses live writes for a publication date changed during a classic save', async () => {
  const { controls, form, revision, publication, button, status } = pageDraftForm()
  const fetch = vi.fn(async () => Response.json({
    room: 'room', baseline: 'Original body', after: 0, revision: '2', snapshot: null, accepted: false,
  }))
  vi.stubGlobal('fetch', fetch)
  bindPageDraft(controls)

  const originalDate = publication.value
  publication.value = ''
  form.dispatchEvent(new Event('htmx:before:request'))
  publication.value = originalDate
  confirmSave(form, revision, '2')
  button.click()

  await vi.waitFor(() => expect(status.textContent).toBe('Save settings first'))
  expect(fetch).not.toHaveBeenCalled()
})

it('keeps the room when a settings save fails and leaves after the retry succeeds', async () => {
  const { controls, form, body, revision, publication, button, status } = pageDraftForm()
  const fetch = vi.fn(async (_url, { method }) => method === 'DELETE'
    ? new Response(null, { status: 204 })
    : Response.json({
      room: 'room', baseline: 'Original body', after: 0, revision: '1', snapshot: null, accepted: false,
    }))
  vi.stubGlobal('fetch', fetch)
  bindPageDraft(controls)
  button.click()
  await vi.waitFor(() => expect(status.textContent).toBe('Saved'))

  publication.value = ''
  form.dispatchEvent(new Event('htmx:before:request'))
  form.dispatchEvent(new CustomEvent('htmx:after:request', {
    detail: { ctx: { response: new Response(null, { status: 409 }) } },
  }))
  expect(button.getAttribute('aria-pressed')).toBe('true')
  body.value = 'New body while the publication date is unsaved'
  body.dispatchEvent(new Event('input', { bubbles: true }))
  expect(status.textContent).toBe('Save settings first')

  form.dispatchEvent(new Event('htmx:before:request'))
  confirmSave(form, revision, '2')
  expect(button.getAttribute('aria-pressed')).toBe('false')
  expect(fetch).toHaveBeenLastCalledWith('/draft', expect.objectContaining({ method: 'DELETE' }))
})

it('opens one room when the mode button is clicked again during editor preparation', async () => {
  const { controls, body, button, status } = pageDraftForm()
  const { promise, resolve } = Promise.withResolvers()
  body.pwEditor = { flushDraft: () => promise }
  const fetch = vi.fn(async () => Response.json({
    room: 'room', baseline: 'Original body', after: 0, revision: '1', snapshot: null, accepted: false,
  }))
  vi.stubGlobal('fetch', fetch)
  bindPageDraft(controls)

  button.click()
  button.click()
  resolve()

  await vi.waitFor(() => expect(status.textContent).toBe('Saved'))
  expect(fetch).toHaveBeenCalledOnce()
})

it('applies the live body to Monaco and switches back to classic mode', async () => {
  document.body.innerHTML = `
    <div data-pw-page-draft data-url="/draft" data-token="csrf" data-connected="saved"
      data-updated="updated" data-classic="Classic mode" data-settings-pending="Save settings first"><button type="button">Join</button><span role="status"></span></div>
    <form data-pw-page-revision-form>
      <input data-pw-page-revision value="1">
      <textarea name="Page[mainContent]">Alpha omega.</textarea>
      <input name="Page[publishedAt]" value="2026-05-01T12:00">
      <button type="submit">Save</button>
    </form>`

  const form = document.querySelector('form')
  const input = form.querySelector('textarea')
  const controls = document.querySelector('[data-pw-page-draft]')
  const status = controls.querySelector('[role="status"]')
  const executeEdits = vi.fn((_origin, [{ range, text }]) => {
    input.setRangeText(text, range.startColumn - 1, range.endColumn - 1)
    input.dispatchEvent(new Event('input', { bubbles: true }))
  })
  input.pwMonaco = {
    editor: { getModel: () => ({ getPositionAt: (offset) => ({ lineNumber: 1, column: offset + 1 }) }), executeEdits },
  }

  const doc = new Y.Doc()
  seedDraft(doc, markdownModel, input.value)
  markdownModel.write(doc, input.value, 'Alpha lively omega.')
  const update = btoa(String.fromCharCode(...Y.encodeStateAsUpdate(doc)))
  const fetch = vi.fn(async (_url, { body }) => {
    const { after } = JSON.parse(body)
    return Response.json({
      revision: '2', room: 'a'.repeat(32), baseline: null,
      after: 1, snapshot: after === 0 ? update : null, accepted: false,
    })
  })
  vi.stubGlobal('fetch', fetch)
  bindPageDraft(controls)
  controls.querySelector('button').click()
  await vi.waitFor(() => expect(status.textContent).toBe('updated'))
  expect(executeEdits).toHaveBeenCalledOnce()
  expect(input.value).toBe('Alpha lively omega.')

  const submit = vi.fn((event) => {
    event.preventDefault()
    expect(input.value).toBe('Alpha lively omega.')
    expect(event.submitter).toBe(form.querySelector('button'))
  })
  form.addEventListener('submit', submit)
  form.requestSubmit(form.querySelector('button'))
  await vi.waitFor(() => expect(submit).toHaveBeenCalledOnce())
  expect(fetch).toHaveBeenCalledTimes(2)

  expect(form.querySelector('[data-pw-page-revision]').value).toBe('2')
  expect(controls.querySelector('button').textContent).toBe('Classic mode')

  const date = form.querySelector('[name$="[publishedAt]"]')
  date.value = ''
  date.dispatchEvent(new Event('change', { bubbles: true }))
  expect(status.textContent).toBe('Save settings first')
  controls.querySelector('button').click()
  await vi.waitFor(() => expect(controls.querySelector('button').textContent).toBe('Join'))
  expect(form.hasAttribute('data-pw-draft-active')).toBe(false)
  expect(fetch.mock.calls.filter(([, request]) => request.method === 'POST')).toHaveLength(2)
  doc.destroy()
})


it('leaves legacy serialized blocks to the regular editor', () => {
  document.body.innerHTML = `
    <div data-pw-page-draft><button type="button">Join</button><span role="status"></span></div>
    <form data-pw-page-revision-form>
      <textarea name="Page[mainContent]">{"blocks":[{"type":"paragraph","data":{"text":"Legacy body"}}]}</textarea>
    </form>`
  const controls = document.querySelector('[data-pw-page-draft]')
  bindPageDraft(controls)
  expect(controls.hidden).toBe(true)
  expect(document.querySelector('textarea').value).toContain('Legacy body')
})

it('offers live editing without switching modes or changing local edits', () => {
  document.body.innerHTML = `
    <div data-pw-page-draft data-join="Join live editing">
      <button type="button" aria-pressed="false">Collaborative mode</button>
      <span role="status"></span><span data-pw-collaboration-notice hidden>Another editor is present</span>
    </div>
    <form data-pw-page-revision-form>
      <input data-pw-page-revision value="4">
      <textarea name="Page[mainContent]">Saved body</textarea>
    </form>`
  const form = document.querySelector('form')
  const body = form.querySelector('textarea')
  const controls = document.querySelector('[data-pw-page-draft]')
  const button = controls.querySelector('button')
  const notice = controls.querySelector('[data-pw-collaboration-notice]')
  bindPageDraft(controls)
  body.value = 'My local edits'

  form.dispatchEvent(new CustomEvent('pw:collaboration-presence', { detail: true }))
  expect(notice.hidden).toBe(false)
  expect(button.textContent).toBe('Join live editing')
  expect(button.getAttribute('aria-pressed')).toBe('false')
  expect(body.value).toBe('My local edits')
  expect(form.querySelector('[data-pw-page-revision]').value).toBe('4')

  form.dispatchEvent(new CustomEvent('pw:collaboration-presence', { detail: false }))
  expect(notice.hidden).toBe(true)
  expect(button.textContent).toBe('Collaborative mode')
})
