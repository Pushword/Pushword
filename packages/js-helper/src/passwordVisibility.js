let initialized = false

export function initPasswordVisibility() {
  if (initialized) return
  initialized = true

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-password-toggle]')
    if (!button) return

    const inputId = button.getAttribute('aria-controls')
    const input = inputId ? document.getElementById(inputId) : null
    if (!(input instanceof HTMLInputElement)) return

    const showPassword = input.type === 'password'
    input.type = showPassword ? 'text' : 'password'
    button.setAttribute('aria-pressed', String(showPassword))

    const hiddenIcon = button.querySelector('[data-password-hidden-icon]')
    const visibleIcon = button.querySelector('[data-password-visible-icon]')
    if (hiddenIcon) hiddenIcon.hidden = showPassword
    if (visibleIcon) visibleIcon.hidden = !showPassword
  })
}
