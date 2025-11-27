const INLINE_MODAL_ID = 'pw-admin-popup-modal'
const INLINE_IFRAME_BODY_CLASS = 'pw-admin-popup-modal'
const INLINE_IFRAME_CLASS = 'pw-admin-popup-iframe'
const INLINE_MESSAGE_TYPE = 'pw-inline-close'

let inlineModalListenerRegistered = false

export function inlinePopup() {
  if (!inlineModalListenerRegistered) {
    window.addEventListener('message', handleInlineMessage, false)
    inlineModalListenerRegistered = true
  }

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-pw-inline-edit-url]')
    if (!trigger) return

    event.preventDefault()
    const url = trigger.getAttribute('data-pw-inline-edit-url')
    if (!url) return

    openInlineModal(url)
  })

  initInlineChildContext()
}

function ensureInlineModal() {
  let modal = document.getElementById(INLINE_MODAL_ID)
  if (!modal) {
    modal = document.createElement('div')
    modal.id = INLINE_MODAL_ID
    modal.className = 'modal fade pw-inline-edit__modal'
    modal.tabIndex = -1
    modal.innerHTML = `
      <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
          <div class="modal-body p-0">
            <iframe class="${INLINE_IFRAME_CLASS}" title="Inline editor" loading="lazy"></iframe>
          </div>
        </div>
      </div>
    `

    document.body.appendChild(modal)
  }

  const iframe = modal.querySelector(`.${INLINE_IFRAME_CLASS}`)
  registerIframeBodyClassHandler(iframe)
  return { modal, iframe }
}

function openInlineModal(url) {
  const { modal, iframe } = ensureInlineModal()
  if (!iframe) {
    window.open(url, '_blank', 'noopener')
    return
  }

  iframe.src = normalizeInlineUrl(url)
  const modalInstance = window.bootstrap
    ? window.bootstrap.Modal.getOrCreateInstance(modal, { backdrop: 'static' })
    : null

  if (modalInstance) {
    modalInstance.show()
    modal.addEventListener(
      'hidden.bs.modal',
      () => {
        iframe.src = ''
      },
      { once: true },
    )
  } else {
    window.open(url, '_blank', 'noopener')
  }
}

function handleInlineMessage(event) {
  if (event.origin !== window.location.origin) return
  const payload = event.data
  if (!payload || payload.type !== INLINE_MESSAGE_TYPE) {
    return
  }

  closeInlineModal(Boolean(payload.refresh))
}

function closeInlineModal(shouldRefresh) {
  const modal = document.getElementById(INLINE_MODAL_ID)
  if (modal && window.bootstrap) {
    window.bootstrap.Modal.getOrCreateInstance(modal)?.hide()
  }

  if (shouldRefresh) {
    window.location.reload()
  }
}

function registerIframeBodyClassHandler(iframe) {
  if (!iframe || iframe.dataset.inlineBodyClassHandler === 'true') return

  iframe.addEventListener('load', () => setIframeBodyClass(iframe))
  iframe.dataset.inlineBodyClassHandler = 'true'
  setIframeBodyClass(iframe)
}

function setIframeBodyClass(iframe) {
  try {
    const iframeBody = iframe.contentDocument?.body
    iframeBody?.classList.add(INLINE_IFRAME_BODY_CLASS)
  } catch {
    // Ignore cross-origin access errors
  }
}

function normalizeInlineUrl(url) {
  try {
    const inlineUrl = new URL(url, window.location.origin)
    if (!inlineUrl.searchParams.has('pwInline')) {
      inlineUrl.searchParams.set('pwInline', '1')
    }

    return inlineUrl.toString()
  } catch {
    return url
  }
}

function initInlineChildContext() {
  if (!new URLSearchParams(window.location.search).has('pwInline')) {
    return
  }

  document.body.classList.add(INLINE_IFRAME_BODY_CLASS)
  bindInlineSaveHandlers()
}

function bindInlineSaveHandlers() {
  const notifyParentRefresh = () => {
    window.parent.postMessage(
      {
        type: INLINE_MESSAGE_TYPE,
        refresh: true,
      },
      window.location.origin,
    )
  }

  const params = new URLSearchParams(window.location.search)
  if (params.has('pwInlineSaved')) {
    notifyParentRefresh()
    return
  }

  const saveButtonSelectors = [
    '.action-save',
    '.action-saveAndReturn',
    '.action-saveAndContinue',
    '[data-action-name="saveAndContinue"]',
    '[data-action-name="saveAndReturn"]',
    '[data-action-name="save"]',
  ].join(', ')

  document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
      return
    }

    const saveButton = event.target.closest(saveButtonSelectors)
    if (saveButton) {
      setTimeout(() => {
        notifyParentRefresh()
      }, 0)
    }
  })

  document.addEventListener(
    'submit',
    (event) => {
      if (!(event.target instanceof HTMLFormElement)) {
        return
      }

      const method = event.target.getAttribute('method')
      if (!method || method.toLowerCase() !== 'post') {
        return
      }

      setTimeout(() => {
        notifyParentRefresh()
      }, 0)
    },
    true,
  )
}
