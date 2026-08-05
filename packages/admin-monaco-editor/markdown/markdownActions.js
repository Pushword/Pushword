/**
 * Markdown editing primitives shared by the toolbar buttons and the keybindings.
 *
 * They take the document text plus a selection offset pair and return minimal
 * edits, so Monaco keeps a usable undo stack — rewriting the whole model per
 * action would collapse the document into a single undo step — and so the
 * behaviour is testable without Monaco or a DOM.
 *
 * Behaviours borrowed from yzhang-gh/vscode-markdown: an empty selection expands
 * to the word under the cursor, a second press removes the markers the first one
 * added, Enter carries the list marker over and clears it on an empty item.
 *
 * @typedef {{ start: number, end: number, text: string }} TextEdit
 * @typedef {{ edits: TextEdit[], selection: [number, number] }} ActionResult
 */

const WORD = /[\p{L}\p{N}_]/u
const INDENT = /^[ \t]*/
const HEADING = /^(#{1,6})[ \t]+/
const UL = /^([ \t]*)[-*+][ \t]+/
const OL = /^([ \t]*)\d+[.)][ \t]+/
const QUOTE = /^([ \t]*)>[ \t]?/
const TASK = /^[ \t]*[-*+][ \t]+\[([ xX])\][ \t]+/
const LIST_ITEM = /^([ \t]*)([-*+]|\d+[.)])([ \t]+)(\[[ xX]\][ \t]+)?(.*)$/
const BLOCKQUOTE_LINE = /^([ \t]*)>[ \t]?(.*)$/
const LINK_TARGET = /^(https?:\/\/|mailto:|#|\/)\S+$/i

const IMAGE_PLACEHOLDER = '/media/default/...'

/** Applies a result to text. Used by the tests and by nothing else — Monaco applies its own. */
export function applyEdits(text, result) {
  return [...result.edits]
    .sort((a, b) => b.start - a.start)
    .reduce(
      (acc, edit) => acc.slice(0, edit.start) + edit.text + acc.slice(edit.end),
      text,
    )
}

export function wordRangeAt(text, offset) {
  let start = offset
  let end = offset
  while (start > 0 && WORD.test(text.charAt(start - 1))) start--
  while (end < text.length && WORD.test(text.charAt(end))) end++

  return [start, end]
}

export function lineRangeAt(text, offset) {
  const start = text.lastIndexOf('\n', offset - 1) + 1
  const newline = text.indexOf('\n', offset)

  return [start, newline === -1 ? text.length : newline]
}

/**
 * Bounds of every line the selection touches. A selection ending right after a
 * newline stops on the previous line, the way a line-wise editor command does.
 */
export function selectedLinesRange(text, start, end) {
  const anchor = end > start ? end - 1 : end
  const newline = text.indexOf('\n', anchor)

  return [text.lastIndexOf('\n', start - 1) + 1, newline === -1 ? text.length : newline]
}

/**
 * `*` is both the italic marker and half of the bold one: only read a lone `*` as
 * italic when the character just outside it is not another `*`.
 *
 * @param openIndex  index of the opening marker's first character
 * @param closeIndex index of the closing marker's last character
 */
function starSafe(text, openIndex, closeIndex, marker) {
  if (marker !== '*') return true

  return text.charAt(openIndex - 1) !== '*' && text.charAt(closeIndex + 1) !== '*'
}

/** Wraps the selection in `marker`, or unwraps it when the markers are already there. */
export function toggleWrap(text, start, end, marker) {
  if (start === end) [start, end] = wordRangeAt(text, start)
  const size = marker.length

  // `**bold**` with the markers inside the selection
  if (
    end - start >= 2 * size &&
    text.slice(start, start + size) === marker &&
    text.slice(end - size, end) === marker &&
    starSafe(text, start, end - 1, marker)
  ) {
    return {
      edits: [
        { start: end - size, end, text: '' },
        { start, end: start + size, text: '' },
      ],
      selection: [start, end - 2 * size],
    }
  }

  // `**bold**` with only `bold` selected
  if (
    start >= size &&
    text.slice(start - size, start) === marker &&
    text.slice(end, end + size) === marker &&
    starSafe(text, start - size, end + size - 1, marker)
  ) {
    return {
      edits: [
        { start: end, end: end + size, text: '' },
        { start: start - size, end: start, text: '' },
      ],
      selection: [start - size, end - size],
    }
  }

  return {
    edits: [
      { start: end, end, text: marker },
      { start, end: start, text: marker },
    ],
    selection: [start + size, end + size],
  }
}

export function headingLevel(lineText) {
  const match = HEADING.exec(lineText)

  return match === null ? 0 : match[1].length
}

function applyHeading(text, offset, level) {
  const [lineStart, lineEnd] = lineRangeAt(text, offset)
  const match = HEADING.exec(text.slice(lineStart, lineEnd))
  const stripped = match === null ? 0 : match[0].length
  const prefix = level === 0 ? '' : `${'#'.repeat(level)} `
  const shift = prefix.length - stripped

  return {
    edits: [{ start: lineStart, end: lineStart + stripped, text: prefix }],
    selection: [
      Math.max(lineStart + prefix.length, offset + shift),
      Math.max(lineStart + prefix.length, offset + shift),
    ],
  }
}

/** Toolbar h2/h3: pressing the level a line already has strips the heading. */
export function toggleHeading(text, offset, level) {
  const [lineStart, lineEnd] = lineRangeAt(text, offset)
  const current = headingLevel(text.slice(lineStart, lineEnd))

  return applyHeading(text, offset, current === level ? 0 : level)
}

/** Ctrl+Shift+] / Ctrl+Shift+[ — walks the level up and down, 0 meaning a plain paragraph. */
export function shiftHeading(text, offset, delta) {
  const [lineStart, lineEnd] = lineRangeAt(text, offset)
  const current = headingLevel(text.slice(lineStart, lineEnd))
  const level = Math.min(6, Math.max(0, current + delta))

  return level === current ? null : applyHeading(text, offset, level)
}

function stripListMarker(body) {
  if (UL.test(body)) return body.replace(UL, '')
  if (OL.test(body)) return body.replace(OL, '')

  return body
}

function blockResult(text, blockStart, blockEnd, lines, nextLines, start, end) {
  const next = nextLines.join('\n')
  const firstShift = nextLines[0].length - lines[0].length
  const totalShift = next.length - (blockEnd - blockStart)

  return {
    edits: [{ start: blockStart, end: blockEnd, text: next }],
    selection: [
      Math.max(blockStart, start + firstShift),
      Math.max(blockStart, end + (start === end ? firstShift : totalShift)),
    ],
  }
}

/**
 * Adds or removes a line marker on every selected line. `ul` and `ol` replace one
 * another; `quote` sits in front of whatever marker is already there.
 *
 * @param {'ul'|'ol'|'quote'} kind
 */
export function toggleLinePrefix(text, start, end, kind) {
  const [blockStart, blockEnd] = selectedLinesRange(text, start, end)
  const lines = text.slice(blockStart, blockEnd).split('\n')
  const pattern = { ul: UL, ol: OL, quote: QUOTE }[kind]
  const filled = lines.filter((line) => line.trim() !== '')
  const remove = filled.length > 0 && filled.every((line) => pattern.test(line))

  let number = 0
  const nextLines = lines.map((line) => {
    if (remove) return line.replace(pattern, '$1')
    if (line.trim() === '' && lines.length > 1) return line

    const indent = INDENT.exec(line)[0]
    const body = line.slice(indent.length)
    number++

    if (kind === 'quote') return `${indent}> ${body}`

    return indent + (kind === 'ol' ? `${number}. ` : '- ') + stripListMarker(body)
  })

  return blockResult(text, blockStart, blockEnd, lines, nextLines, start, end)
}

/** Alt+C — flips `[ ]` and `[x]`, turning a plain line into a task item first. */
export function toggleTask(text, start, end) {
  const [blockStart, blockEnd] = selectedLinesRange(text, start, end)
  const lines = text.slice(blockStart, blockEnd).split('\n')

  const nextLines = lines.map((line) => {
    const task = TASK.exec(line)
    if (task !== null) {
      const checked = task[1] === ' ' ? '[x]' : '[ ]'

      return line.replace(/\[[ xX]\]/, checked)
    }

    const indent = INDENT.exec(line)[0]
    const body = line.slice(indent.length)

    return UL.test(body) ? indent + body.replace(UL, '- [ ] ') : `${indent}- [ ] ${body}`
  })

  return blockResult(text, blockStart, blockEnd, lines, nextLines, start, end)
}

/** Inline backticks on one line, a fenced block over several. */
export function toggleCode(text, start, end) {
  if (start === end || !text.slice(start, end).includes('\n')) {
    return toggleWrap(text, start, end, '`')
  }

  const [blockStart, blockEnd] = selectedLinesRange(text, start, end)
  const lines = text.slice(blockStart, blockEnd).split('\n')
  const fenced =
    /^```/.test(lines[0]) && lines.length > 1 && /^```\s*$/.test(lines.at(-1))
  const nextLines = fenced ? lines.slice(1, -1) : ['```', ...lines, '```']

  return blockResult(text, blockStart, blockEnd, lines, nextLines, start, end)
}

/** `[selection]()` with the caret parked between the parentheses. */
export function insertLink(text, start, end) {
  const inserted = `[${text.slice(start, end)}]()`
  const caret = start + inserted.length - 1

  return { edits: [{ start, end, text: inserted }], selection: [caret, caret] }
}

/** `![selection](/media/default/...)` with the placeholder selected, ready to be typed over. */
export function insertImage(text, start, end) {
  const label = text.slice(start, end)
  const urlStart = start + label.length + 4

  return {
    edits: [{ start, end, text: `![${label}](${IMAGE_PLACEHOLDER})` }],
    selection: [urlStart, urlStart + IMAGE_PLACEHOLDER.length],
  }
}

export function isLinkTarget(value) {
  return LINK_TARGET.test(value.trim())
}

/**
 * Pasting a URL over a selection turns it into a link instead of replacing the
 * text. Returns null when the paste should go through untouched.
 */
export function linkifyPaste(text, start, end, pasted) {
  const target = pasted.trim()
  if (start === end || !isLinkTarget(target)) return null

  const label = text.slice(start, end)
  if (label.includes('\n')) return null

  const inserted = `[${label}](${target})`

  return {
    edits: [{ start, end, text: inserted }],
    selection: [start + inserted.length, start + inserted.length],
  }
}

function insertion(offset, text) {
  const caret = offset + text.length

  return { edits: [{ start: offset, end: offset, text }], selection: [caret, caret] }
}

/**
 * What Enter should do inside a list or a blockquote. Returns null when Monaco's
 * own handling is right.
 */
export function computeEnter(text, offset) {
  const [lineStart, lineEnd] = lineRangeAt(text, offset)
  const line = text.slice(lineStart, lineEnd)
  const item = LIST_ITEM.exec(line)

  if (item === null) {
    const quote = BLOCKQUOTE_LINE.exec(line)
    if (quote === null || quote[2] === '') return null

    return insertion(offset, `\n${quote[1]}> `)
  }

  const [, indent, marker, space, checkbox, body] = item
  const markerEnd = lineStart + indent.length + marker.length + space.length
  if (offset < markerEnd) return null

  // An empty item ends the list rather than starting one more.
  if (body === '') {
    return {
      edits: [{ start: lineStart, end: lineEnd, text: '' }],
      selection: [lineStart, lineStart],
    }
  }

  const nextMarker = /^\d/.test(marker)
    ? `${Number.parseInt(marker, 10) + 1}${marker.slice(-1)}`
    : marker

  return insertion(
    offset,
    `\n${indent}${nextMarker}${space}${checkbox === undefined ? '' : '[ ] '}`,
  )
}
