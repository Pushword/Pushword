import { decodeBlock, encodeBlock } from './block-markup.js'

export class EmailBlock {
  static get toolbox() {
    return { title: 'Reusable content', icon: '<svg width="20" height="20" viewBox="0 0 20 20"><path d="M2 2h6v6H2zm10 0h6v6h-6zM2 12h6v6H2zm10 0h6v6h-6z" fill="none" stroke="currentColor"/></svg>' }
  }

  static get isReadOnlySupported() { return true }
  static get sanitize() { return { markdown: true, label: true, reference: true, provider: true, parameters: true } }
  static isItMarkdownExported(markdown) { return markdown.trim().startsWith('```newsletter-block\n') }
  static exportToMarkdown(data) { return data.reference ? encodeBlock(data) : data.markdown || '' }
  static importFromMarkdown(editor, markdown) { editor.blocks.insert('newsletterBlock', decodeBlock(markdown)) }

  constructor({ data, config, readOnly, block }) {
    this.data = data || {}
    this.config = config
    this.readOnly = readOnly
    this.block = block
  }

  render() {
    this.root = document.createElement('div')
    this.root.className = 'pw-newsletter-block'
    this.heading = document.createElement('strong')
    this.heading.textContent = this.data.label || this.config.labels.title
    this.root.append(this.heading)
    this.status = document.createElement('p')
    this.status.setAttribute('role', 'status')
    this.status.className = 'pw-newsletter-block-status'
    this.root.append(this.status)

    this.picker = document.createElement('div')
    this.picker.className = 'pw-newsletter-block-picker'
    this.provider = this.input('select', this.config.labels.source)
    this.query = this.input('input', this.config.labels.search)
    this.query.type = 'search'
    this.query.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') { event.preventDefault(); this.searchButton.click() }
    })
    this.searchButton = this.button(this.config.labels.search, () => this.search())
    this.picker.append(this.field(this.provider, this.config.labels.source), this.field(this.query, this.config.labels.search), this.searchButton)
    this.results = document.createElement('div')
    this.results.className = 'pw-newsletter-block-results'
    this.picker.append(this.results)
    this.root.append(this.picker)

    const label = document.createElement('label')
    this.contentLabel = label
    label.className = 'pw-newsletter-block-field'
    label.textContent = this.config.labels.content
    this.content = document.createElement('textarea')
    this.content.className = 'form-control'
    this.content.rows = 8
    this.content.value = this.data.markdown || ''
    this.content.disabled = this.readOnly
    this.content.addEventListener('input', () => {
      this.data.markdown = this.content.value
      this.block.dispatchChange()
    })
    label.append(this.content)
    label.classList.toggle('d-none', !this.data.reference)
    this.root.append(label)

    const actions = document.createElement('div')
    actions.className = 'pw-newsletter-block-actions'
    this.refresh = this.button(this.config.labels.refresh, async () => {
      if (!window.confirm(this.config.labels.replace)) return
      const response = await this.request({ provider: this.data.provider, reference: this.data.reference, parameters: JSON.stringify(this.data.parameters || {}) })
      if (response) this.select(response.item)
    })
    actions.append(this.refresh)
    const save = document.createElement('details')
    const summary = document.createElement('summary')
    summary.textContent = this.config.labels.save
    save.append(summary)
    this.name = this.input('input', this.config.labels.name)
    save.append(this.field(this.name, this.config.labels.name), this.button(this.config.labels.save, () => this.saveCopy()))
    actions.append(save)
    this.root.append(actions)
    this.picker.classList.toggle('d-none', this.readOnly || Boolean(this.data.reference))
    actions.classList.toggle('d-none', this.readOnly || !this.data.reference)
    this.actions = actions
    this.refresh.classList.toggle('d-none', !this.data.reference)
    if (!this.readOnly && !this.data.reference) void this.loadProviders()
    return this.root
  }

  input(tag, label) {
    const input = document.createElement(tag)
    input.className = tag === 'select' ? 'form-select' : 'form-control'
    input.setAttribute('aria-label', label)
    if (tag === 'input') input.placeholder = label
    return input
  }

  field(input, text) {
    const label = document.createElement('label')
    label.className = 'pw-newsletter-block-field'
    label.append(text, input)
    return label
  }

  setStatus(message, error = false) {
    this.status.textContent = message
    this.status.classList.toggle('text-danger', error)
  }

  button(label, action) {
    const button = document.createElement('button')
    button.type = 'button'
    button.className = 'btn btn-outline-secondary btn-sm'
    button.textContent = label
    button.addEventListener('click', async () => {
      button.disabled = true
      try { await action() } catch { this.setStatus(this.config.labels.failed, true) }
      finally { button.disabled = false }
    })
    return button
  }

  async request(params) {
    const form = this.root.closest('form')
    const url = new URL(this.config.url, window.location.origin)
    for (const [key, value] of Object.entries(params)) url.searchParams.set(key, value)
    url.searchParams.set('audience', form?.querySelector('[name="Campaign[audience]"]')?.value || '')
    url.searchParams.set('scheduledAt', form?.querySelector('[name="Campaign[scheduledAt]"]')?.value || '')
    this.setStatus(this.config.labels.loading)
    this.picker.setAttribute('aria-busy', 'true')
    try {
      const response = await fetch(url)
      const data = await response.json()
      if (!response.ok) { this.setStatus(data.error || this.config.labels.failed, true); return null }
      this.setStatus('')
      return data
    } finally { this.picker.removeAttribute('aria-busy') }
  }

  async loadProviders() {
    try {
      const data = await this.request({})
      if (!data) return
      for (const [value, label] of Object.entries(data.providers)) {
        const option = document.createElement('option')
        option.value = value
        option.textContent = label
        this.provider.append(option)
      }
    } catch { this.setStatus(this.config.labels.failed, true) }
  }

  async search() {
    const data = await this.request({ provider: this.provider.value, q: this.query.value })
    if (!data) return
    this.results.replaceChildren()
    for (const item of data.items) {
      this.results.append(this.button(item.label, () => this.select(item)))
    }
    if (!data.items.length) this.setStatus(this.config.labels.empty)
  }

  select(item) {
    this.data = item
    this.heading.textContent = item.label
    this.content.value = item.markdown
    this.picker.classList.add('d-none')
    this.refresh.classList.remove('d-none')
    this.contentLabel.classList.remove('d-none')
    this.actions.classList.remove('d-none')
    this.block.dispatchChange()
    this.content.focus()
  }

  async saveCopy() {
    const body = new URLSearchParams({ _token: this.config.token, name: this.name.value, markdown: this.content.value })
    this.setStatus(this.config.labels.loading)
    const response = await fetch(this.config.saveUrl, { method: 'POST', body })
    const data = await response.json()
    this.setStatus(response.ok ? this.config.labels.saved : data.error || this.config.labels.failed, !response.ok)
  }

  save() { return { ...this.data, markdown: this.content.value } }
}

window.PushwordNewsletterBlock = EmailBlock
