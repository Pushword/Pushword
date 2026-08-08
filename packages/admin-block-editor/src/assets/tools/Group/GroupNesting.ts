import { GroupKind } from './GroupSyntax'

/** `<div …>` and `</div>` occurrences, in source order. */
const DIV_TAG = /<div\b|<\/div\b/gi

/**
 * What is still open while markdown chunks are classified in document order, so
 * markerhood stays symmetric: a closing chunk becomes a GroupEnd only when the
 * thing it closes was imported as a GroupStart of the same kind.
 *
 * Without it every `</div>` was a marker while `<div style="…">` — anything
 * richer than id/class — stayed Raw, so the pairing married a group's start to
 * the closer of a div the user had hand-written, and deleting the group took
 * that closer with it. The show-more markers are counted apart from the divs:
 * they are not tags, so a `</div>` must never close one, nor the reverse.
 */
export class GroupNesting {
  /** One entry per open `<div>`: true when a GroupStart opened it. */
  private readonly openDivs: boolean[] = []

  /** How many show-more blocks are open; they carry no ambiguity, only a count. */
  private openShowMores = 0

  /** A chunk imported as a GroupStart opens a marker group of that kind. */
  openGroup(kind: GroupKind): void {
    if ('div' === kind) {
      this.openDivs.push(true)

      return
    }

    this.openShowMores++
  }

  /** Close what a closing chunk ends; true when a marker of that kind opened it. */
  closeGroup(kind: GroupKind): boolean {
    if ('div' === kind) {
      return this.openDivs.pop() === true
    }

    if (0 === this.openShowMores) {
      return false
    }

    this.openShowMores--

    return true
  }

  /** Account for the divs a Raw chunk leaves open, or closes. */
  trackRaw(markdown: string): void {
    for (const [tag] of markdown.matchAll(DIV_TAG)) {
      if (tag.startsWith('</')) {
        this.openDivs.pop()
      } else {
        this.openDivs.push(false)
      }
    }
  }
}
