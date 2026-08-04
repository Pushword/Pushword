import {
  IconChevronDown,
  IconChevronLeft,
  IconChevronRight,
  IconListBulleted,
  IconMenuSmall,
  IconTrash,
} from '@codexteam/icons'
import './outline.css'
import { GroupRegistry } from '../tools/Group/GroupRegistry'
import { buildOutlineTree, OutlineNode, OutlineSource } from './OutlineModel'
import { DragSpan, dropIndexFor, isActualMove } from './outlineDnd'

export interface OutlineToolMeta {
  title: string
  icon: string
}

export interface OutlineLabels {
  deleteBlock: string
  deleteGroup: string
  deleteHeading: string
  deleteSection: string
  moveBlock: string
  moveGroup: string
  moveHeading: string
  moveSection: string
  outline: string
  toggleSection: string
}

export interface OutlinePanelOptions {
  holderId: string
  source: OutlineSource
  labels: OutlineLabels
  toolMeta: (type: string) => OutlineToolMeta
  /** Source serving a Monaco view ('markdown' | 'json'); null hides the panel there. */
  monacoSource?: (mode: string) => OutlineSource | null
}

const COLLAPSE_STORAGE_KEY = 'pw-outline-collapsed'
const FALLBACK_ICON =
  '<svg width="17" height="17" viewBox="0 0 17 17"><circle cx="8.5" cy="8.5" r="2" fill="currentColor"/></svg>'

/**
 * The left rail listing the blocks in use: fixed to the viewport over the
 * admin navigation, collapsible (persisted), fed by an OutlineSource so the
 * same panel serves the EditorJS surface and the Monaco source views. It
 * follows the mode switches on its own, by observing the holder EditorModeManager
 * shows and hides — no coupling back into the manager.
 */
export class OutlinePanel {
  private readonly holderId: string
  private readonly editorSource: OutlineSource
  private readonly monacoSource: ((mode: string) => OutlineSource | null) | undefined
  private source: OutlineSource
  private readonly labels: OutlineLabels
  private readonly toolMeta: (type: string) => OutlineToolMeta

  private readonly root: HTMLElement
  private readonly list: HTMLOListElement
  private readonly opener: HTMLButtonElement

  /** Entry indices of sections folded in the panel; reset by structural moves is fine. */
  private readonly foldedSections = new Set<number>()
  private refreshTimer: number | null = null

  /** Span being dragged from one of the panel handles, if any. */
  private dragging: DragSpan | null = null
  private unitCount = 0

  constructor(options: OutlinePanelOptions) {
    this.holderId = options.holderId
    this.editorSource = options.source
    this.monacoSource = options.monacoSource
    this.source = options.source
    this.labels = options.labels
    this.toolMeta = options.toolMeta

    this.list = document.createElement('ol')
    this.list.className = 'pw-outline-list'

    this.root = document.createElement('aside')
    this.root.className = 'pw-outline'
    this.root.setAttribute('aria-label', this.labels.outline)
    this.root.append(this.buildHead(), this.list)

    this.opener = this.iconButton(IconListBulleted, this.labels.outline, 'pw-outline-opener')
    this.opener.addEventListener('click', () => this.setCollapsed(false, true))

    document.body.append(this.root, this.opener)
    this.setCollapsed(this.initialCollapsed(), false)
    this.followEditorVisibility()
    this.attachListDropTarget()
    this.attachListKeyboard()
  }

  /** Debounced full re-render; every data change funnels through here. */
  scheduleRefresh(): void {
    if (this.refreshTimer !== null) window.clearTimeout(this.refreshTimer)
    this.refreshTimer = window.setTimeout(() => {
      this.refreshTimer = null
      this.refresh()
    }, 300)
  }

