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
