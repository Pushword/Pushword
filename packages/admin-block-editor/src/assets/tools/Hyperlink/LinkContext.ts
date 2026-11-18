import type EditorJS from '@editorjs/editorjs'
import type { API } from '@editorjs/editorjs'
import Hyperlink from './Hyperlink'
import { selectedLink } from './selectedLink'
import { isSafeLinkUrl } from './safeLinkUrl'
import { captureSelection, restoreSelection, type EditorSelection } from '../utils/Undo/Selection'
import { textSelections } from '../utils/TextSelectionState'

/** Link properties stay separate from the native text range and its keyboard focus. */
export function setupLinkContext(
  editor: EditorJS,
  holder: HTMLElement,
  config: ConstructorParameters<typeof Hyperlink>[0]['config'],
  labels: Record<string, string>,
  signal: AbortSignal,
): void {
  const panel = document.createElement('div')
  panel.className = 'pw-link-context'
  panel.hidden = true
  panel.setAttribute('role', 'region')
  panel.setAttribute('aria-label', labels.Link ?? 'Link')
  const summary = document.createElement('div')
  summary.className = 'pw-link-context__summary'
  const address = document.createElement('a')
  address.className = 'pw-link-context__address'
  address.target = '_blank'
  address.rel = 'noopener noreferrer'
  const edit = document.createElement('button')
  edit.type = 'button'
  edit.textContent = labels['Edit link'] ?? 'Edit link'
  const unlink = document.createElement('button')
  unlink.type = 'button'
  unlink.textContent = labels['Remove link'] ?? 'Remove link'
  summary.append(address, edit, unlink)
  panel.append(summary)
  holder.append(panel)

  let anchor: HTMLAnchorElement | null = null
  let dismissed: HTMLAnchorElement | null = null
  let saved: EditorSelection | null = null
  let editing = false
  let tool: Hyperlink | null = null
  let dragging = false
  let timer: ReturnType<typeof setTimeout> | undefined
  const api = editor as unknown as API
  const hide = (): void => {
    tool?.clear()
    tool = null
    panel.querySelector('.link-options-wrapper')?.remove()
    editing = false
    panel.hidden = true
  }
  const finish = (): void => {
    dismissed = anchor
    hide()
    void restoreSelection(editor, holder, saved)
  }
  const position = (): void => {
    if (panel.hidden || !anchor?.isConnected) return
    const selection = window.getSelection()
    const range = selection?.rangeCount ? selection.getRangeAt(0) : null
    const bounds = !editing && range && anchor.contains(range.startContainer)
      ? range.getBoundingClientRect() : anchor.getBoundingClientRect()
    const box = panel.getBoundingClientRect()
    panel.style.left = `${Math.max(8, Math.min(bounds.left, innerWidth - box.width - 8))}px`
    // Keep the summary above the selection; Editor.js' formatting toolbar sits below it.
    const top = bounds.top >= box.height + 8 ? bounds.top - box.height - 8 : bounds.bottom + 8
    panel.style.top = `${Math.max(8, Math.min(top, innerHeight - box.height - 8))}px`
  }
  const refresh = (): void => {
    if (!panel.hidden && panel.contains(document.activeElement)) return
    if (editing) hide()
    const link = selectedLink()
    if (link !== dismissed) dismissed = null
    if (dragging || editor.readOnly.isEnabled || textSelections.has(holder) ||
        holder.querySelector('.ce-block--selected') || !link || !holder.contains(link) ||
        !link.closest('[contenteditable]')?.contains(document.activeElement) || link === dismissed) {
      hide()
      anchor = link
      return
    }
    anchor = link
    const href = link.getAttribute('href')!
    address.textContent = href
    address.title = href
    if (isSafeLinkUrl(href)) address.setAttribute('href', href)
    else address.removeAttribute('href')
    summary.hidden = false
    panel.hidden = false
    position()
  }
  const schedule = (): void => {
    if (!editing && selectedLink() !== dismissed) dismissed = null
    clearTimeout(timer)
    timer = setTimeout(refresh, 220)
  }
  const open = (): void => {
    if (!anchor?.isConnected || editor.readOnly.isEnabled) return
    saved = captureSelection(holder)
    api.inlineToolbar.close()
    tool = new Hyperlink({
      api: {
        ...api,
        styles: api.styles,
        selection: api.selection,
        i18n: { t: label => labels[label] ?? label },
        inlineToolbar: { ...api.inlineToolbar, close: finish },
      },
      ...(config ? { config } : {}),
    })
    panel.append(tool.renderActions())
    editing = true
    summary.hidden = true
    panel.hidden = false
    tool.edit(anchor)
    position()
  }

  // Buttons may be clicked without collapsing the text range before it is saved.
  summary.addEventListener('mousedown', event => event.preventDefault())
  edit.addEventListener('click', open)
  unlink.addEventListener('click', () => {
    if (!anchor || editor.readOnly.isEnabled) return
    saved = captureSelection(holder)
    anchor.replaceWith(...anchor.childNodes)
    finish()
  })
  const options = { signal }
  document.addEventListener('selectionchange', schedule, options)
  document.addEventListener('focusin', schedule, options)
  document.addEventListener('keyup', schedule, options)
  holder.addEventListener('input', schedule, options)
  document.addEventListener('pointerdown', event => {
    if (panel.contains(event.target as Node)) return
    if (editing) tool?.updateLink()
    dragging = holder.contains(event.target as Node)
    dismissed = null
    hide()
  }, options)
  const stopDragging = (): void => {
    dragging = false
    schedule()
  }
  document.addEventListener('pointerup', stopDragging, options)
  document.addEventListener('pointercancel', stopDragging, options)
  window.addEventListener('keydown', event => {
    const inText = event.target instanceof Element && holder.contains(event.target) &&
      !!event.target.closest('[contenteditable="true"]')
    if ((event.ctrlKey || event.metaKey) && !event.altKey && event.key.toLowerCase() === 'k' && inText) {
      const link = selectedLink()
      if (!link || !holder.contains(link) || editor.readOnly.isEnabled ||
          textSelections.has(holder) || holder.querySelector('.ce-block--selected')) return
      event.preventDefault()
      event.stopImmediatePropagation()
      anchor = link
      open()
    } else if (event.key === 'Escape' && !panel.hidden && (inText || panel.contains(event.target as Node))) {
      event.preventDefault()
      event.stopImmediatePropagation()
      if (!editing) saved = captureSelection(holder)
      finish()
    }
  }, { capture: true, signal })
  window.addEventListener('resize', position, options)
  document.addEventListener('scroll', position, { capture: true, signal })
  const resize = new ResizeObserver(position)
  resize.observe(panel)
  signal.addEventListener('abort', () => {
    clearTimeout(timer)
    resize.disconnect()
    panel.remove()
  }, { once: true })
}
