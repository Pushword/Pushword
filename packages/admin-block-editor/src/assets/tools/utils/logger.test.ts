import { afterEach, expect, it, vi } from 'vitest'
import { logger } from './logger'

afterEach(() => vi.restoreAllMocks())

it.each([false, true])(
  'reports caught tool failures, including nested error metadata: %s',
  (nested) => {
    document.body.innerHTML = '<div class="editorjs-holder"></div>'
    vi.spyOn(console, 'error').mockImplementation(() => {})
    const reported = vi.fn()
    document
      .querySelector('.editorjs-holder')!
      .addEventListener('pw:editor-error', reported)
    const error = new TypeError('private details')
    logger.error('Tool failed', nested ? { error } : error)
    expect(reported).toHaveBeenCalledOnce()
    expect(reported.mock.calls[0]![0].detail).toEqual({ operation: 'tool', error })
  },
)
