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