  refresh(): void {
    const focused =
      document.activeElement instanceof HTMLElement &&
      this.list.contains(document.activeElement)
        ? document.activeElement.closest('.pw-outline-row')?.getAttribute('data-index')
        : null

    const entries = this.source.entries()
    this.unitCount = entries.length
    const tree = buildOutlineTree(entries)
    this.list.replaceChildren(...tree.map((node) => this.renderNode(node, 0)))

    if (focused != null) {
      this.list
        .querySelector<HTMLButtonElement>(`[data-index="${focused}"] .pw-outline-label`)
        ?.focus()
    }
  }

  private buildHead(): HTMLElement {
    const head = document.createElement('header')
    head.className = 'pw-outline-head'

    const title = document.createElement('span')
    title.className = 'pw-outline-title'
    title.textContent = this.labels.outline

    const collapse = this.iconButton(IconChevronLeft, this.labels.outline, 'pw-outline-toggle')
    collapse.addEventListener('click', () => this.setCollapsed(true, true))

    head.append(title, collapse)
    return head
  }

  private initialCollapsed(): boolean {
    const stored = localStorage.getItem(COLLAPSE_STORAGE_KEY)
    if (stored !== null) return stored === '1'

    return !window.matchMedia('(min-width: 1200px)').matches
  }

  private setCollapsed(collapsed: boolean, persist: boolean): void {
    this.root.classList.toggle('pw-outline--collapsed', collapsed)
    this.opener.hidden = !collapsed
    if (persist) localStorage.setItem(COLLAPSE_STORAGE_KEY, collapsed ? '1' : '0')
  }

  /**
   * The mode manager hides the holder while a Monaco source view is open; the
   * panel follows that visibility to swap between the EditorJS source and the
   * Monaco one — no coupling back into the manager.
   */
  private followEditorVisibility(): void {
    const holder = document.getElementById(this.holderId)
    if (holder === null) return

    const follow = (): void => {
      this.swapSource(holder.style.display === 'none' ? this.currentMode() : null)
    }
    new MutationObserver(follow).observe(holder, {
      attributes: true,
      attributeFilter: ['style'],
    })
  }

  /** data-editor of the bound field: 'markdown' | 'json', or null for EditorJS. */
  private currentMode(): string | null {
    const holder = document.getElementById(this.holderId)
    const input = document.getElementById(holder?.getAttribute('data-input-id') ?? '')
    return input?.getAttribute('data-editor') ?? null
  }

  private swapSource(mode: string | null): void {
    this.source.dispose?.()
    const next = mode === null ? this.editorSource : (this.monacoSource?.(mode) ?? null)

    const off = next === null
    this.root.classList.toggle('pw-outline--off', off)
    this.opener.classList.toggle('pw-outline--off', off)
    if (next === null) return

    this.source = next
    next.bind?.(() => this.scheduleRefresh())
    this.scheduleRefresh()
  }

  private renderNode(node: OutlineNode, depth: number): HTMLLIElement {
    const item = document.createElement('li')
    item.className = 'pw-outline-item'

    const row = document.createElement('div')
    row.className = 'pw-outline-row'
    row.dataset.index = String(node.entry.index)
    row.style.setProperty('--pw-outline-depth', String(depth))
    if (node.entry.level !== null) {
      row.classList.add('pw-outline-row--header', `pw-outline-row--h${node.entry.level}`)
    }

    const folded = this.foldedSections.has(node.entry.index)
    row.append(
      node.children.length > 0 ? this.caretButton(node, folded) : this.caretSpacer(),
      this.labelButton(node),
      this.buildActions(node),
    )
    this.attachDropTarget(row, node, node.children.length > 0 && !folded)
    item.appendChild(row)

    if (node.children.length > 0 && !folded) {
      const children = document.createElement('ol')
      children.className = 'pw-outline-children'
      children.append(...node.children.map((child) => this.renderNode(child, depth + 1)))
      item.appendChild(children)
    }

    return item
  }

  private caretButton(node: OutlineNode, folded: boolean): HTMLButtonElement {
    const caret = this.iconButton(
      folded ? IconChevronRight : IconChevronDown,
      this.labels.toggleSection,
      'pw-outline-caret',
    )
    caret.setAttribute('aria-expanded', String(!folded))
    caret.addEventListener('click', () => {
      if (!this.foldedSections.delete(node.entry.index)) {
        this.foldedSections.add(node.entry.index)
      }
      this.refresh()
    })
    return caret
  }

