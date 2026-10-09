import type EditorJS from '@editorjs/editorjs'
import type { editor, ISelection } from 'monaco-editor'

/** Raw and CodeBlock register their embedded editor without exposing Editor.js internals. */
export const embeddedEditors = new WeakMap<
  HTMLElement,
  {
    ready: Promise<void>
    instance: editor.IStandaloneCodeEditor | undefined
  }
>()

interface Point {
  block: string
  field: number
  path: number[]
  offset: number
  textOffset: number
}

export interface EditorSelection {
  anchor?: Point
  focus?: Point
  control?: {
    block: string
    field: number
    start?: number
    end?: number
    direction?: 'forward' | 'backward' | 'none'
    scrollTop?: number
    scrollLeft?: number
  }
  monaco?: {
    block: string
    selection: ISelection
    scrollTop: number
    scrollLeft: number
  }
  blocks?: string[]
}

const fields = (block: HTMLElement): HTMLElement[] =>
  Array.from(
    block.querySelectorAll<HTMLElement>(
      '.ce-block__content [contenteditable="true"], .ce-block__content input:not([type="hidden"]), .ce-block__content textarea, .ce-block__content select',
    ),
  ).filter((element) => !element.closest('.monaco-editor'))

function point(
  node: Node | null,
  offset: number,
  holder: HTMLElement,
): Point | undefined {
  const element = node instanceof Element ? node : node?.parentElement
  const block = element?.closest<HTMLElement>('.ce-block')
  if (!block || !holder.contains(block) || !node) return undefined
  const inputs = fields(block)
  const field = inputs.findIndex((input) => input === node || input.contains(node))
  if (field < 0) return undefined
  const root = inputs[field]!
  const path: number[] = []
  for (
    let current = node;
    current !== root && current.parentNode;
    current = current.parentNode
  ) {
    path.unshift(Array.from(current.parentNode.childNodes).indexOf(current as ChildNode))
  }
  const range = document.createRange()
  range.selectNodeContents(root)
  range.setEnd(node, offset)
  return {
    block: block.dataset.id!,
    field,
    path,
    offset,
    textOffset: range.toString().length,
  }
}

export function captureSelection(holder: HTMLElement): EditorSelection | null {
  const active = document.activeElement as HTMLElement | null
  const block = active?.closest<HTMLElement>('.ce-block')
  if (block && holder.contains(block)) {
    const wrapper = block.querySelector<HTMLElement>('.editorjs-monaco-wrapper')
    const monaco = wrapper && embeddedEditors.get(wrapper)?.instance
    if (monaco?.hasTextFocus()) {
      const selection = monaco.getSelection()
      if (selection)
        return {
          monaco: {
            block: block.dataset.id!,
            selection: { ...selection },
            scrollTop: monaco.getScrollTop(),
            scrollLeft: monaco.getScrollLeft(),
          },
        }
    }
    if (
      active instanceof HTMLInputElement ||
      active instanceof HTMLTextAreaElement ||
      active instanceof HTMLSelectElement
    ) {
      const field = fields(block).indexOf(active)
      if (
        field >= 0 &&
        !(active instanceof HTMLSelectElement) &&
        active.selectionStart !== null
      ) {
        return {
          control: {
            block: block.dataset.id!,
            field,
            start: active.selectionStart,
            end: active.selectionEnd!,
            direction: active.selectionDirection ?? 'none',
            scrollTop: active.scrollTop,
            scrollLeft: active.scrollLeft,
          },
        }
      }
      if (field >= 0) return { control: { block: block.dataset.id!, field } }
    }
  }
  const selected = Array.from(
    holder.querySelectorAll<HTMLElement>('.ce-block--selected'),
  ).map((node) => node.dataset.id!)
  if (selected.length) return { blocks: selected }
  const selection = window.getSelection()
  if (!selection) return null
  const anchor = point(selection.anchorNode, selection.anchorOffset, holder)
  const focus = point(selection.focusNode, selection.focusOffset, holder)
  return anchor && focus ? { anchor, focus } : null
}

function resolvePoint(root: HTMLElement, saved: Point): { node: Node; offset: number } {
  let node: Node | undefined = root
  for (const index of saved.path) node = node?.childNodes[index]
  const length =
    node?.nodeType === Node.TEXT_NODE ? node.textContent!.length : node?.childNodes.length
  if (node && length !== undefined && saved.offset <= length)
    return { node, offset: saved.offset }
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT)
  let remaining = saved.textOffset
  let last: Node = root
  while (walker.nextNode()) {
    last = walker.currentNode
    const count = last.textContent!.length
    if (remaining <= count) return { node: last, offset: remaining }
    remaining -= count
  }
  return {
    node: last,
    offset: last === root ? root.childNodes.length : last.textContent!.length,
  }
}

