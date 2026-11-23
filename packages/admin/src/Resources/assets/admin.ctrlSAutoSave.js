/**
 * Gestion de la sauvegarde automatique avec Ctrl+S
 */

/**
 * Initialise la sauvegarde automatique avec Ctrl+S
 * Utilise HTMX pour envoyer le formulaire
 */
export function initCtrlSAutoSave() {
  const form = document.querySelector('form[data-pw-ctrl-s-form="1"]')
  if (!form || !window.htmx) return

  const ctrlEvent = form.getAttribute('data-pw-ctrl-s-event') || 'pw-ctrl-s-event'
  const indicatorSelector = form.getAttribute('data-pw-ctrl-s-indicator') || ''
  const buttonSelector = form.getAttribute('data-pw-ctrl-s-button') || ''
  const indicator = indicatorSelector ? document.querySelector(indicatorSelector) : null
  const saveButton = buttonSelector ? document.querySelector(buttonSelector) : null
  const indicatorDefaultText = indicator ? indicator.textContent.trim() : ''
  const shortcut = form.querySelector('[data-pw-ctrl-s-submit]')
  const revision = form.querySelector('[data-pw-page-revision]')

  if (indicator) {
    indicator.dataset.pwCtrlSDefault =
      indicator.dataset.pwCtrlSDefault || indicatorDefaultText
  }

  let state = {
    isSaving: false,
    successTimeout: null,
  }

  const updateIndicator = (newState, message = '') => {
    if (saveButton) {
      saveButton.dataset.pwCtrlSState = newState
    }

    if (!indicator) return
    const defaultLabel = indicator.dataset.pwCtrlSDefault || indicatorDefaultText
    indicator.dataset.state = newState
    indicator.textContent = message || defaultLabel
    console.debug(`[AutoSave] State: ${newState}`, message ? `- ${message}` : '')
  }

  const handleSuccess = () => {
    updateIndicator('success', 'Saved')
    state.successTimeout = window.setTimeout(() => {
      updateIndicator('idle', '')
    }, 200)
  }

  const handleError = () => {
    updateIndicator('error', 'Save failed')
    state.successTimeout = window.setTimeout(() => updateIndicator('idle', ''), 4000)
  }

  const resetState = () => {
    state.isSaving = false
    if (state.successTimeout) {
      window.clearTimeout(state.successTimeout)
      state.successTimeout = null
    }
  }

  const triggerAutosave = () => {
    if (state.isSaving) {
      console.debug('[AutoSave] Already saving, skipping')
      return
    }
    state.isSaving = true
    updateIndicator('saving', 'Saving...')
    form.dispatchEvent(new Event(ctrlEvent, { bubbles: true }))
  }

  if (shortcut) {
    form.addEventListener('submit', (event) => {
      if (event.submitter !== shortcut || event.defaultPrevented) return
      event.preventDefault()
      triggerAutosave()
    })
  }

  document.addEventListener(
    'keydown',
    (event) => {
      if (
        (event.ctrlKey || event.metaKey) &&
        event.key &&
        event.key.toLowerCase() === 's'
      ) {
        if (!form.isConnected) return
        event.preventDefault()
        // Let the block editor finish writing the current Markdown first.
        if (shortcut) form.requestSubmit(shortcut)
        else triggerAutosave()
      }
    },
    true,
  )

  form.addEventListener('htmx:before:request', () => {
    updateIndicator('saving', 'Saving...')
  })

  form.addEventListener('htmx:after:request', (event) => {
    resetState()
    const response = event.detail?.ctx?.response
    const status = response?.status ?? 0
    const saved = revision
      ? status === 204 && response.headers?.has('X-Pushword-Page-Revision')
      : status >= 200 && status < 400
    if (saved) {
      handleSuccess()
    } else {
      handleError()
    }
  })

  // htmx 4 has no htmx:send:error — network failures fire htmx:error
  form.addEventListener('htmx:error', () => {
    resetState()
    handleError()
  })

  form.addEventListener('htmx:response:error', () => {
    resetState()
    handleError()
  })

  updateIndicator('idle', '')
}
