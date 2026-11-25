import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { isDeepStrictEqual } from 'node:util'
import * as Y from 'yjs'
import { carouselModel, markdownModel, seedDraft, textChange } from './admin.draftSession.js'

function apply(doc, snapshot) {
  Y.applyUpdate(doc, Buffer.from(snapshot, 'base64'))
  if (doc.store.pendingStructs || doc.store.pendingDs) throw new Error('Incomplete Yjs state')
}

function normalizeMarkdown(text, expected) {
  if (text.toString() === expected) return
  // Apply Page::normalizeMainContent in place to keep the IDs between edits.
  for (const [pattern, replace] of [
    [/^[ \n\r\t\v\0]+|[ \n\r\t\v\0]+$/g, ''],
    [/\r\n?/g, '\n'],
    [/<a[^>]+"><\/a>/g, ''],
  ]) {
    let shift = 0
    for (const match of text.toString().matchAll(pattern)) {
      const index = match.index + shift
      text.delete(index, match[0].length)
      if (replace) text.insert(index, replace)
      shift += replace.length - match[0].length
    }
  }
  if (text.toString() !== expected) throw new Error('Unexpected canonical Markdown')
}

function patchMarkdown(doc, baseline, { replacements, content, normalized }) {
  const text = doc.getText('body')
  doc.transact(() => {
    normalizeMarkdown(text, baseline)
    for (const { find, replace } of replacements) {
      const original = text.toString()
      const { from, to, insert } = textChange(find, replace)
      const removed = to - from
      let shift = 0
      for (let offset = original.indexOf(find); offset !== -1; offset = original.indexOf(find, offset + find.length)) {
        const index = offset + shift + from
        if (removed) text.delete(index, removed)
        if (insert) text.insert(index, insert)
        shift += insert.length - removed
      }
    }
    if (text.toString() !== content) throw new Error('Unexpected Markdown projection')
    normalizeMarkdown(text, normalized)
  })
}

export function project({ carousel, baseline, stored, incoming, content, legacy = [], patch }) {
  const model = carousel ? carouselModel : markdownModel
  const doc = new Y.Doc()
  try {
    if (stored) apply(doc, stored)
    else seedDraft(doc, model, baseline)

    if (patch) patchMarkdown(doc, baseline, patch)

    // An earlier WIP kept incremental updates on disk. They can depend on one
    // another, so check completeness only after the entire journal is applied.
    for (const update of legacy) Y.applyUpdate(doc, Buffer.from(update, 'base64'))
    if (doc.store.pendingStructs || doc.store.pendingDs) throw new Error('Incomplete legacy state')

    if (incoming) {
      apply(doc, incoming)
      const value = model.read(doc)
      if (carousel ? !isDeepStrictEqual(value, JSON.parse(content)) : value !== content) {
        return null
      }
    }

    const value = model.read(doc)
    return {
      snapshot: Buffer.from(Y.encodeStateAsUpdate(doc)).toString('base64'),
      content: carousel ? JSON.stringify(value) : value,
    }
  } catch {
    return null
  } finally {
    doc.destroy()
  }
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  const input = JSON.parse(readFileSync(0, 'utf8'))
  process.stdout.write(JSON.stringify(project(input)))
}
