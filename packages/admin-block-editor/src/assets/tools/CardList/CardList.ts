import './index.css'
import make from '../utils/make'
import ToolboxIcon from './toolbox-icon.svg?raw'
import { MarkdownUtils } from '../utils/MarkdownUtils'
import { MediaUtils } from '../utils/media'
import { API, BlockToolData } from '@editorjs/editorjs'
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import { Suggest } from '../../../../../admin/src/Resources/assets/suggest.js'
import { BaseTool } from '../Abstract/BaseTool'
import { exportCardListToMarkdown } from './CardListExportToMarkdown'
import { jsonrepair } from 'jsonrepair'
import Raw from '../Raw/Raw'

const ImageIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>`
const RemoveImageIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>`
const ToggleIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>`
// Eye-slash icon for obfuscate
const ObfuscateIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`

export interface CardListItem {
  page?: string
  title?: string
  image?: string
  link?: string
  obfuscateLink?: boolean
  description?: string
  buttonLink?: string
  buttonLinkLabel?: string
}

export interface CardListData extends BlockToolData {
  items: CardListItem[]
}

interface CardListItemNodes {
  wrapper: HTMLElement
  pageInput: HTMLInputElement
  titleInput: HTMLElement
  imageContainer: HTMLElement
  imageValue: string
  linkInput: HTMLInputElement
  obfuscateLinkInput: HTMLInputElement
  descriptionInput: HTMLElement
  buttonLinkInput: HTMLInputElement
  buttonLinkLabelInput: HTMLInputElement
}

const MoveUpIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>`
const MoveDownIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>`
const DeleteIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>`
const AddIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>`

export default class CardList extends BaseTool {
  declare public data: CardListData
  private itemNodes: CardListItemNodes[] = []
  private itemsContainer?: HTMLElement

  public static toolbox = {
    title: 'Card List',
    icon: ToolboxIcon,
  }

  public static defaultData: CardListData = {
    items: [],
  }

  /**
   * Sanitizer rules - allow inline HTML in title field
   */
  static get sanitize() {
    return {
      items: {
        title: {
          br: true,
          span: { class: true },
          b: true,
          strong: true,
          i: true,
          em: true,
          u: true,
          s: true,
          small: true,
          a: { href: true, target: true, rel: true },
          sup: true,
          sub: true,
        },
        description: true,
      },
    }
  }

  constructor({
    data,
    api,
    readOnly,
  }: {
    data: CardListData
    api: API
    readOnly: boolean
  }) {
    super({ data, api, readOnly })

    this.data = {
      items: Array.isArray(data?.items) ? data.items : [],
    }
  }

