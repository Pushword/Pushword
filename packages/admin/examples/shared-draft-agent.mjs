import * as Y from 'yjs'
import { carouselModel, markdownModel, seedDraft } from '../src/Resources/assets/admin.draftSession.js'

const [address, search, replacement, field] = process.argv.slice(2)
const token = process.env.PUSHWORD_TOKEN
if (!address || !token || (search !== undefined && (!search || replacement === undefined))) {
  throw new Error('Set PUSHWORD_TOKEN, then pass the draft API URL and optionally SEARCH REPLACEMENT [carousel JSON pointer].')
}

const url = new URL(address)
const carousel = /^\/api\/repurpose\/\d+\/draft$/.test(url.pathname)
const model = carousel ? carouselModel : markdownModel
const doc = new Y.Doc()
let room = null
let after = 0

async function sync(write = false) {
  const value = model.read(doc)
  const response = await fetch(url, {
    method: 'POST',
    redirect: 'error',
    headers: {
      'Content-Type': 'application/json',
      Authorization: 'Bearer ' + token,
    },
    body: JSON.stringify({
      room,
      after,
      ...(write ? {
        snapshot: Buffer.from(Y.encodeStateAsUpdate(doc)).toString('base64'),
        content: carousel ? JSON.stringify(value) : value,
      } : {}),
    }),
  })
  if (!response.ok) throw new Error('Live editing returned HTTP ' + response.status)
  const result = await response.json()
  if (room === null && !result.snapshot) seedDraft(doc, model, result.baseline)
  if (result.snapshot) Y.applyUpdate(doc, Buffer.from(result.snapshot, 'base64'))
  for (const update of result.legacyUpdates ?? []) Y.applyUpdate(doc, Buffer.from(update, 'base64'))
  room = result.room
  after = result.after
  return result.accepted
}

function replace(text) {
  if (typeof text !== 'string' || text.indexOf(search) < 0 || text.indexOf(search) !== text.lastIndexOf(search)) {
    throw new Error('The search text must occur exactly once in the chosen field.')
  }
  return text.replace(search, () => replacement)
}

await sync()
if (search !== undefined) {
  const previous = model.read(doc)
  if (carousel) {
    if (!field?.startsWith('/')) throw new Error('A carousel edit requires a JSON pointer such as /slides/0/title.')
    const next = structuredClone(previous)
    const path = field.slice(1).split('/').map((part) => part.replace(/~1/g, '/').replace(/~0/g, '~'))
    const key = path.pop()
    const target = path.reduce((parent, name) => parent && Object.hasOwn(parent, name) ? parent[name] : undefined, next)
    if (!target || !Object.hasOwn(target, key)) throw new Error('No field at ' + field)
    target[key] = replace(target[key])
    doc.transact(() => model.write(doc, previous, next))
  } else {
    doc.transact(() => model.write(doc, previous, replace(previous)))
  }
  let saved = false
  for (let attempt = 0; attempt < 5 && !saved; attempt++) saved = await sync(true)
  if (!saved) throw new Error('Could not save the edit while other participants were writing.')
}

const draft = model.read(doc)
console.log(carousel ? JSON.stringify(draft, null, 2) : draft)
doc.destroy()
