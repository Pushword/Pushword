import type EditorJS from '@editorjs/editorjs'
import { GroupRegistry } from '../Group/GroupRegistry'
import { embeddedEditors } from './Undo/Selection'
import { fieldRange, selectedBlocks, textFieldAt, textFields, textSelections, wholeBlockSelected } from './TextSelectionState'

function hasContent(field: Element): boolean {
  return Boolean(field.textContent || field.querySelector('img'))
}

/** Delete text inside boundary tools, and whole blocks only between the endpoints. */
export function replaceTextSelection(editor: EditorJS, holder: HTMLElement, range: Range, text: string): void {
  const blocks = selectedBlocks(holder, range)
  const first = textFieldAt(range.startContainer, holder)
  const last = textFieldAt(range.endContainer, holder)
  const fields = textFields(holder).filter(field => range.intersectsNode(field))
  const removed = new Set(blocks.filter((block, index) =>
    (index > 0 && index < blocks.length - 1) || wholeBlockSelected(block, range),
  ).map(block => block.dataset.id!))

  // Crossing one group boundary must not cascade deletion to an unselected partner.
  const markers = Array.from(holder.querySelectorAll<HTMLElement>('.ce-block')).flatMap(block => {
    const marker = block.querySelector<HTMLElement>('.pw-group-start, .pw-group-end')
    return marker ? [{ id: block.dataset.id!, name: marker.classList.contains('pw-group-start') ? 'groupStart' : 'groupEnd',
      kind: marker.dataset.pwGroupKind === 'showMore' ? 'showMore' as const : 'div' as const }] : []
  })
  const pairs = GroupRegistry.computePairs(markers)
  for (const marker of markers) {
    const partner = pairs.get(marker.id)
    if (!partner || !removed.has(partner)) removed.delete(marker.id)
  }

  const parts = fields.map(field => {
    const selected = fieldRange(field, range)
    const before = document.createRange()
    before.selectNodeContents(field)
    before.setEnd(selected.startContainer, selected.startOffset)
    const after = document.createRange()
    after.selectNodeContents(field)
    after.setStart(selected.endContainer, selected.endOffset)
    return { field, before: before.cloneContents(), after: after.cloneContents() }
  })
  textSelections.delete(holder)
  window.getSelection()!.removeAllRanges()
  const target = first ?? last
  const inserted = document.createTextNode(text)
  for (const part of parts) {
    part.field.replaceChildren(part.before, ...(part.field === target ? [inserted] : []), part.after)
  }

  const firstBlock = first?.closest<HTMLElement>('.ce-block')
  const lastBlock = last?.closest<HTMLElement>('.ce-block')
  const protectedGroup = blocks.some(block => block.querySelector('.pw-group-start, .pw-group-end') && !removed.has(block.dataset.id!))
  if (first && last && first !== last && !protectedGroup) {
    if (first.matches('.ce-paragraph') && last.matches('.ce-paragraph')) {
      first.append(...Array.from(last.childNodes))
      removed.add(lastBlock!.dataset.id!)
    } else {
      const firstItem = first.closest('.cdx-list__item')
      const lastItem = last.closest('.cdx-list__item')
      if (firstItem && lastItem && firstItem !== lastItem && firstItem.parentElement === lastItem.parentElement &&
        !first.closest('.cdx-list-checklist')) {
        first.append(...Array.from(last.childNodes))
        const children = lastItem.querySelector(':scope > .cdx-list__item-children')
        if (children) {
          const existing = firstItem.querySelector(':scope > .cdx-list__item-children')
          if (existing) existing.append(...Array.from(children.childNodes))
          else firstItem.append(children)
        }
        lastItem.remove()
      }
    }
  }

  // Remove selected empty list items from the leaves up; retained children keep their parent.
  for (const field of Array.from(fields).reverse()) {
    if (!holder.contains(field)) continue
    const item = field.closest('.cdx-list__item')
    if (!item || hasContent(field) || item.querySelector(':scope > .cdx-list__item-children > .cdx-list__item')) continue
    const wrapper = item.closest('.cdx-list')!
    if (firstBlock === lastBlock && wrapper.querySelectorAll('.cdx-list__item').length === 1) {
      item.querySelector('.cdx-list__checkbox--checked')?.classList.remove('cdx-list__checkbox--checked')
    } else item.remove()
  }
  for (const block of blocks) {
    if (removed.has(block.dataset.id!)) continue
    const name = editor.blocks.getBlockByIndex(editor.blocks.getBlockIndex(block.dataset.id!))!.name
    if (name === 'list' && !block.querySelector('.cdx-list__item')) removed.add(block.dataset.id!)
    if (['paragraph', 'header', 'quote'].includes(name) && textFields(block).every(field => !hasContent(field))) {
      if (!(first === last || (first?.matches('.ce-paragraph') && last?.matches('.ce-paragraph') && block === firstBlock))) {
        removed.add(block.dataset.id!)
      }
    }
  }

  // Insert before removing everything, otherwise Editor.js creates an extra default block.
  let fallback: string | undefined
  if (!target || (removed.has(firstBlock?.dataset.id ?? '') && removed.has(lastBlock?.dataset.id ?? ''))) {
    const index = editor.blocks.getBlockIndex(blocks[0]!.dataset.id!)
    const escaped = document.createElement('span')
    escaped.textContent = text
    fallback = editor.blocks.insert('paragraph', { text: escaped.innerHTML }, undefined, index, false).id
  }
  for (const id of Array.from(removed).reverse()) {
    const index = editor.blocks.getBlockIndex(id)
    if (index >= 0) editor.blocks.delete(index)
  }
  if (fallback) {
    editor.caret.setToBlock(fallback, 'end')
    return
  }
  const caret = document.createRange()
  if (inserted.isConnected) {
    target!.focus({ preventScroll: true })
    caret.setStart(inserted, inserted.length)
  } else {
    const field = [last, first].find(field => field?.isConnected) ?? textFields(holder)[0]
    if (!field) return
    field.focus({ preventScroll: true })
    caret.selectNodeContents(field)
    caret.collapse(field === last)
  }
  caret.collapse(true)
  window.getSelection()!.removeAllRanges()
  window.getSelection()!.addRange(caret)
}

