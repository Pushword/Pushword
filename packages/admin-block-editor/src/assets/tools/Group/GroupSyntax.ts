/**
 * The markdown a group's boundaries are written in — recognised, parsed and
 * built here so the tools, the parser and the outline rail never disagree on
 * what a marker line is.
 *
 * A group wraps its blocks in one of two things: a plain `<div>`, or the
 * collapsible show-more block. The collapsible one has two spellings, and both
 * stay: `{{ startShowMore() }}` is what the editor writes, `<!--start-show-more-->`
 * is what bodies written before it hold, and rewriting those on open would
 * rewrite pages nobody edited.
 */

/** What the boundary wraps. Pairing is per kind: the two never close each other. */
export type GroupKind = 'div' | 'showMore'

/** How the boundary is spelled. A group re-exports the spelling it was read in. */
export type GroupSyntax = 'div' | 'twig' | 'comment'

/** A Twig scalar we are willing to read back: no variables, no expressions. */
const LITERAL = String.raw`(?:null|'[^']*'|"[^"]*")`

/** A literal, optionally named — `'mt-8'` or `showMoreExtraClass: 'mt-8'`. */
const ARGUMENT = String.raw`(?:([a-zA-Z_]\w*)\s*:\s*)?(${LITERAL})`

/** Up to two arguments, or none. */
const ARGUMENTS = String.raw`\s*(?:${ARGUMENT}(?:\s*,\s*${ARGUMENT})?\s*)?`

const twigCall = (name: string): RegExp =>
  new RegExp(String.raw`^\{\{\s*${name}\(${ARGUMENTS}\)\s*\}\}$`)

/** A lone `<div>` line whose only attributes are id/class; anything richer stays Raw. */
const DIV_START = /^<div(?:\s+(?:id|class)="[^"]*")*\s*>$/

const DIV_END = /^<\/div>$/

const COMMENT_START = /^<!--start-show-more-->$/

const COMMENT_END = /^<!--end-show-more-->$/

const TWIG_START = twigCall('startShowMore')

const TWIG_END = twigCall('endShowMore')

/** The spelling of an opening line, or null when no tool should claim it. */
export function startSyntax(markdown: string): GroupSyntax | null {
  const trimmed = markdown.trim()

  if (DIV_START.test(trimmed)) return 'div'
  if (COMMENT_START.test(trimmed)) return 'comment'
  if (TWIG_START.test(trimmed)) return 'twig'

  return null
}

/** The spelling of a closing line. Whether it really closes a group is GroupNesting's call. */
export function endSyntax(markdown: string): GroupSyntax | null {
  const trimmed = markdown.trim()

  if (DIV_END.test(trimmed)) return 'div'
  if (COMMENT_END.test(trimmed)) return 'comment'
  if (TWIG_END.test(trimmed)) return 'twig'

  return null
}

export function kindOf(syntax: GroupSyntax): GroupKind {
  return 'div' === syntax ? 'div' : 'showMore'
}

/** What a `{{ name(…) }}` call has between its parentheses, verbatim. */
function callBody(markdown: string): string {
  return (/\(([^)]*)\)/.exec(markdown.trim())?.[1] ?? '').trim()
}

/**
 * The arguments of a `{{ name(…) }}` call, placed in the order `parameters` declares
 * them: a named one goes to its own slot, an unnamed one to the next free slot, and a
 * slot nobody filled stays null — as does an explicit `null`.
 */
function callArguments(markdown: string, parameters: string[]): (string | null)[] {
  const values: (string | null)[] = parameters.map(() => null)
  let next = 0

  for (const [, name, literal] of callBody(markdown).matchAll(new RegExp(ARGUMENT, 'g'))) {
    const at = undefined === name ? next++ : parameters.indexOf(name)
    if (at < 0 || at >= values.length) continue

    values[at] = 'null' === literal ? null : literal.slice(1, -1)
  }

  return values
}

/** `startShowMore`'s own signature, so a named argument lands in the right slot. */
const START_PARAMETERS = ['id', 'showMoreExtraClass']

/** The anchor and the wrapper class an opening call carries. */
export function startCallArguments(markdown: string): (string | null)[] {
  return callArguments(markdown, START_PARAMETERS)
}

/**
 * `{{ startShowMore(…) }}` carrying what the group holds: its anchor becomes the
 * block's id, its class the wrapper's extra class. A class on its own is named
 * rather than passed after a `null` — the Twig signature reads `''` as a real, and
 * colliding, id.
 */
export function buildStartCall(anchor: string, className: string): string {
  if ('' === className) {
    return '' === anchor ? '{{ startShowMore() }}' : `{{ startShowMore('${anchor}') }}`
  }

  if ('' === anchor) {
    return `{{ startShowMore(showMoreExtraClass: '${className}') }}`
  }

  return `{{ startShowMore('${anchor}', '${className}') }}`
}

/** `args` is what the author wrote, kept verbatim so a hand-set background survives. */
export function buildEndCall(args: string): string {
  return `{{ endShowMore(${args}) }}`
}

/** The arguments of an `{{ endShowMore(…) }}` line, verbatim, ready to be given back. */
export function endCallArguments(markdown: string): string {
  return callBody(markdown)
}
