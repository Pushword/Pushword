import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { isDeepStrictEqual } from 'node:util'
import * as Y from 'yjs'
import { carouselModel, markdownModel, seedDraft } from './admin.draftSession.js'

function apply(doc, snapshot) {
  Y.applyUpdate(doc, Buffer.from(snapshot, 'base64'))
  if (doc.store.pendingStructs || doc.store.pendingDs) throw new Error('Incomplete Yjs state')
}

export function project({ carousel, baseline, stored, incoming, content, legacy = [] }) {
  const model = carousel ? carouselModel : markdownModel
  const doc = new Y.Doc()
  try {
    if (stored) apply(doc, stored)
    else seedDraft(doc, model, baseline)

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