function fragmentHtml(field: HTMLElement, range: Range): string {
  const container = document.createElement('div')
  container.append(fieldRange(field, range).cloneContents())
  return container.innerHTML
}

function plain(html: string): string {
  const container = document.createElement('div')
  container.innerHTML = html.replace(/\n/g, ' ')
  container.querySelectorAll('br').forEach(br => br.replaceWith('\n'))
  return container.textContent ?? ''
}

interface ListItem { content: string; meta: Record<string, unknown>; items: ListItem[] }

/** Semantic fragments omit toolbars, heading pickers and embedded editor chrome. */
export function textSelectionClipboard(holder: HTMLElement, range: Range): Record<string, string> {
  const fragments: { text: string; html: string }[] = []
  let listData: { style: string; meta: Record<string, unknown>; items: ListItem[] } | undefined
  const blocks = selectedBlocks(holder, range)
  for (const block of blocks) {
    if (block.querySelector('.pw-group-start, .pw-group-end')) continue
    const fields = textFields(block).filter(field => range.intersectsNode(field) && !fieldRange(field, range).collapsed)
    const images = wholeBlockSelected(block, range) ? Array.from(block.querySelectorAll('img')) : []
    if (images.length) {
      const caption = fields.map(field => plain(fragmentHtml(field, range))).join('\n')
      const copies = images.map(image => image.cloneNode(true) as HTMLImageElement)
      if (copies.length === 1 && caption) copies[0]!.alt = caption
      fragments.push({ text: copies.map(image => image.alt).join('\n'), html: copies.map(image => image.outerHTML).join('') })
      continue
    }
    const list = block.querySelector<HTMLElement>('.cdx-list')
    if (list) {
      const lines: string[] = []
      const style = list.classList.contains('cdx-list-checklist') ? 'checklist' : list.tagName === 'OL' ? 'ordered' : 'unordered'
      const itemsOf = (wrapper: Element): ListItem[] => Array.from(wrapper.children).flatMap(item => {
        const field = item.querySelector<HTMLElement>(':scope > .cdx-list__item-content')!
        const children = item.querySelector(':scope > .cdx-list__item-children')
        const selected = fields.includes(field)
        const content = selected ? fragmentHtml(field, range) : ''
        if (selected) lines.push(plain(content))
        const items = children ? itemsOf(children) : []
        return selected ? [{ content, meta: style === 'checklist'
          ? { checked: Boolean(item.querySelector(':scope > .cdx-list__checkbox--checked')) } : {}, items }] : items
      })
      const items = itemsOf(list)
      const tag = style === 'ordered' ? 'ol' : 'ul'
      const html = (items: ListItem[]): string => `<${tag}>${items.map(item => `<li>${item.content}${item.items.length ? html(item.items) : ''}</li>`).join('')}</${tag}>`
      const meta: Record<string, unknown> = {}
      const start = /item\s+(-?\d+)/.exec(list.style.counterReset)
      if (start) meta.start = Number(start[1]) + 1
      const counterType = list.style.getPropertyValue('--list-counter-type')
      if (counterType) meta.counterType = counterType
      listData = { style, meta, items }
      fragments.push({ text: lines.join('\n'), html: html(items) })
    } else if (fields.length && block.querySelector('.tc-table')) {
      const rows = Array.from(block.querySelectorAll('.tc-row')).map(row =>
        Array.from(row.querySelectorAll<HTMLElement>('.tc-cell')),
      ).filter(cells => cells.some(cell => fields.includes(cell)))
      fragments.push({
        text: rows.map(cells => cells.map(cell => fields.includes(cell) ? plain(fragmentHtml(cell, range)) : '').join('\t')).join('\n'),
        html: `<table>${rows.map(cells => `<tr>${cells.map(cell => `<td>${fields.includes(cell) ? fragmentHtml(cell, range) : ''}</td>`).join('')}</tr>`).join('')}</table>`,
      })
    } else if (fields.length && block.querySelector('.cdx-quote')) {
      fragments.push({ text: fields.map(field => plain(fragmentHtml(field, range))).join('\n'),
        html: `<blockquote>${fields.map(field => {
          const tag = field.matches('.cdx-quote__caption') ? 'cite' : 'p'
          return `<${tag}>${fragmentHtml(field, range)}</${tag}>`
        }).join('')}</blockquote>` })
    } else if (fields.length) {
      const content = fields.map(field => fragmentHtml(field, range))
      const heading = fields[0]!.matches('.ce-header') ? fields[0]!.tagName.toLowerCase() : 'p'
      fragments.push({ text: content.map(plain).join('\n'), html: content.map(html => `<${heading}>${html}</${heading}>`).join('') })
    } else if (wholeBlockSelected(block, range)) {
      const wrapper = block.querySelector<HTMLElement>('.editorjs-monaco-wrapper')
      if (wrapper) {
        const embedded = embeddedEditors.get(wrapper)
        const source = embedded?.instance?.getValue() ?? embedded?.getValue?.() ?? ''
        const code = document.createElement('code')
        code.textContent = source
        fragments.push({ text: source, html: `<pre>${code.outerHTML}</pre>` })
      }
    }
  }
  const clipboard: Record<string, string> = {
    'text/plain': fragments.map(fragment => fragment.text).join('\n\n'),
    'text/html': fragments.map(fragment => fragment.html).join(''),
  }
  if (blocks.length === 1 && listData) clipboard['application/x-editor-js'] = JSON.stringify([{ tool: 'list', data: listData }])
  return clipboard
}
