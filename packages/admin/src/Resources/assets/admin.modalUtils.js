/**
 * Shared modal utility module.
 * Provides common modal functionality for media picker and inline popup.
 * Includes WCAG 2.1 AA accessibility features: focus trap, ARIA roles.
 */

const debug = (...args) => console.debug('[ModalUtils]', ...args)

/** @type {HTMLElement|null} Element that had focus before modal opened */
let previouslyFocusedElement = null

/** @type {Function|null} Cleanup function for focus trap */
let focusTrapCleanup = null

/**
 * Creates a focus trap within a modal element (WCAG 2.4.3).
 * Traps Tab key within the modal to prevent focus from escaping.
 *
 * @param {HTMLElement} modal - The modal element
 * @returns {Function} Cleanup function to remove the focus trap
 */
function createFocusTrap(modal) {
  const focusableSelectors = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
    'iframe',
  ].join(', ')

  function handleKeydown(e) {
    if (e.key !== 'Tab') return

    const focusableElements = modal.querySelectorAll(focusableSelectors)
    if (focusableElements.length === 0) return

    const firstFocusable = focusableElements[0]
    const lastFocusable = focusableElements[focusableElements.length - 1]

    if (e.shiftKey) {
      if (document.activeElement === firstFocusable || document.activeElement === modal) {
        e.preventDefault()
        lastFocusable.focus()
      }
    } else {
      if (document.activeElement === lastFocusable) {
        e.preventDefault()
        firstFocusable.focus()
      }
    }
  }

  modal.addEventListener('keydown', handleKeydown)
  return () => modal.removeEventListener('keydown', handleKeydown)
}

/**
 * Sets initial focus to the first focusable element in the modal.
 *
 * @param {HTMLElement} modal - The modal element
 */
function setInitialFocus(modal) {
  const closeButton = modal.querySelector('.btn-close')
  const iframe = modal.querySelector('iframe')

  if (closeButton) {
    closeButton.focus()
  } else if (iframe) {
    iframe.focus()
  } else {
    modal.focus()
  }
}

/**
 * @typedef {Object} ModalConfig
 * @property {string} id - Modal element ID
 * @property {string} iframeClass - CSS class for the iframe
 * @property {string} [title] - Optional modal title
 * @property {boolean} [hasHeader] - Whether to show modal header (default: false)
 * @property {string} [modalClass] - Additional CSS class for the modal element
 */

/**
 * @typedef {Object} ModalElements
 * @property {HTMLElement} modal - The modal element
 * @property {HTMLIFrameElement|null} iframe - The iframe element
 */

/**
 * Creates or retrieves a modal element with an iframe.
 *
 * @param {ModalConfig} config - Modal configuration
 * @returns {ModalElements}
 */
export function ensureModal(config) {
  const { id, iframeClass, title = '', hasHeader = false, modalClass = '' } = config

  let modal = document.getElementById(id)
  if (!modal) {
    modal = document.createElement('div')
    modal.id = id
    modal.className = `modal fade pw-admin-modal ${modalClass}`.trim()
    modal.tabIndex = -1

    // WCAG 2.1 AA: Add ARIA attributes for modal accessibility
    modal.setAttribute('role', 'dialog')
    modal.setAttribute('aria-modal', 'true')

    const titleId = `${id}-title`
    if (title) {
      modal.setAttribute('aria-labelledby', titleId)
    } else {
      modal.setAttribute('aria-label', 'Modal dialog')
    }

    const headerHtml = hasHeader
      ? `
        <div class="modal-header">
          <h5 class="modal-title" id="${titleId}">${title}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      `
      : ''

    modal.innerHTML = `
      <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
          ${headerHtml}
          <div class="modal-body p-0">
            <iframe class="${iframeClass}" title="${title || 'Modal content'}" loading="lazy"></iframe>
          </div>
        </div>
      </div>
    `

    document.body.appendChild(modal)
    debug('Created modal', id)
  }

  const iframe = modal.querySelector(`.${iframeClass}`)

  return { modal, iframe }
}

/**
 * Opens a modal with the given URL.
 *
 * @param {ModalConfig} config - Modal configuration
 * @param {string} url - URL to load in the iframe
 * @param {Object} [options] - Additional options
 * @param {Function} [options.onHide] - Callback when modal is hidden
 * @returns {boolean} - True if modal was opened, false if fallback to window.open
 */
