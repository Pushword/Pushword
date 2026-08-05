import { describe, it, expect } from 'vitest'
import { beginMediaPick, MediaUtils } from './media'

/**
 * Image blocks, card lists and quizzes all open the one picker modal through the
 * one hidden <select>, so the pick registry is shared: whoever opens next owns
 * the answer, and the opener it displaced must already have stopped listening.
 */
describe('beginMediaPick', () => {
  it('drops what the previous pick left listening, whichever tool opened it', () => {
    const abandoned = beginMediaPick()
    expect(abandoned.signal.aborted).toBe(false)

    const picking = beginMediaPick()

    expect(abandoned.signal.aborted).toBe(true)
    expect(picking.signal.aborted).toBe(false)
  })
})

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
