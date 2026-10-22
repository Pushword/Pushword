export function initEmailPreview(root = document) {
  root.querySelectorAll('[data-pw-email-preview]').forEach((panel) => {
    if (panel.dataset.initialized) return
    panel.dataset.initialized = 'true'

    const form = panel.querySelector('form')
    const frame = panel.querySelector('iframe')
    const status = panel.querySelector('[role="status"]')
    let timer
    let request
    let revision = 0

    async function update(version) {
      request = new AbortController()
      const data = new FormData(form)
      data.set('preview', '1')
      status.textContent = status.dataset.updating
      frame.setAttribute('aria-busy', 'true')

      try {
        const response = await fetch(form.action, { method: 'POST', body: data, signal: request.signal })
        if (!response.ok) throw new Error('Preview failed')
        const result = await response.json()
        if (version !== revision) return
        frame.srcdoc = result.html
        status.textContent = status.dataset.ready
      } catch (error) {
        if (version !== revision || error.name === 'AbortError') return
        status.textContent = status.dataset.failed
      } finally {
        if (version === revision) frame.setAttribute('aria-busy', 'false')
      }
    }

    function schedule() {
      clearTimeout(timer)
      request?.abort()
      const version = ++revision
      timer = setTimeout(() => update(version), 250)
    }

    form.addEventListener('input', schedule)
    form.addEventListener('change', schedule)
    panel.querySelectorAll('[data-preview-width]').forEach((button) => {
      button.addEventListener('click', () => {
        panel.dataset.viewport = button.dataset.previewWidth
        panel.querySelectorAll('[data-preview-width]').forEach((option) => {
          option.setAttribute('aria-pressed', String(option === button))
        })
      })
    })
  })
}