export function openModal(config, url, options = {}) {
  const { modal, iframe } = ensureModal(config)

  if (!iframe) {
    window.open(url, '_blank', 'noopener')
    return false
  }

  // WCAG 2.1 AA: Store currently focused element for restoration
  previouslyFocusedElement = document.activeElement

  iframe.src = url
  debug('Opening modal with URL', url)

  const modalInstance = window.bootstrap
    ? window.bootstrap.Modal.getOrCreateInstance(modal, { backdrop: 'static' })
    : null

  if (modalInstance) {
    modalInstance.show()

    // WCAG 2.1 AA: Set up focus trap when modal is shown
    modal.addEventListener(
      'shown.bs.modal',
      () => {
        focusTrapCleanup = createFocusTrap(modal)
        setInitialFocus(modal)
      },
      { once: true },
    )

    modal.addEventListener(
      'hidden.bs.modal',
      () => {
        iframe.src = ''

        // WCAG 2.1 AA: Clean up focus trap
        if (focusTrapCleanup) {
          focusTrapCleanup()
          focusTrapCleanup = null
        }

        // WCAG 2.1 AA: Restore focus to triggering element
        if (previouslyFocusedElement && previouslyFocusedElement.focus) {
          previouslyFocusedElement.focus()
          previouslyFocusedElement = null
        }

        if (typeof options.onHide === 'function') {
          options.onHide()
        }
      },
      { once: true },
    )

    return true
  }

  // Fallback if Bootstrap is not available
  window.open(url, '_blank', 'noopener')
  return false
}

/**
 * Closes a modal by its ID.
 *
 * @param {string} modalId - Modal element ID
 * @param {boolean} [shouldRefresh=false] - Whether to refresh the page after closing
 */
export function closeModal(modalId, shouldRefresh = false) {
  const modal = document.getElementById(modalId)
  if (modal && window.bootstrap) {
    window.bootstrap.Modal.getInstance(modal)?.hide()
  }

  if (shouldRefresh) {
    window.location.reload()
  }
}

/**
 * Sets a body class on the iframe's content document.
 *
 * @param {HTMLIFrameElement} iframe - The iframe element
 * @param {string} className - CSS class to add to iframe body
 */
export function setIframeBodyClass(iframe, className) {
  try {
    const iframeBody = iframe.contentDocument?.body
    iframeBody?.classList.add(className)
  } catch {
    // Ignore cross-origin access errors
  }
}

/**
 * Registers a handler to set body class on iframe load.
 *
 * @param {HTMLIFrameElement} iframe - The iframe element
 * @param {string} className - CSS class to add to iframe body
 */
export function registerIframeBodyClassHandler(iframe, className) {
  if (!iframe || iframe.dataset.bodyClassHandler === 'true') {
    return
  }

  iframe.addEventListener('load', () => setIframeBodyClass(iframe, className))
  iframe.dataset.bodyClassHandler = 'true'
  setIframeBodyClass(iframe, className)
}

/**
 * Creates a message handler for postMessage communication.
 *
 * @param {string} expectedType - Expected message type
 * @param {Function} handler - Handler function receiving (payload) => void
 * @returns {Function} - Event listener function
 */
export function createMessageHandler(expectedType, handler) {
  return (event) => {
    if (event.origin !== window.location.origin) {
      return
    }

    const payload = event.data
    if (!payload || payload.type !== expectedType) {
      return
    }

    debug('Received message', expectedType, payload)
    handler(payload)
  }
}

/**
 * Sends a postMessage to the parent window.
 *
 * @param {string} type - Message type
 * @param {Object} data - Additional data to send
 */
export function sendMessageToParent(type, data = {}) {
  debug('Sending message to parent', type, data)
  window.parent.postMessage(
    {
      type,
      ...data,
    },
    window.location.origin,
  )
}

/**
 * Normalizes a URL by adding query parameters.
 *
 * @param {string} url - Base URL
 * @param {Object<string, string>} params - Query parameters to add
 * @returns {string} - Normalized URL
 */
export function normalizeUrl(url, params = {}) {
  try {
    const urlObj = new URL(url, window.location.origin)

    for (const [key, value] of Object.entries(params)) {
      if (!urlObj.searchParams.has(key)) {
        urlObj.searchParams.set(key, value)
      }
    }

    return urlObj.toString()
  } catch {
    return url
  }
}


