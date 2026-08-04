import {
  IconChevronDown,
  IconChevronLeft,
  IconChevronRight,
  IconListBulleted,
  IconTrash,
} from '@codexteam/icons'
import './outline.css'
import { GroupRegistry } from '../tools/Group/GroupRegistry'
import { buildOutlineTree, OutlineNode, OutlineSource } from './OutlineModel'

export interface OutlineToolMeta {
  title: string
  icon: string
}

export interface OutlineLabels {
  deleteBlock: string
  deleteGroup: string
  deleteSection: string
  outline: string
  toggleSection: string
}

export interface OutlinePanelOptions {
  holderId: string
  source: OutlineSource
  labels: OutlineLabels
  toolMeta: (type: string) => OutlineToolMeta
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
  private readonly source: OutlineSource
  private readonly labels: OutlineLabels
  private readonly toolMeta: (type: string) => OutlineToolMeta

  private readonly root: HTMLElement
  private readonly list: HTMLOListElement
  private readonly opener: HTMLButtonElement

  /** Entry indices of sections folded in the panel; reset by structural moves is fine. */
  private readonly foldedSections = new Set<number>()
  private refreshTimer: number | null = null

  constructor(options: OutlinePanelOptions) {
    this.holderId = options.holderId
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
    const tree = buildOutlineTree(this.source.entries())
    this.list.replaceChildren(...tree.map((node) => this.renderNode(node, 0)))
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
   * The mode manager hides the holder while a Monaco source view is open, and
   * panel edits would then be silently discarded by the next mode switch — so
   * the panel follows the holder's visibility.
   */
  private followEditorVisibility(): void {
    const holder = document.getElementById(this.holderId)
    if (holder === null) return

    const follow = (): void => {
      const off = holder.style.display === 'none'
      this.root.classList.toggle('pw-outline--off', off)
      this.opener.classList.toggle('pw-outline--off', off)
      if (!off) this.scheduleRefresh()
    }
    new MutationObserver(follow).observe(holder, {
      attributes: true,
      attributeFilter: ['style'],
    })
  }

  private renderNode(node: OutlineNode, depth: number): HTMLLIElement {
    const item = document.createElement('li')
    item.className = 'pw-outline-item'

    const row = document.createElement('div')
    row.className = 'pw-outline-row'
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
    return button
  }

  private buildActions(node: OutlineNode): HTMLElement {
    const actions = document.createElement('span')
    actions.className = 'pw-outline-actions'

    const start = node.entry.index
    const isSpan = node.spanEnd > start
    if (node.entry.type === GroupRegistry.START && isSpan) {
      actions.appendChild(
        this.deleteButton(this.labels.deleteGroup, start, node.spanEnd, true),
      )
    } else if (node.entry.level !== null && isSpan) {
      actions.append(
        this.deleteButton(this.labels.deleteBlock, start, start, false),
        this.deleteButton(this.labels.deleteSection, start, node.spanEnd, true),
      )
    } else {
      actions.appendChild(this.deleteButton(this.labels.deleteBlock, start, start, false))
    }

    return actions
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