/** Restore the selection in one turn, then reveal it only when it is outside the viewport. */
export async function restoreSelection(
  editor: EditorJS,
  holder: HTMLElement,
  saved: EditorSelection | null,
): Promise<void> {
  if (!saved) return
  const blockId =
    saved.monaco?.block ?? saved.control?.block ?? saved.focus?.block ?? saved.blocks?.[0]
  const index = blockId ? editor.blocks.getBlockIndex(blockId) : -1
  if (index === undefined || index < 0) return
  const scroll = { x: window.scrollX, y: window.scrollY }
  const block = editor.blocks.getBlockByIndex(index)!
  let rectangle: DOMRect | undefined
  if (saved.monaco) {
    const wrapper = block.holder.querySelector<HTMLElement>('.editorjs-monaco-wrapper')
    const embedded = wrapper && embeddedEditors.get(wrapper)
    await embedded?.ready
    const monaco = embedded?.instance
    if (monaco) {
      monaco.focus()
      monaco.setSelection(saved.monaco.selection)
      monaco.setScrollPosition({
        scrollTop: saved.monaco.scrollTop,
        scrollLeft: saved.monaco.scrollLeft,
      })
      const bounds = monaco.getDomNode()?.getBoundingClientRect()
      const position = monaco.getPosition()
      const cursor = position && monaco.getScrolledVisiblePosition(position)
      if (bounds && cursor)
        rectangle = new DOMRect(
          bounds.x + cursor.left,
          bounds.y + cursor.top,
          1,
          cursor.height,
        )
    }
  } else if (saved.blocks) {
    editor.caret.setToBlock(index)
    for (const node of holder.querySelectorAll<HTMLElement>('.ce-block')) {
      node.classList.toggle('ce-block--selected', saved.blocks.includes(node.dataset.id!))
    }
    block.holder.tabIndex = -1
    block.holder.focus({ preventScroll: true })
    rectangle = block.holder.getBoundingClientRect()
  } else {
    editor.caret.setToBlock(index)
    if (saved.control) {
      const input = fields(block.holder)[saved.control.field] as
        HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement | undefined
      if (input) {
        input.closest('details')?.setAttribute('open', '')
        input.focus({ preventScroll: true })
        if (!(input instanceof HTMLSelectElement) && saved.control.start !== undefined) {
          input.setSelectionRange(
            saved.control.start,
            saved.control.end ?? saved.control.start,
            saved.control.direction,
          )
        }
        input.scrollTop = saved.control.scrollTop ?? 0
        input.scrollLeft = saved.control.scrollLeft ?? 0
        rectangle = input.getBoundingClientRect()
        // A tall textarea can already contain the visible caret: do not reveal its bottom.
        if (
          rectangle.height > window.innerHeight - 120 &&
          rectangle.top < window.innerHeight - 40 &&
          rectangle.bottom > 80
        )
          rectangle = undefined
      }
    } else if (saved.anchor && saved.focus) {
      const anchorIndex = editor.blocks.getBlockIndex(saved.anchor.block)
      const anchorBlock =
        anchorIndex === undefined || anchorIndex < 0
          ? undefined
          : editor.blocks.getBlockByIndex(anchorIndex)
      const anchorField = anchorBlock && fields(anchorBlock.holder)[saved.anchor.field]
      const focusField = fields(block.holder)[saved.focus.field]
      if (anchorField && focusField) {
        focusField.focus({ preventScroll: true })
        const anchor = resolvePoint(anchorField, saved.anchor)
        const focus = resolvePoint(focusField, saved.focus)
        const selection = window.getSelection()!
        selection.setBaseAndExtent(anchor.node, anchor.offset, focus.node, focus.offset)
        const range = document.createRange()
        range.setStart(focus.node, focus.offset)
        range.collapse(true)
        rectangle = range.getBoundingClientRect?.()
      }
    }
  }
  const measuredScrollY = window.scrollY
  window.scrollTo({ left: scroll.x, top: scroll.y, behavior: 'instant' })
  if (rectangle && rectangle.height > 0) {
    // Re-measure after cancelling Editor.js' automatic scroll.
    const offset = measuredScrollY - scroll.y
    const top = rectangle.top + offset
    const bottom = rectangle.bottom + offset
    if (top < 80) window.scrollBy({ top: top - 80, behavior: 'instant' })
    else if (bottom > window.innerHeight - 40)
      window.scrollBy({
        top: bottom - window.innerHeight + 40,
        behavior: 'instant',
      })
  }
}