  private caretSpacer(): HTMLSpanElement {
    const spacer = document.createElement('span')
    spacer.className = 'pw-outline-caret pw-outline-caret--none'
    return spacer
  }

  private labelButton(node: OutlineNode): HTMLButtonElement {
    const meta = this.toolMeta(node.entry.type)

    const icon = document.createElement('span')
    icon.className = 'pw-outline-icon'
    icon.innerHTML = meta.icon === '' ? FALLBACK_ICON : meta.icon

    const text = document.createElement('span')
    text.className = 'pw-outline-text'
    text.textContent = node.entry.label === '' ? meta.title : node.entry.label
    if (node.entry.label === '') text.classList.add('pw-outline-text--type')

    const button = document.createElement('button')
    button.type = 'button'
    button.className = 'pw-outline-label'
    button.title = meta.title
    button.append(icon, text)
    button.addEventListener('click', () => this.source.navigateTo(node.entry))
    button.addEventListener('keydown', (event) => this.moveByKeyboard(event, node))
    return button
  }

  /** Alt+Arrow moves the block one slot; Alt+Shift+Arrow its section (groups always whole). */
  private moveByKeyboard(event: KeyboardEvent, node: OutlineNode): void {
    if (!event.altKey || (event.key !== 'ArrowUp' && event.key !== 'ArrowDown')) return
    event.preventDefault()

    const start = node.entry.index
    const wholeSpan =
      node.entry.type === GroupRegistry.START ||
      (event.shiftKey && node.entry.level !== null)
    const end = wholeSpan ? node.spanEnd : start
    const up = event.key === 'ArrowUp'
    const to = up ? start - 1 : end + 2
    if (to < 0 || to > this.unitCount) return

    this.source.moveSpan(start, end, to)
    this.refresh()
    this.list
      .querySelector<HTMLButtonElement>(
        `[data-index="${up ? start - 1 : start + 1}"] .pw-outline-label`,
      )
      ?.focus()
  }

  /** Plain arrows walk the list from label to label. */
  private attachListKeyboard(): void {
    this.list.addEventListener('keydown', (event) => {
      if (event.altKey || (event.key !== 'ArrowUp' && event.key !== 'ArrowDown')) return
      const labels = [...this.list.querySelectorAll<HTMLButtonElement>('.pw-outline-label')]
      const current = labels.indexOf(document.activeElement as HTMLButtonElement)
      if (current === -1) return
      event.preventDefault()
      labels[current + (event.key === 'ArrowDown' ? 1 : -1)]?.focus()
    })
  }

  private buildActions(node: OutlineNode): HTMLElement {
    const actions = document.createElement('span')
    actions.className = 'pw-outline-actions'

    const start = node.entry.index
    const isSpan = node.spanEnd > start
    if (node.entry.type === GroupRegistry.START && isSpan) {
      // A group is atomic — its markers live and die together — so no block-alone pair.
      actions.append(
        this.dragHandle(this.labels.moveGroup, { start, end: node.spanEnd }, true),
        this.deleteButton(this.labels.deleteGroup, start, node.spanEnd, true),
      )
    } else if (node.entry.level !== null && isSpan) {
      // "the heading", not "the block": next to "the section" the generic word reads
      // as if it also covered the section's content.
      actions.append(
        this.dragHandle(this.labels.moveHeading, { start, end: start }, false),
        this.deleteButton(this.labels.deleteHeading, start, start, false),
        this.dragHandle(this.labels.moveSection, { start, end: node.spanEnd }, true),
        this.deleteButton(this.labels.deleteSection, start, node.spanEnd, true),
      )
    } else {
      actions.append(
        this.dragHandle(this.labels.moveBlock, { start, end: start }, false),
        this.deleteButton(this.labels.deleteBlock, start, start, false),
      )
    }

    return actions
  }

