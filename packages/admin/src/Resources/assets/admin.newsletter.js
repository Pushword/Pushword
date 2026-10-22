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

    function update() {
      const design = designs[audience.value]
      description.hidden = !design
      edit.hidden = true
      warning.hidden = true
      if (customization) customization.hidden = true
      if (!design) {
        current.textContent = summary.dataset.empty
        return
      }
      const layout = select.value || design.layout
      const label = Array.from(select.options).find((option) => option.value === layout)?.textContent || layout
      current.textContent = summary.dataset.current.replace('%theme%', label)
      const custom = layout.startsWith('twig:') || layout.startsWith('saved:')
      description.textContent = custom ? summary.dataset.custom : summary.dataset.shared.replace('%audience%', design.name)
      if (layout.startsWith('saved:')) {
        edit.href = summary.dataset.templateEditUrl.replace('__template__', layout.slice(6))
        edit.textContent = summary.dataset.editTemplate
        edit.hidden = false
        warning.textContent = summary.dataset.templateWarning
        warning.hidden = false
      } else if (!select.value || !custom) {
        edit.href = design.url
        edit.textContent = summary.dataset.editAudience
        warning.textContent = summary.dataset.audienceWarning
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
        const { groups } = await response.json()
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
