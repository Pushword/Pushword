import './contextHelp.css'

let nextId = 0

function positionHelp(panel, button) {
  const anchor = button.getBoundingClientRect()
  const bounds = panel.getBoundingClientRect()
  const gap = 8
  const edge = 16
  panel.style.left = `${Math.max(edge, Math.min(anchor.left, window.innerWidth - bounds.width - edge))}px`
  panel.style.top = `${Math.max(edge, Math.min(anchor.bottom + gap, window.innerHeight - bounds.height - edge))}px`
}

/** Enhance only explicitly classified reference help, preserving the original nodes. */
function initContextHelp(root = document) {
  const rows = [...root.querySelectorAll('[data-pw-help]')]
  if (root.matches?.('[data-pw-help]')) rows.unshift(root)

  for (const row of rows) {
    if (row.dataset.pwHelpInitialized) continue
    const content = row.querySelector(
      '[data-pw-help-content], :scope > .form-widget > .form-help, :scope > .form-text, .form-fieldset-help',
    )
    if (!content || !content.textContent.trim()) continue

    const label = row.querySelector(
      ':scope > label, :scope > legend, .form-check > label, .form-fieldset-title-content',
    )
    const button = document.createElement('button')
    button.type = 'button'
    button.className = 'pw-help-button'
    button.setAttribute('aria-label', row.dataset.pwHelp)
    button.setAttribute('aria-expanded', 'false')
    button.innerHTML =
      '<svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7.25"/><path d="M8 7.4a2.1 2.1 0 0 1 4 .9c0 1.4-2 1.7-2 3"/><path d="M10 14h.01"/></svg>'

    const panel = document.createElement('div')
    panel.id = row.dataset.pwHelpId || `pw-reference-help-${++nextId}`
    panel.className = 'pw-help-panel'
    panel.setAttribute('popover', 'auto')
    button.setAttribute('popovertarget', panel.id)
    button.setAttribute('aria-controls', panel.id)

    const counter = content.querySelector('#titleWidth, #descWidth')
    const summary = row.dataset.pwHelpSummary
    if (summary || counter) {
      const inline = document.createElement('small')
      inline.className = 'form-text pw-help-summary'
      if (summary) inline.append(summary)
      if (counter) inline.append(' ', counter)
      content.after(inline)
    }

    if (label?.matches('label') && label.parentElement === row) {
      const group = document.createElement('div')
      group.className = 'pw-help-label'
      label.before(group)
      group.append(label, button, panel)
    } else if (label?.matches('legend')) {
      label.classList.add('pw-help-label')
      label.append(button)
      label.after(panel)
    } else if (label?.matches('.form-fieldset-title-content')) {
      const group = document.createElement('div')
      group.className = 'pw-help-label pw-help-heading'
      label.before(group)
      group.append(label, button, panel)
    } else if (label) label.after(button, panel)
    else content.before(button, panel)
    panel.append(content)
    row.dataset.pwHelpInitialized = 'true'

    panel.addEventListener('beforetoggle', (event) => {
      button.setAttribute('aria-expanded', String(event.newState === 'open'))
      if (event.newState === 'open') positionHelp(panel, button)
    })
    panel.addEventListener('toggle', () => {
      if (panel.matches(':popover-open')) positionHelp(panel, button)
    })
  }
}

window.initContextHelp = initContextHelp
document.addEventListener('DOMContentLoaded', () => initContextHelp())
document.addEventListener('alpine:initialized', () => initContextHelp())
document.addEventListener('ea.collection.item-added', (event) =>
  initContextHelp(event.detail.newElement),
)
document.addEventListener('htmx:after:swap', () => initContextHelp())
function repositionOpenHelp() {
  document.querySelectorAll('.pw-help-panel:popover-open').forEach((panel) => {
    positionHelp(panel, document.querySelector(`[popovertarget="${panel.id}"]`))
  })
}

window.addEventListener('resize', repositionOpenHelp)
window.addEventListener('scroll', repositionOpenHelp, true)
