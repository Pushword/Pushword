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
        triggerAutosave()
      }
    },
    true,
  )

  form.addEventListener('htmx:beforeRequest', () => {
    updateIndicator('saving', 'Saving...')
  })

  form.addEventListener('htmx:afterRequest', (event) => {
    resetState()
    const status = event?.detail?.xhr?.status ?? 0
    if (status >= 200 && status < 400) {
      handleSuccess()
    } else {
      handleError()
    }
  })

  form.addEventListener('htmx:sendError', () => {
    resetState()
    handleError()
  })

  form.addEventListener('htmx:responseError', () => {
    resetState()
    handleError()
  })

  updateIndicator('idle', '')
}