  private dragHandle(label: string, span: DragSpan, wholeSpan: boolean): HTMLButtonElement {
    const handle = this.iconButton(
      IconMenuSmall,
      label,
      'pw-outline-handle' + (wholeSpan ? ' pw-outline-action--span' : ''),
    )
    handle.draggable = true
    handle.addEventListener('dragstart', (event) => {
      this.dragging = span
      const row = handle.closest('.pw-outline-row')
      if (event.dataTransfer && row instanceof HTMLElement) {
        event.dataTransfer.effectAllowed = 'move'
        // Firefox starts no drag without data.
        event.dataTransfer.setData('text/plain', '')
        event.dataTransfer.setDragImage(row, 0, 0)
      }
    })
    handle.addEventListener('dragend', () => {
      this.dragging = null
      this.clearDropIndicator()
    })
    return handle
  }

  private attachDropTarget(
    row: HTMLElement,
    node: OutlineNode,
    childrenVisible: boolean,
  ): void {
    row.addEventListener('dragover', (event) => {
      if (this.dragging === null) return
      const below = this.isBelow(event, row)
      if (!isActualMove(this.dragging, dropIndexFor(node, below, childrenVisible))) {
        this.clearDropIndicator()
        return
      }
      event.preventDefault()
      if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
      this.showDropIndicator(row, below)
    })
    row.addEventListener('dragleave', () => {
      row.classList.remove('pw-outline-drop--above', 'pw-outline-drop--below')
    })
    row.addEventListener('drop', (event) => {
      if (this.dragging === null) return
      event.preventDefault()
      this.dropSpan(dropIndexFor(node, this.isBelow(event, row), childrenVisible))
    })
  }

  /** The list's own padding is the drop zone for "after everything". */
  private attachListDropTarget(): void {
    this.list.addEventListener('dragover', (event) => {
      if (this.dragging === null || event.target !== this.list) return
      if (!isActualMove(this.dragging, this.unitCount)) return
      event.preventDefault()
      if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
      const lastRow = [...this.list.querySelectorAll<HTMLElement>('.pw-outline-row')].pop()
      if (lastRow !== undefined) this.showDropIndicator(lastRow, true)
    })
    this.list.addEventListener('drop', (event) => {
      if (this.dragging === null || event.target !== this.list) return
      event.preventDefault()
      this.dropSpan(this.unitCount)
    })
  }

  private dropSpan(to: number): void {
    const span = this.dragging
    this.dragging = null
    this.clearDropIndicator()
    if (span === null || !isActualMove(span, to)) return

    this.source.moveSpan(span.start, span.end, to)
    this.scheduleRefresh()
  }

  private isBelow(event: DragEvent, row: HTMLElement): boolean {
    const rect = row.getBoundingClientRect()
    return event.clientY > rect.top + rect.height / 2
  }

  private showDropIndicator(row: HTMLElement, below: boolean): void {
    this.clearDropIndicator()
    row.classList.add(below ? 'pw-outline-drop--below' : 'pw-outline-drop--above')
  }

  private clearDropIndicator(): void {
    this.root
      .querySelectorAll('.pw-outline-drop--above, .pw-outline-drop--below')
      .forEach((row) => row.classList.remove('pw-outline-drop--above', 'pw-outline-drop--below'))
  }

  private deleteButton(
    label: string,
    start: number,
    end: number,
    wholeSpan: boolean,
  ): HTMLButtonElement {
    const button = this.iconButton(
      IconTrash,
      label,
      'pw-outline-action' + (wholeSpan ? ' pw-outline-action--span' : ''),
    )
    button.addEventListener('click', () => {
      this.source.deleteSpan(start, end)
      this.scheduleRefresh()
    })
    return button
  }

  private iconButton(icon: string, label: string, className: string): HTMLButtonElement {
    const button = document.createElement('button')
    button.type = 'button'
    button.className = className
    button.innerHTML = icon
    button.title = label
    button.setAttribute('aria-label', label)
    return button
  }
}
