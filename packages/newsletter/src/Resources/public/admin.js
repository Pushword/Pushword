export function initCampaignDesigns(root = document) {
  root.querySelectorAll('[data-pw-campaign-design]').forEach((select) => {
    if (select.dataset.designInitialized) return
    select.dataset.designInitialized = 'true'
    const designs = JSON.parse(select.dataset.pwCampaignDesign)
    const audience = select.form.elements.namedItem('Campaign[audience]')
    const summary = select.form.querySelector('[data-campaign-design-summary]')
    const current = summary.querySelector('[data-campaign-design-current]')
    const description = summary.querySelector('[data-campaign-design-description]')
    const edit = summary.querySelector('[data-campaign-design-edit]')
    const warning = summary.querySelector('[data-campaign-design-warning]')
    const customization = summary.querySelector('[data-campaign-design-edit-details]')
    const customizationLabel = customization?.querySelector('summary')
    const preheader = select.form.elements.namedItem('Campaign[preheader]')?.closest('.form-group')

    function update() {
      const design = designs[audience.value]
      const layout = select.value || design?.layout
      const personal = layout === 'personal'
      if (preheader) preheader.hidden = personal
      if (customizationLabel) customizationLabel.textContent = personal ? summary.dataset.personalCustomize : summary.dataset.customize
      description.hidden = !design
      edit.hidden = true
      warning.hidden = true
      if (customization) customization.hidden = true
      if (!design) {
        current.textContent = summary.dataset.empty
        return
      }
      const label = Array.from(select.options).find((option) => option.value === layout)?.textContent || layout
      current.textContent = summary.dataset.current.replace('%theme%', label)
      const custom = layout.startsWith('twig:') || layout.startsWith('saved:')
      if (personal) description.textContent = summary.dataset.personal.replace('%audience%', design.name)
      else if (custom) description.textContent = summary.dataset.custom
      else description.textContent = summary.dataset.shared.replace('%audience%', design.name)
      if (layout.startsWith('saved:')) {
        edit.href = summary.dataset.templateEditUrl.replace('__template__', layout.slice(6))
        edit.textContent = summary.dataset.editTemplate
        edit.hidden = false
        warning.textContent = summary.dataset.templateWarning
        warning.hidden = false
      } else if (!select.value || !custom) {
        edit.href = design.url + (personal ? '#email_design_signatureMarkdown' : '')
        edit.textContent = personal ? summary.dataset.personalEdit : summary.dataset.editAudience
        warning.textContent = personal ? summary.dataset.personalWarning : summary.dataset.audienceWarning
        edit.hidden = false
        warning.hidden = false
      }
      if (customization) customization.hidden = edit.hidden
    }
    select.addEventListener('change', update)
    audience.addEventListener('change', update)
    update()

    const templates = select.form.querySelector('[data-campaign-templates]')
    if (templates) window.addEventListener('focus', async () => {
      if (!select.isConnected) return
      try {
        const response = await fetch(templates.dataset.choicesUrl)
        if (!response.ok) return
        const { groups, audienceLayouts } = await response.json()
        for (const [id, layout] of Object.entries(audienceLayouts)) {
          if (designs[id]) designs[id].layout = layout
        }
        const selected = select.value
        select.querySelectorAll('optgroup').forEach((group) => group.remove())
        for (const [label, choices] of Object.entries(groups)) {
          const group = document.createElement('optgroup')
          group.label = label
          for (const [name, value] of Object.entries(choices)) group.append(new Option(name, value))
          select.append(group)
        }
        select.value = selected
        select.dispatchEvent(new Event('change', { bubbles: true }))
      } catch {
        // Keep the current choices if a background refresh cannot complete.
      }
    })
  })
}

export function initNewsletterIndexes(root = document) {
  root.querySelectorAll('[data-pw-newsletter-index]').forEach((panel) => {
    if (panel.dataset.initialized) return
    panel.dataset.initialized = 'true'
    panel.querySelectorAll('.form-batch-checkbox-all, .form-batch-checkbox').forEach((input) => {
      const all = input.classList.contains('form-batch-checkbox-all')
      const name = all ? panel.dataset.selectAll : panel.dataset.selectRow.replace('%id%', input.value)
      input.setAttribute('aria-label', name)
      if (all) input.closest('th').setAttribute('aria-label', name)
      const label = document.createElement('label')
      label.htmlFor = input.id
      label.className = 'pw-newsletter-selection'
      input.before(label)
      label.append(input)
      if (all) {
        const text = document.createElement('span')
        text.className = 'pw-newsletter-selection-text'
        text.textContent = name
        label.append(text)
      }
    })
    panel.querySelectorAll('tbody .dropdown-toggle[role="button"]').forEach((button) => {
      button.setAttribute('aria-label', panel.dataset.rowActions.replace('%id%', button.closest('tr').dataset.id))
    })
  })
}

