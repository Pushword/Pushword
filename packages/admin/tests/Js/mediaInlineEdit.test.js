// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
  deleteMedia,
  saveField,
} from '../../src/Resources/assets/admin.mediaInlineEdit.js'

const ctx = {
  inlineUpdateUrl: '/admin/media/__ID__/inline-update',
  deleteUrl: '/admin/media/__ID__/multi-delete',
  csrfToken: 't',
}

function row() {
  document.body.innerHTML = `
    <table><tr>
      <td><input data-field="alt" data-id="7" value="typed"></td>
      <td><span class="pw-row-status"></span></td>
    </tr></table>`
  return document.querySelector('tr')
}

function respond(status, body) {
  vi.stubGlobal(
    'fetch',
    vi.fn(async () => new Response(JSON.stringify(body), { status })),
  )
}

beforeEach(() => {
  vi.unstubAllGlobals()
})

describe('saveField', () => {
  it('shows the reason a refused edit was not saved under its field', async () => {
    const tr = row()
    const input = tr.querySelector('input')
    respond(422, { error: 'Refused by the media policy.' })

    await saveField(input, tr, ctx)

    expect(input.classList.contains('is-invalid')).toBe(true)
    expect(tr.querySelector('.pw-field-error').textContent).toBe(
      'Refused by the media policy.',
    )
    // The typed value stays, so the editor can fix it rather than retype it.
    expect(input.value).toBe('typed')
  })

  it('clears the reason once the field saves', async () => {
    const tr = row()
    const input = tr.querySelector('input')
    respond(422, { error: 'Refused by the media policy.' })
    await saveField(input, tr, ctx)

    respond(200, { success: true })
    await saveField(input, tr, ctx)

    expect(input.classList.contains('is-invalid')).toBe(false)
    expect(tr.querySelector('.pw-field-error')).toBeNull()
  })
})

describe('deleteMedia', () => {
  it('names why the media was kept', async () => {
    const tr = row()
    respond(409, { error: 'Used by a slideshow.', reasons: ['Used by a slideshow.'] })

    await deleteMedia(7, tr, ctx)

    expect(document.body.textContent).toContain('Used by a slideshow.')
    expect(document.querySelector('tr')).not.toBeNull()
  })
})
