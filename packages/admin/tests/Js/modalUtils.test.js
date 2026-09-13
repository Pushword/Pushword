// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { openModal } from '../../src/Resources/assets/admin.modalUtils.js'

const config = { id: 'test-modal', iframeClass: 'test-frame' }

beforeEach(() => {
  document.body.innerHTML = ''
  vi.stubGlobal('open', vi.fn())
})

describe('openModal', () => {
  it('rejects executable and cross-origin URLs before loading an iframe', () => {
    expect(openModal(config, 'javascript:alert(1)')).toBe(false)
    expect(openModal(config, 'https://other.example/admin')).toBe(false)
    expect(document.querySelector('iframe')).toBeNull()
    expect(window.open).not.toHaveBeenCalled()
  })

  it('rejects malformed URLs', () => {
    expect(openModal(config, 'http://%')).toBe(false)
    expect(document.querySelector('iframe')).toBeNull()
  })

  it('loads an admin path on the current origin', () => {
    openModal(config, '/admin/page/1')

    expect(document.querySelector('iframe').src).toBe(`${window.location.origin}/admin/page/1`)
  })
})