export function numberNewNewsletterStep(element) {
  const input = element.querySelector('[data-pw-sequence-number]')
  if (!input) return
  const numbers = Array.from(input.form.querySelectorAll('[data-pw-sequence-number]'))
    .filter((other) => other !== input).map((other) => Number(other.value))
  input.value = String(Math.max(0, ...numbers) + 1)
  const title = element.querySelector('.accordion-button')
  Array.from(title.childNodes).filter((node) => node.nodeType === Node.TEXT_NODE).forEach((node) => node.remove())
  title.append(` ${input.dataset.emailLabel} ${input.value}`)
}

export function initNewsletterDelays(root = document) {
  root.querySelectorAll('[data-pw-delay-preview]').forEach((input) => {
    if (input.dataset.delayInitialized) return
    input.dataset.delayInitialized = 'true'
    const output = root.querySelector(`[data-delay-output][for="${input.id}"]`)

    function update() {
      const minutes = input.valueAsNumber
      if (!Number.isFinite(minutes) || minutes < 0) {
        output.textContent = ''
        return
      }
      if (minutes === 0) {
        output.textContent = output.dataset.immediate
        return
      }
      output.textContent = [
        [Math.floor(minutes / 1440), output.dataset.days],
        [Math.floor((minutes % 1440) / 60), output.dataset.hours],
        [minutes % 60, output.dataset.minutes],
      ].filter(([value]) => value > 0).map(([value, unit]) => `${value} ${unit}`).join(' ')
    }
    input.addEventListener('input', update)
    update()
  })
}

export function initAutomationRecipients(root = document) {
  root.querySelectorAll('[data-pw-automation-recipients]').forEach((panel) => {
    if (panel.dataset.initialized) return
    panel.dataset.initialized = 'true'
    const form = panel.closest('form')
    const source = form.elements.namedItem('Automation[source]')
    const stop = form.elements.namedItem('Automation[stopWhenAsJson]')?.closest('.form-group')
    const fields = panel.querySelector('.form-fieldset-body')
    const note = document.createElement('p')
    note.className = 'pw-newsletter-recipient-note'
    note.textContent = panel.dataset.pwAutomationRecipients
    fields.before(note)
    function update() {
      fields.hidden = source.value === 'contact'
      note.hidden = !fields.hidden
      if (stop) stop.hidden = source.value === 'page'
    }
    source.addEventListener('change', update)
    update()
  })
}

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
    if (kind === 'campaign') previewFields = ['audience', 'emailLayout', 'senderProfile', 'subject', 'preheader', 'bodyMarkdown', 'transactional'].map((name) => `Campaign[${name}]`)
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

    function updateLayout(layoutChanged = false) {
      if (kind === 'audience') {
        const layout = form.querySelector('[name="email_design[emailLayout]"]:checked')?.value || ''
        const custom = layout.startsWith('twig:') || layout.startsWith('saved:')
        panel.querySelector('[data-email-editable]').hidden = custom || layout === 'personal'
        if (layoutChanged) {
          const signature = panel.querySelector('[data-email-personal]')
          signature.open = layout === 'personal' || location.hash === '#email_design_signatureMarkdown'
        }
        panel.querySelector('[data-email-template-help]').hidden = !custom
      }
      dirty = !['campaign', 'automation'].includes(kind) && designValues() !== savedValues
      if (saveState) {
        saveState.dataset.dirty = String(dirty)
        saveState.textContent = dirty ? saveState.dataset.unsaved : saveState.dataset.saved
      }
    }

    updateLayout(true)

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
        const sender = panel.querySelector('[data-preview-sender]')
        if (sender) sender.textContent = result.sender || ''
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
      updateLayout(event?.target.name === 'email_design[emailLayout]')
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

document.addEventListener('ea.collection.item-added', (event) => {
  numberNewNewsletterStep(event.detail.newElement)
  initNewsletterDelays(event.detail.newElement)
  initEmailPreview(event.detail.newElement)
})

window.addEventListener('load', () => {
  const navigation = document.querySelector('.pw-newsletter-nav')
  const active = navigation?.querySelector('[aria-current="page"]')
  if (active) navigation.scrollLeft = Math.max(0, active.offsetLeft - navigation.offsetLeft + active.offsetWidth - navigation.clientWidth)
  initEmailPreview()
  initNewsletterDelays()
  initNewsletterIndexes()
  initCampaignDesigns()
  initAutomationRecipients()
})
