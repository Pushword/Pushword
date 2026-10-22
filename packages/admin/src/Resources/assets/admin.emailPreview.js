export function initEmailPreview(root = document) {
  root.querySelectorAll('[data-pw-email-preview]').forEach((panel) => {
    if (panel.dataset.initialized) return
    panel.dataset.initialized = 'true'

    const form = panel.querySelector('form')
    const frame = panel.querySelector('iframe')
    const status = panel.querySelector('[data-preview-status]')
    const retry = panel.querySelector('[data-preview-retry]')
    const saveState = panel.querySelector('[data-save-state]')
    const savedValues = panel.dataset.savedValues ? JSON.stringify(JSON.parse(panel.dataset.savedValues)) : designValues()
    let timer
    let request
    let revision = 0
    let dirty = false
    let submitting = false

    function designValues() {
      const data = new FormData(form)
      return JSON.stringify(['emailLayout', 'headerMarkdown', 'footerMarkdown'].map((name) => data.get(`email_design[${name}]`)))
    }

    function updateLayout() {
      const custom = form.querySelector('[name="email_design[emailLayout]"]:checked')?.value.startsWith('twig:') ?? false
      panel.querySelector('[data-email-editable]').hidden = custom
      panel.querySelector('[data-email-template-help]').hidden = !custom
      dirty = designValues() !== savedValues
      saveState.dataset.dirty = String(dirty)
      saveState.textContent = dirty ? saveState.dataset.unsaved : saveState.dataset.saved
    }

    updateLayout()

    async function update(version) {
      request = new AbortController()
      const data = new FormData(form)
      data.set('preview', '1')
      status.textContent = status.dataset.updating
      status.dataset.state = 'updating'
      retry.hidden = true
      frame.setAttribute('aria-busy', 'true')

      try {
        const response = await fetch(form.action, { method: 'POST', body: data, signal: request.signal })
        if (!response.ok) throw new Error('Preview failed')
        const result = await response.json()
        if (version !== revision) return
        frame.srcdoc = result.html
        status.textContent = status.dataset.ready
        status.dataset.state = 'ready'
      } catch (error) {
        if (version !== revision || error.name === 'AbortError') return
        status.textContent = status.dataset.failed
        status.dataset.state = 'error'
        retry.hidden = false
      } finally {
        if (version === revision) frame.setAttribute('aria-busy', 'false')
      }
    }

    function schedule() {
      updateLayout()
      clearTimeout(timer)
      request?.abort()
      status.textContent = status.dataset.updating
      status.dataset.state = 'updating'
      const version = ++revision
      timer = setTimeout(() => update(version), 250)
    }

    form.addEventListener('input', schedule)
    form.addEventListener('change', schedule)
    form.addEventListener('submit', () => { submitting = true })
    window.addEventListener('beforeunload', (event) => {
      if (form.isConnected && dirty && !submitting) {
        event.preventDefault()
        event.returnValue = ''
      }
    })
    retry.addEventListener('click', () => {
      clearTimeout(timer)
      request?.abort()
      update(++revision)
    })
    panel.querySelector('[data-design-defaults]').addEventListener('click', (event) => {
      const button = event.currentTarget
      form.elements.namedItem('email_design[headerMarkdown]').value = button.dataset.header
      form.elements.namedItem('email_design[footerMarkdown]').value = button.dataset.footer
      schedule()
    })
    panel.querySelectorAll('[data-preview-width]').forEach((button) => {
      button.addEventListener('click', () => {
        panel.dataset.viewport = button.dataset.previewWidth
        panel.querySelectorAll('[data-preview-width]').forEach((option) => {
          option.setAttribute('aria-pressed', String(option === button))
        })
      })
    })
    panel.querySelectorAll('[data-design-panel]').forEach((button) => {
      button.addEventListener('click', () => {
        panel.dataset.mobilePanel = button.dataset.designPanel
        panel.querySelectorAll('[data-design-panel]').forEach((option) => {
          option.setAttribute('aria-pressed', String(option === button))
        })
      })
    })
  })
}
