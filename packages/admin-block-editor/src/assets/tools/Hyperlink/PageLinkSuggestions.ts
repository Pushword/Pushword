export interface PageLink {
  uri: string
  title: string
}

export default class PageLinkSuggestions {
  private static nextId = 0
  private results: PageLink[] = []
  private activeIndex = -1
  private searchTimer?: ReturnType<typeof setTimeout>
  private list = document.createElement('div')
  private status = document.createElement('div')
  private preview = document.createElement('div')

  constructor(
    private input: HTMLInputElement,
    container: HTMLElement,
    private pages: PageLink[],
    private translate: (label: string) => string,
  ) {
    this.list.id = `page-link-suggestions-${++PageLinkSuggestions.nextId}`
    this.list.className = 'link-suggestions__list'
    this.list.setAttribute('role', 'listbox')
    this.list.setAttribute('aria-label', this.translate('Pages'))
    this.status.className = 'link-suggestions__status'
    this.status.setAttribute('role', 'status')
    this.preview.className = 'link-suggestions__preview'
    container.append(this.list, this.status, this.preview)

    input.id ||= `${this.list.id}-input`
    input.setAttribute('role', 'combobox')
    input.setAttribute('aria-autocomplete', 'list')
    input.setAttribute('aria-controls', this.list.id)
    input.setAttribute('autocomplete', 'off')
    input.addEventListener('input', () => {
      clearTimeout(this.searchTimer)
      // Mutating suggestion text inside the native input event splits Chrome's
      // typing history into individual characters. Render after that event ends.
      this.searchTimer = setTimeout(() => this.search(), 0)
    })
    input.addEventListener('focus', () => this.search())
    input.addEventListener('blur', () => this.close())
    input.addEventListener('keydown', (event) => this.keydown(event))
    // Keep the editor's text selection and the input's focus while choosing a row.
    this.list.addEventListener('mousedown', (event) => event.preventDefault())
    this.close()
  }

  private renderPage(page: PageLink): HTMLElement {
    const row = document.createElement('span')
    row.className = 'link-suggestions__page'
    const title = document.createElement('span')
    title.className = 'link-suggestions__title'
    title.textContent = page.title
    const path = document.createElement('span')
    path.className = 'link-suggestions__path'
    path.textContent = page.uri
    if (page.title) row.append(title)
    row.append(path)
    return row
  }

  private search(): void {
    const query = this.input.value.trim().toLowerCase()
    this.close()
    if (!query) return

    const path = query.startsWith('/') ? query : `/${query}`
    const rank = (page: PageLink): number => {
      const uri = page.uri.toLowerCase()
      if (uri === path) return 0
      return uri.startsWith(path) ? 1 : 2
    }
    const matches = this.pages
      .filter(
        (page) =>
          page.uri.toLowerCase().includes(query) ||
          page.title.toLowerCase().includes(query),
      )
      .sort((a, b) => rank(a) - rank(b) || a.uri.localeCompare(b.uri))
    this.results = matches.slice(0, 20)
    this.list.replaceChildren()
    this.results.forEach((page, index) => {
      const option = this.renderPage(page)
      option.id = `${this.list.id}-${index}`
      option.setAttribute('role', 'option')
      option.setAttribute('aria-selected', 'false')
      option.addEventListener('click', () => this.select(index))
      this.list.append(option)
    })
    this.list.hidden = this.results.length === 0
    this.preview.hidden = true
    this.input.setAttribute('aria-expanded', String(this.results.length > 0))
    this.status.textContent = ''
    if (matches.length > 20) {
      this.status.textContent = this.translate(
        'First 20 pages. Refine your search to see more.',
      )
    } else if (matches.length === 0) {
      this.status.textContent = this.translate('No matching page. You can paste a URL.')
    }
    this.status.hidden = !this.status.textContent
  }

  private keydown(event: KeyboardEvent): void {
    if (
      this.searchTimer !== undefined &&
      ['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)
    ) {
      this.search()
    }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      if (this.list.hidden) this.search()
      if (!this.results.length) return
      event.preventDefault()
      event.stopPropagation()
      const step = event.key === 'ArrowDown' ? 1 : -1
      if (this.activeIndex < 0) {
        this.activeIndex = step > 0 ? 0 : this.results.length - 1
      } else {
        this.activeIndex =
          (this.activeIndex + step + this.results.length) % this.results.length
      }
      Array.from(this.list.children).forEach((option, index) => {
        option.setAttribute('aria-selected', String(index === this.activeIndex))
      })
      const active = this.list.children[this.activeIndex] as HTMLElement
      this.input.setAttribute('aria-activedescendant', active.id)
      active.scrollIntoView({ block: 'nearest' })
    } else if (event.key === 'Enter' && this.activeIndex >= 0) {
      event.preventDefault()
      event.stopImmediatePropagation()
      this.select(this.activeIndex)
    } else if (
      event.key === 'Escape' &&
      (!this.list.hidden || this.searchTimer !== undefined)
    ) {
      event.preventDefault()
      event.stopImmediatePropagation()
      this.close()
    }
  }

  private select(index: number): void {
    this.input.value = this.results[index].uri
    this.close()
  }

  close(): void {
    clearTimeout(this.searchTimer)
    this.searchTimer = undefined
    this.results = []
    this.activeIndex = -1
    this.list.hidden = true
    this.status.hidden = true
    this.input.setAttribute('aria-expanded', 'false')
    this.input.removeAttribute('aria-activedescendant')
    const uri = this.input.value.trim()
    const page = this.pages.find((page) => page.uri === uri)
    this.preview.replaceChildren(this.renderPage(page ?? { uri, title: '' }))
    this.preview.hidden = !uri
  }
}
