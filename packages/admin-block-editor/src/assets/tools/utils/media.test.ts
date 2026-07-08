import { describe, it, expect } from 'vitest'
import { MediaUtils } from './media'

describe('MediaUtils.uploadErrorMessage', () => {
  it('returns the server-provided error from the failure body', async () => {
    const response = new Response(JSON.stringify({ success: 0, error: 'mediaTypeMismatch' }), {
      status: 422,
    })
    expect(await MediaUtils.uploadErrorMessage(response)).toBe('mediaTypeMismatch')
  })

  it('falls back to the HTTP status when the body has no error field', async () => {
    const response = new Response(JSON.stringify({ success: 1 }), { status: 500 })
    expect(await MediaUtils.uploadErrorMessage(response)).toBe('HTTP 500')
  })

  it('falls back to the HTTP status when the error field is empty', async () => {
    const response = new Response(JSON.stringify({ success: 0, error: '' }), { status: 422 })
    expect(await MediaUtils.uploadErrorMessage(response)).toBe('HTTP 422')
  })

  it('falls back to the HTTP status when the body is not JSON (e.g. an HTML error page)', async () => {
    const response = new Response('<html><body>Internal Server Error</body></html>', {
      status: 502,
    })
    expect(await MediaUtils.uploadErrorMessage(response)).toBe('HTTP 502')
  })
})
