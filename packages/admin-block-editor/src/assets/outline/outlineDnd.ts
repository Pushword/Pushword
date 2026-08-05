import { OutlineNode } from './OutlineModel'

export interface DragSpan {
  start: number
  end: number
}

/** Unit index a drop above or below a row lands before. */
export function dropIndexFor(
  node: OutlineNode,
  below: boolean,
  childrenVisible: boolean,
): number {
  if (!below) return node.entry.index

  // Below a row whose children are rendered means "as its first child";
  // otherwise the whole (folded or leaf) span is skipped.
  return childrenVisible ? node.entry.index + 1 : node.spanEnd + 1
}

/** A span dropped into or right around itself is a no-op, not a move. */
export function isActualMove(span: DragSpan, to: number): boolean {
  return to < span.start || to > span.end + 1
}

export interface KeyboardMove {
  span: DragSpan
  up: boolean
  /** Moving a whole section or group, rather than the block alone. */
  wholeSpan: boolean
}

/**
 * Unit index an Alt+Arrow move lands before, null when nothing sits that way.
 * A lone block travels one rendered row — into the section opening right below
 * it, over a folded one; a whole span clears the neighbouring span instead, so
 * two sections swap rather than trade their bodies.
 */
export function keyboardDropIndex(
  tree: OutlineNode[],
  move: KeyboardMove,
  folded: ReadonlySet<number>,
): number | null {
  const { span, up, wholeSpan } = move

  if (up) {
    const above = wholeSpan
      ? spanAt(tree, span.start - 1)
      : rowAbove(tree, span.start - 1, folded)
    return above === null ? null : dropIndexFor(above, false, false)
  }

  const target = span.end + 1
  const below = spanAt(tree, target)
  if (below === null) return null

  const stepsIn =
    !wholeSpan &&
    below.entry.index === target &&
    below.children.length > 0 &&
    !folded.has(below.entry.index)
  return dropIndexFor(below, true, stepsIn)
}

/**
 * Outermost node opening or closing at a unit index: the span a whole-span move
 * has to clear, or the container it steps out of when the index is that
 * container's own head or its group end marker.
 */
function spanAt(nodes: OutlineNode[], index: number): OutlineNode | null {
  for (const node of nodes) {
    if (node.entry.index > index) break
    if (node.spanEnd < index) continue

    return node.entry.index === index || node.spanEnd === index
      ? node
      : spanAt(node.children, index)
  }

  return null
}

/** Last row the panel renders at or before a unit index — a folded section stands for its span. */
function rowAbove(
  nodes: OutlineNode[],
  index: number,
  folded: ReadonlySet<number>,
): OutlineNode | null {
  let last: OutlineNode | null = null
  for (const node of nodes) {
    if (node.entry.index > index) break
    last = folded.has(node.entry.index) ? node : (rowAbove(node.children, index, folded) ?? node)
  }

  return last
}
