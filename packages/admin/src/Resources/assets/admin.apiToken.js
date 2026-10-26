export function initApiTokenFields() {
  document.querySelectorAll('[data-api-token-widget]').forEach((widget) => {
    const input = widget.querySelector('input')
    const generate = widget.querySelector('[data-token-action="generate"]')
    const copy = widget.querySelector('[data-token-action="copy"]')
    const regenerate = widget.querySelector('[data-token-action="regenerate"]')
    const revoke = widget.querySelector('[data-token-action="revoke"]')
    const copyLabel = copy.querySelector('span')
    const originalCopyLabel = copyLabel.textContent

    function updateButtons() {
      const hasToken = input.value.trim() !== ''
      generate.hidden = hasToken
      copy.hidden = !hasToken
      regenerate.hidden = !hasToken
      revoke.hidden = !hasToken
    }

    function setToken(value) {
      input.value = value
      updateButtons()
      input.dispatchEvent(new Event('input', { bubbles: true }))
      input.dispatchEvent(new Event('change', { bubbles: true }))
    }

    function generateToken() {
      const bytes = crypto.getRandomValues(new Uint8Array(32))
      return [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('')
    }

    generate.addEventListener('click', () => {
      setToken(generateToken())
      copy.focus()
    })

    copy.addEventListener('click', async () => {
      await navigator.clipboard.writeText(input.value)
      copyLabel.textContent = widget.dataset.copiedLabel
      setTimeout(() => {
        copyLabel.textContent = originalCopyLabel
      }, 1500)
    })

    regenerate.addEventListener('click', () => {
      if (confirm(widget.dataset.regenerateConfirm)) setToken(generateToken())
    })

    revoke.addEventListener('click', () => {
      if (confirm(widget.dataset.revokeConfirm)) {
        setToken('')
        generate.focus()
      }
    })

    updateButtons()
  })
}
