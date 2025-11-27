import {
  openModal,
  closeModal,
  createMessageHandler,
  sendMessageToParent,
  normalizeUrl,
  registerIframeBodyClassHandler,
  ensureModal,
} from './admin.modalUtils.js'

const INLINE_MODAL_ID = 'pw-admin-popup-modal'
const INLINE_IFRAME_BODY_CLASS = 'pw-admin-popup-modal'
const INLINE_IFRAME_CLASS = 'pw-admin-popup-iframe'
const INLINE_MESSAGE_TYPE = 'pw-inline-close'

let inlineModalListenerRegistered = false

export function inlinePopup() {
  if (!inlineModalListenerRegistered) {
    window.addEventListener(
      'message',
      createMessageHandler(INLINE_MESSAGE_TYPE, handleInlinePayload),
      false,
    )
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
  const result = ensureModal({
    id: INLINE_MODAL_ID,
    iframeClass: INLINE_IFRAME_CLASS,
    title: 'Inline editor',
    hasHeader: false,
  })

  if (result.iframe) {
    registerIframeBodyClassHandler(result.iframe, INLINE_IFRAME_BODY_CLASS)
  }

  return result
}

function openInlineModal(url) {
  // Ensure modal is created
  ensureInlineModal()

  const normalizedUrl = normalizeUrl(url, { pwInline: '1' })

  openModal(
    {
      id: INLINE_MODAL_ID,
      iframeClass: INLINE_IFRAME_CLASS,
      title: 'Inline editor',
      hasHeader: false,
    },
    normalizedUrl,
  )
}

function handleInlinePayload(payload) {
  closeModal(INLINE_MODAL_ID, Boolean(payload.refresh))
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
    sendMessageToParent(INLINE_MESSAGE_TYPE, { refresh: true })
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