  private getPageSlugs(): string[] {
    if (window.pagesUriList) {
      return window.pagesUriList.map((str: string) => str.replace(/^\//, ''))
    }
    return []
  }

  private hasCustomValues(item: CardListItem): boolean {
    return !!(
      item.title ||
      item.image ||
      item.link ||
      item.obfuscateLink ||
      item.description ||
      item.buttonLink ||
      item.buttonLinkLabel
    )
  }

  private createItemElement(item: CardListItem, index: number): CardListItemNodes {
    const wrapper = make.element('div', 'cardlist-item')

    // Header with slug input, toggle, and actions
    const header = make.element('div', 'cardlist-item-header')

    // Page/slug input in header
    const pageInputWrapper = make.element('div', 'cardlist-header-input')
    // Use custom title or link as placeholder hint when no slug
    const placeholderHint = item.title || item.link || ''
    const placeholder = placeholderHint ? `→ ${placeholderHint}` : 'Page slug...'
    const pageInput = make.element('input', 'cardlist-slug-input', {
      type: 'text',
      name: 'page',
      value: item.page || '',
      placeholder,
    }) as HTMLInputElement
    const pageSuggester = make.element('div', 'page-suggester')
    pageInputWrapper.appendChild(pageInput)
    pageInputWrapper.appendChild(pageSuggester)

    const pageSlugs = this.getPageSlugs()
    if (pageSlugs.length > 0) {
      // @ts-ignore
      new Suggest.Local(pageInput, pageSuggester, pageSlugs, {
        highlight: true,
        dispMax: 10,
      })
    }

    // Validate slug on blur with debounce
    let slugValidationTimeout: ReturnType<typeof setTimeout> | null = null
    pageInput.addEventListener('blur', () => {
      if (slugValidationTimeout) clearTimeout(slugValidationTimeout)
      slugValidationTimeout = setTimeout(() => {
        const slug = pageInput.value
        const isValid = this.isValidSlug(slug)
        pageInput.classList.toggle('cardlist-slug-invalid', !isValid)
      }, 500)
    })

    // Clear invalid state when user starts typing and update placeholder if cleared
    pageInput.addEventListener('input', () => {
      pageInput.classList.remove('cardlist-slug-invalid')
      // Reset placeholder to default when slug has value
      if (pageInput.value) {
        pageInput.placeholder = 'Page slug...'
      }
    })

    // Toggle button for customization (inline in header)
    const hasCustom = this.hasCustomValues(item)
    const toggleBtnClasses = hasCustom
      ? ['cardlist-toggle-btn', 'cardlist-toggle-btn--has-content']
      : ['cardlist-toggle-btn']
    const toggleBtn = make.element('button', toggleBtnClasses, { type: 'button' })
    toggleBtn.innerHTML = ToggleIcon + '<span class="cardlist-toggle-dot"></span>'

    const actions = make.element('div', 'cardlist-item-actions')

    const moveUpBtn = make.element(
      'button',
      'cardlist-item-btn',
      { type: 'button' },
      MoveUpIcon,
    )
    moveUpBtn.addEventListener('click', () => this.moveItem(index, -1))

    const moveDownBtn = make.element(
      'button',
      'cardlist-item-btn',
      { type: 'button' },
      MoveDownIcon,
    )
    moveDownBtn.addEventListener('click', () => this.moveItem(index, 1))

    const deleteBtn = make.element(
      'button',
      ['cardlist-item-btn', 'cardlist-item-btn--delete'],
      { type: 'button' },
      DeleteIcon,
    )
    deleteBtn.addEventListener('click', () => this.removeItem(index))

    actions.appendChild(toggleBtn)
    actions.appendChild(moveUpBtn)
    actions.appendChild(moveDownBtn)
    actions.appendChild(deleteBtn)

    header.appendChild(pageInputWrapper)
    header.appendChild(actions)

    // Customization fields container
    const customFieldsClasses = hasCustom
      ? ['cardlist-custom-fields']
      : ['cardlist-custom-fields', 'cardlist-custom-fields--hidden']
    const customFields = make.element('div', customFieldsClasses)

    // Title field (contenteditable to preserve inline HTML)
    const titleField = this.createHtmlEditableField('Title', 'title', item.title || '')
    const titleInput = titleField.querySelector('[contenteditable]') as HTMLElement

    // Image field with media picker
    const {
      field: imageField,
      container: imageContainer,
      value: imageValue,
    } = this.createMediaPickerField('Image', item.image || '', index)

    // Link field with obfuscate checkbox on same row
    const linkRow = make.element('div', 'cardlist-link-row')
    const linkField = this.createField('Link', 'link', item.link || '')
    linkField.classList.add('cardlist-link-field')
    const linkInput = linkField.querySelector('input') as HTMLInputElement

    const obfuscateField = this.createIconCheckboxField(
      ObfuscateIcon,
      'obfuscateLink',
      item.obfuscateLink || false,
      'Obfuscate link',
    )
    const obfuscateLinkInput = obfuscateField.querySelector('input') as HTMLInputElement

    linkRow.appendChild(linkField)
    linkRow.appendChild(obfuscateField)

    // Update placeholder when title or link changes (only if slug is empty)
    const updatePlaceholder = () => {
      if (!pageInput.value) {
        const hint = titleInput.textContent || linkInput.value || ''
        pageInput.placeholder = hint ? `→ ${hint}` : 'Page slug...'
      }
    }
    titleInput.addEventListener('input', updatePlaceholder)
    linkInput.addEventListener('input', updatePlaceholder)

    // Description field (contenteditable with inline toolbar, full width)
    const descriptionField = this.createContentEditableField(
      'Description',
      'description',
      item.description || '',
    )
    descriptionField.classList.add('cardlist-item-field--full')
    const descriptionInput = descriptionField.querySelector(
      '[contenteditable]',
    ) as HTMLElement

    // Button Link field
    const buttonLinkField = this.createField(
      'Button Link',
      'buttonLink',
      item.buttonLink || '',
    )
    const buttonLinkInput = buttonLinkField.querySelector('input') as HTMLInputElement

    // Button Link Label field
    const buttonLinkLabelField = this.createField(
      'Button Label',
      'buttonLinkLabel',
      item.buttonLinkLabel || '',
    )
    const buttonLinkLabelInput = buttonLinkLabelField.querySelector(
      'input',
    ) as HTMLInputElement

    // Button fields in a row (label first, then link)
    const buttonRow = make.element('div', 'cardlist-button-row')
    buttonRow.appendChild(buttonLinkLabelField)
    buttonRow.appendChild(buttonLinkField)

    // Add custom fields
    customFields.appendChild(titleField)
    customFields.appendChild(imageField)
    customFields.appendChild(linkRow)
    customFields.appendChild(descriptionField)
    customFields.appendChild(buttonRow)

    // Toggle button click handler
    toggleBtn.addEventListener('click', () => {
      const isHidden = customFields.classList.contains('cardlist-custom-fields--hidden')
      customFields.classList.toggle('cardlist-custom-fields--hidden', !isHidden)
      toggleBtn.classList.toggle('cardlist-toggle-btn--active', isHidden)
    })

    wrapper.appendChild(header)
    wrapper.appendChild(customFields)

    return {
      wrapper,
      pageInput,
      titleInput,
      imageContainer,
      imageValue,
      linkInput,
      obfuscateLinkInput,
      descriptionInput,
      buttonLinkInput,
      buttonLinkLabelInput,
    }
  }

  private createField(label: string, name: string, value: string): HTMLElement {
    const field = make.element('div', 'cardlist-item-field')
    const labelEl = make.element('label')
    labelEl.textContent = label
    const input = make.element('input', null, {
      type: 'text',
      name,
      value,
      placeholder: label,
    }) as HTMLInputElement
    field.appendChild(labelEl)
    field.appendChild(input)
    return field
  }

  private createContentEditableField(
    label: string,
    name: string,
    value: string,
  ): HTMLElement {
    const field = make.element('div', 'cardlist-item-field')
    const labelEl = make.element('label')
    labelEl.textContent = label
    const editable = make.element(
      'div',
      ['cardlist-description', 'ce-paragraph', 'cdx-block'],
      {
        contentEditable: !this.readOnly ? 'true' : 'false',
        'data-name': name,
        'data-placeholder': label,
      },
    )
    // Convert markdown to HTML for editing
    if (value) {
      editable.innerHTML = MarkdownUtils.convertInlineMarkdownToHtml(value)
    }
    field.appendChild(labelEl)
    field.appendChild(editable)
    return field
  }

  private createHtmlEditableField(
    label: string,
    name: string,
    value: string,
  ): HTMLElement {
    const field = make.element('div', 'cardlist-item-field')
    const labelEl = make.element('label')
    labelEl.textContent = label
    const editable = make.element(
      'div',
      ['cardlist-title'],
      {
        contentEditable: !this.readOnly ? 'true' : 'false',
        'data-name': name,
        'data-placeholder': label,
      },
    )
    // Keep raw HTML as-is, decode entities if needed
    if (value) {
      // Decode HTML entities in case they were encoded during storage
      const decoded = value
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&amp;/g, '&')
        .replace(/&quot;/g, '"')
      editable.innerHTML = decoded
    }
    field.appendChild(labelEl)
    field.appendChild(editable)
    return field
  }

  private createIconCheckboxField(
    icon: string,
    name: string,
    checked: boolean,
    title: string,
  ): HTMLElement {
    const field = make.element('div', 'cardlist-icon-checkbox')
    const input = make.element('input', null, {
      type: 'checkbox',
      name,
      id: `${name}-${Date.now()}`,
    }) as HTMLInputElement
    if (checked) {
      input.checked = true
    }
    const labelEl = make.element('label', 'cardlist-icon-checkbox-label', { for: input.id, title })
    labelEl.innerHTML = icon
    field.appendChild(input)
    field.appendChild(labelEl)
    return field
  }

  private createMediaPickerField(
    label: string,
    value: string,
    itemIndex: number,
  ): { field: HTMLElement; container: HTMLElement; value: string } {
    const field = make.element('div', 'cardlist-item-field')
    const labelEl = make.element('label')
    labelEl.textContent = label

    const container = make.element('div', 'cardlist-media-picker')
    container.dataset.value = value

    const preview = make.element('div', 'cardlist-media-preview')
    if (value) {
      const img = make.element('img') as HTMLImageElement
      img.src = MediaUtils.buildFullUrl(value)
      preview.appendChild(img)
    }

    const actions = make.element('div', 'cardlist-media-actions')

    const selectBtn = make.element(
      'button',
      'cardlist-media-btn',
      { type: 'button' },
      ImageIcon + ' Select',
    )
    selectBtn.addEventListener('click', () => this.openMediaPicker(itemIndex))

    const removeBtn = make.element(
      'button',
      ['cardlist-media-btn', 'cardlist-media-btn--remove'],
      { type: 'button' },
      RemoveImageIcon,
    )
    removeBtn.style.display = value ? 'flex' : 'none'
    removeBtn.addEventListener('click', () => {
      container.dataset.value = ''
      preview.innerHTML = ''
      removeBtn.style.display = 'none'
    })

    actions.appendChild(selectBtn)
    actions.appendChild(removeBtn)

    container.appendChild(preview)
    container.appendChild(actions)

    field.appendChild(labelEl)
    field.appendChild(container)

    return { field, container, value }
  }

  private openMediaPicker(itemIndex: number): void {
    const selectElement = document.querySelector(
      'select[id*="inline_image"]',
    ) as HTMLSelectElement | null
    if (!selectElement) {
      this.api.notifier.show({ message: 'Media picker not available', style: 'error' })
      return
    }

    const pickerWrapper = selectElement.closest('.pw-media-picker') as HTMLElement | null
    if (!pickerWrapper) return

    const actionButton = pickerWrapper.querySelector(
      '[data-pw-media-picker-action="choose"]',
    ) as HTMLButtonElement | null
    if (!actionButton) return

    const messageHandler = (event: MessageEvent) => {
      if (event.origin !== window.location.origin) return
      const payload = event.data
      if (!payload || payload.type !== 'pw-media-picker-select') return
      if (payload.fieldId !== selectElement.id) return

      window.removeEventListener('message', messageHandler)

      const media = payload.media
      if (!media) return

      const mediaName = media.fileName || String(media.id)
      this.setItemImage(itemIndex, mediaName)
    }

    window.addEventListener('message', messageHandler)
    actionButton.click()
  }

  private setItemImage(itemIndex: number, mediaName: string): void {
    const nodes = this.itemNodes[itemIndex]
    if (!nodes) return

    nodes.imageContainer.dataset.value = mediaName
    nodes.imageValue = mediaName

    const preview = nodes.imageContainer.querySelector('.cardlist-media-preview')
    if (preview) {
      preview.innerHTML = ''
      const img = make.element('img') as HTMLImageElement
      img.src = MediaUtils.buildFullUrl(mediaName)
      preview.appendChild(img)
    }

    const removeBtn = nodes.imageContainer.querySelector(
      '.cardlist-media-btn--remove',
    ) as HTMLElement
    if (removeBtn) {
      removeBtn.style.display = 'flex'
    }
  }

  private moveItem(index: number, direction: number): void {
    this.updateDataFromNodes()
    const newIndex = index + direction
    if (newIndex < 0 || newIndex >= this.data.items.length) return

    const items = this.data.items
    const temp = items[index]!
    items[index] = items[newIndex]!
    items[newIndex] = temp

    this.renderItems()
  }

  private removeItem(index: number): void {
    this.updateDataFromNodes()
    this.data.items.splice(index, 1)
    this.renderItems()
  }

  private addItem(): void {
    this.updateDataFromNodes()
    this.data.items.push({})
    this.renderItems()
  }

  private updateDataFromNodes(): void {
    this.data.items = this.itemNodes.map((nodes): CardListItem => {
      const item: CardListItem = {}
      if (nodes.pageInput.value) item.page = nodes.pageInput.value
      // Get HTML content from title (contenteditable)
      const titleHtml = nodes.titleInput.innerHTML?.trim()
      if (titleHtml) item.title = titleHtml
      // Get image from media picker container
      const imageValue = nodes.imageContainer.dataset.value
      if (imageValue) item.image = imageValue
      if (nodes.linkInput.value) item.link = nodes.linkInput.value
      if (nodes.obfuscateLinkInput.checked)
        item.obfuscateLink = nodes.obfuscateLinkInput.checked
      // Convert HTML to markdown for description
      const descriptionHtml = nodes.descriptionInput.innerHTML?.trim()
      if (descriptionHtml) {
        item.description = MarkdownUtils.convertInlineHtmlToMarkdown(descriptionHtml)
      }
      if (nodes.buttonLinkInput.value) item.buttonLink = nodes.buttonLinkInput.value
      if (nodes.buttonLinkLabelInput.value)
        item.buttonLinkLabel = nodes.buttonLinkLabelInput.value
      return item
    })
  }

  private renderItems(): void {
    if (!this.itemsContainer) return

    this.itemsContainer.innerHTML = ''
    this.itemNodes = []

    this.data.items.forEach((item, index) => {
      const nodes = this.createItemElement(item, index)
      this.itemNodes.push(nodes)
      this.itemsContainer!.appendChild(nodes.wrapper)
    })
  }

  public render(): HTMLElement {
    const wrapper = make.element('div', ['cardlist-wrapper', this.api.styles.block])

    this.itemsContainer = make.element('div', 'cardlist-items')
    this.renderItems()

    const addBtn = make.element(
      'button',
      'cardlist-add-btn',
      { type: 'button' },
      AddIcon + ' Add Card',
    )
    addBtn.addEventListener('click', () => this.addItem())

    wrapper.appendChild(this.itemsContainer)
    wrapper.appendChild(addBtn)

    return wrapper
  }

  public save(): CardListData {
    this.updateDataFromNodes()
    // Filter out empty items
    this.data.items = this.data.items.filter(
      (item) => item.page || item.title || item.image || item.link || item.description,
    )
    return this.data
  }

  private isValidSlug(slug: string): boolean {
    if (!slug) return true // Empty is valid
    const slugs = this.getPageSlugs()
    return slugs.includes(slug)
  }

  public validate(): boolean {
    this.updateDataFromNodes()
    if (this.data.items.length === 0) return false

    // Check all slugs are valid (visual feedback already handled by blur event)
    let allValid = true
    this.itemNodes.forEach((nodes) => {
      const slug = nodes.pageInput.value
      const isValid = this.isValidSlug(slug)
      nodes.pageInput.classList.toggle('cardlist-slug-invalid', !isValid)
      if (!isValid) allValid = false
    })

    return allValid
  }

  public static exportToMarkdown(data: CardListData, tunes?: BlockTuneData): string {
    return exportCardListToMarkdown(data, tunes)
  }

  static importFromMarkdown(editor: API, markdown: string): void {
    const result = MarkdownUtils.parseTunesFromMarkdown(markdown)
    const tunes: BlockTuneData = result.tunes
    markdown = result.markdown

    const match = markdown.match(/\{\{\s*card_list\(\s*(\[.*\])\s*\)\s*\}\}/s)
    if (!match || !match[1]) return

    try {
      const items = JSON.parse(jsonrepair(match[1])) as CardListItem[]
      const data: CardListData = { items }

      const block = editor.blocks.insert('card_list', data)
      editor.blocks.update(block.id, data, tunes)
    } catch (e) {
      console.error('Failed to parse card_list data:', e)
      Raw.importFromMarkdown(editor, markdown)
    }
  }

  static isItMarkdownExported(markdown: string): boolean {
    return markdown.trim().match(/\{\{\s*card_list\(\s*\[.*\]\s*\)\s*\}\}/s) !== null
  }
}
