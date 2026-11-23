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
