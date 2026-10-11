// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { closeModal, openModal } from '../../src/Resources/assets/admin.modalUtils.js'

const config = { id: 'test-modal', iframeClass: 'test-frame' }

beforeEach(() => {
  document.body.innerHTML = ''
  vi.unstubAllGlobals()
  vi.stubGlobal('open', vi.fn())
})

function stubBootstrapModal() {
  let modal
  let opening = false
  let shown = false
  const instance = {
    show: vi.fn(() => {
      if (shown) return
      shown = true
      opening = true
      modal.dispatchEvent(new Event('show.bs.modal'))
      modal.style.display = 'block'
      modal.classList.add('show')
    }),
    hide: vi.fn(() => {
      if (!shown || opening) return
      shown = false
      modal.classList.remove('show')
      modal.style.display = 'none'
      modal.dispatchEvent(new Event('hidden.bs.modal'))
    }),
  }
  vi.stubGlobal('bootstrap', {
    Modal: {
      getOrCreateInstance: (element) => {
        modal = element
        return instance
      },
      getInstance: () => instance,
    },
  })
  return {
    instance,
    finishOpening() {
      opening = false
      modal.dispatchEvent(new Event('shown.bs.modal'))
    },
  }
}

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

describe('closeModal', () => {
  it('closes after the opening animation and does not close a later reopening', () => {
    const bootstrap = stubBootstrapModal()
    openModal(config, '/admin/media')
    const modal = document.getElementById(config.id)

    closeModal(config.id)
    expect(modal.classList.contains('show')).toBe(true)

    bootstrap.finishOpening()
    expect(modal.style.display).toBe('none')
    expect(bootstrap.instance.hide).toHaveBeenCalledTimes(1)

    openModal(config, '/admin/media')
    bootstrap.finishOpening()
    expect(modal.classList.contains('show')).toBe(true)
    expect(bootstrap.instance.hide).toHaveBeenCalledTimes(1)
    closeModal(config.id)
  })

  it('closes an already shown modal immediately', () => {
    const bootstrap = stubBootstrapModal()
    openModal(config, '/admin/media')
    bootstrap.finishOpening()

    closeModal(config.id)

    expect(document.getElementById(config.id).style.display).toBe('none')
    expect(bootstrap.instance.hide).toHaveBeenCalledTimes(1)
  })
})
