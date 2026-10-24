export function initEmailPreview(root = document) {
  root.querySelectorAll('[data-pw-email-preview]').forEach((panel) => {
    if (panel.dataset.initialized) return
    panel.dataset.initialized = 'true'

    const header = document.querySelector('body:is(.ea-edit, .ea-new) .content-header')
    if (header && panel.querySelector('[data-design-panel]')) {
      const updateHeaderOffset = () => {
        panel.style.setProperty('--pw-newsletter-header-offset', `calc(${header.offsetHeight}px + var(--ea-sp-8))`)
      }
      new ResizeObserver(updateHeaderOffset).observe(header)
      updateHeaderOffset()
    }

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
    const kind = panel.dataset.previewKind || 'audience'
    const form = panel.querySelector('form') || panel.closest('form')
    if (!form) return
    const disclosure = kind === 'automation' ? panel.closest('details') : null
    let previewFields
    if (kind === 'campaign') previewFields = ['audience', 'emailLayout', 'subject', 'preheader', 'bodyMarkdown', 'transactional'].map((name) => `Campaign[${name}]`)
    if (kind === 'automation') {
      const step = `Automation[steps][${panel.dataset.previewStep}]`
      previewFields = ['Automation[audience]', 'Automation[transactional]', `${step}[subject]`, `${step}[bodyMarkdown]`]
    }
    const frame = panel.querySelector('iframe')
    const status = panel.querySelector('[data-preview-status]')
    const retry = panel.querySelector('[data-preview-retry]')
    const saveState = panel.querySelector('[data-save-state]')
    const canvas = panel.querySelector('.pw-newsletter-design-canvas')
    const savedValues = panel.dataset.savedValues ? JSON.stringify(JSON.parse(panel.dataset.savedValues)) : designValues()
    let timer
    let request
    let revision = 0
    let dirty = false
    let submitting = false

    function designValues() {
      const data = new FormData(form)
      const prefix = kind === 'template' ? 'EmailTemplate' : 'email_design'
      const fields = kind === 'template' ? ['name', 'wrapped', 'headerMarkdown', 'footerMarkdown'] : ['emailLayout', 'headerMarkdown', 'footerMarkdown', 'signatureMarkdown']
      return JSON.stringify(fields.map((name) => data.get(`${prefix}[${name}]`)))
    }

    function updateLayout() {
      if (kind === 'audience') {
        const layout = form.querySelector('[name="email_design[emailLayout]"]:checked')?.value || ''
        const custom = layout.startsWith('twig:') || layout.startsWith('saved:')
        panel.querySelector('[data-email-editable]').hidden = custom || layout === 'personal'
        panel.querySelector('[data-email-personal]').hidden = layout !== 'personal'
        panel.querySelector('[data-email-template-help]').hidden = !custom
      }
      dirty = !['campaign', 'automation'].includes(kind) && designValues() !== savedValues
      if (saveState) {
        saveState.dataset.dirty = String(dirty)
        saveState.textContent = dirty ? saveState.dataset.unsaved : saveState.dataset.saved
      }
    }

    updateLayout()

    async function update(version) {
      request = new AbortController()
      const data = new FormData(form)
      data.set('preview', '1')
      if (kind !== 'audience') data.set('_preview_token', panel.dataset.previewToken)
      if (kind === 'automation') data.set('_preview_step', panel.dataset.previewStep)
      status.textContent = status.dataset.updating
      status.dataset.state = 'updating'
      retry.hidden = true
      frame.setAttribute('aria-busy', 'true')

      try {
        const response = await fetch(panel.dataset.previewUrl || form.action, { method: 'POST', body: data, signal: request.signal })
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

    function schedule(event) {
      if (!panel.isConnected) return
      if (event && previewFields && !previewFields.includes(event.target.name)) return
      updateLayout()
      clearTimeout(timer)
      request?.abort()
      const version = ++revision
      const audienceField = kind === 'automation' ? 'Automation[audience]' : 'Campaign[audience]'
      const empty = ['campaign', 'automation'].includes(kind) && !form.elements.namedItem(audienceField).value
      if (canvas) canvas.hidden = empty
      if (empty) {
        status.textContent = status.dataset.empty
        status.dataset.state = 'empty'
        retry.hidden = true
        frame.setAttribute('aria-busy', 'false')
        return
      }
      status.textContent = status.dataset.updating
      status.dataset.state = 'updating'
      if (disclosure && !disclosure.open) return
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
    panel.querySelector('[data-design-defaults]')?.addEventListener('click', (event) => {
      const button = event.currentTarget
      form.elements.namedItem('email_design[headerMarkdown]').value = button.dataset.header
      form.elements.namedItem('email_design[footerMarkdown]').value = button.dataset.footer
      schedule()
    })
    disclosure?.addEventListener('toggle', () => schedule())
    if (kind !== 'audience') schedule()
  })
}
