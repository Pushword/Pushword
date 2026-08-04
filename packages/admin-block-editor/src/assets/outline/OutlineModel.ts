import { GroupRegistry } from '../tools/Group/GroupRegistry'

/**
 * The outline tree the left panel renders: one derivation shared by every
 * producer (EditorJS block list, Monaco markdown chunks, Monaco JSON blocks).
 *
 * Containers are group marker pairs and header sections. Groups pair by
 * document-order stack matching — GroupRegistry.computePairs itself, so the
 * outline and the editor can never disagree. A matched groupEnd is absorbed
 * into its group node; unmatched markers stay leaf entries, exactly as the
 * registry keeps them. A header owns the following siblings until the next
 * header of same-or-higher level in the SAME container: a group is atomic at
 * the level where it starts, so spans always nest and moving one can never
 * tear a marker pair apart.
 */

export interface OutlineEntry {
  /** Position of the unit in its source: block index, or markdown chunk index. */
  index: number
  /** Tool name (EditorJS block) or the tool a markdown chunk belongs to. */
  type: string
  label: string
  /** Header level; null for any other type — it is what makes an entry a header. */
  level: number | null
}

export interface OutlineNode {
  entry: OutlineEntry
  children: OutlineNode[]
  /**
   * Last unit index the node owns, inclusive: a group's end marker, a header
   * section's last descendant, the entry's own index for leaves.
   */
  spanEnd: number
}

/** What the panel needs from an editing surface; one implementation per mode. */
export interface OutlineSource {
  entries(): OutlineEntry[]
  navigateTo(entry: OutlineEntry): void
  deleteSpan(start: number, end: number): void
  /** Move units start..end (inclusive) so they land before the unit at `to`. */
  moveSpan(start: number, end: number, to: number): void
  /** Start pushing change notifications, for sources the panel cannot observe otherwise. */
  bind?(onChange: () => void): void
  dispose?(): void
}

export function buildOutlineTree(entries: OutlineEntry[]): OutlineNode[] {
  return buildRange(entries, 0, entries.length, groupEndByStart(entries))
}

/** Positions of matched group markers, keyed start → end. */
function groupEndByStart(entries: OutlineEntry[]): Map<number, number> {
  const markers: { id: string; name: string }[] = []
  entries.forEach((entry, position) => {
    if (entry.type === GroupRegistry.START || entry.type === GroupRegistry.END) {
      markers.push({ id: String(position), name: entry.type })
    }
  })

  const pairs = GroupRegistry.computePairs(markers)
  const result = new Map<number, number>()
  entries.forEach((entry, position) => {
    if (entry.type !== GroupRegistry.START) return
    const endPosition = pairs.get(String(position))
    if (endPosition !== undefined) result.set(position, Number(endPosition))
  })

  return result
}

function buildRange(
  entries: OutlineEntry[],
  from: number,
  to: number,
  endOfStart: Map<number, number>,
): OutlineNode[] {
  // Pass 1 — atoms: leaves, and each matched group recursed into one node.
  const atoms: OutlineNode[] = []
  let position = from
  while (position < to) {
    const entry = entries[position]
    if (entry === undefined) break

    const endPosition = endOfStart.get(position)
    if (endPosition !== undefined) {
      atoms.push({
        entry,
        children: buildRange(entries, position + 1, endPosition, endOfStart),
        spanEnd: entries[endPosition]?.index ?? endPosition,
      })
      position = endPosition + 1
    } else {
      atoms.push({ entry, children: [], spanEnd: entry.index })
      position++
    }
  }

  return foldHeaders(atoms)
}

/**
 * Pass 2 — fold a sibling list: a header adopts following siblings until the
 * next header of same-or-higher level. Only headers enter the stack, so a
 * group's interior (already folded by recursion) is never re-parented.
 */
function foldHeaders(atoms: OutlineNode[]): OutlineNode[] {
  const result: OutlineNode[] = []
  const openHeaders: OutlineNode[] = []

  for (const atom of atoms) {
    const level = atom.entry.level
    if (level !== null) {
      let top = openHeaders[openHeaders.length - 1]
      while (top !== undefined && top.entry.level !== null && top.entry.level >= level) {
        openHeaders.pop()
        top = openHeaders[openHeaders.length - 1]
      }
    }

    const parent = openHeaders[openHeaders.length - 1]
    if (parent === undefined) {
      result.push(atom)
    } else {
      parent.children.push(atom)
    }
    if (level !== null) openHeaders.push(atom)
  }

  for (const node of result) closeSpan(node)
  return result
}

function closeSpan(node: OutlineNode): number {
  let end = node.spanEnd
  for (const child of node.children) {
    end = Math.max(end, closeSpan(child))
  }
  node.spanEnd = end
  return end
}
