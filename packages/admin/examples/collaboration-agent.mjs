import * as Y from 'yjs'

const [, , editorUrl, line] = process.argv
const token = process.env.PUSHWORD_TOKEN
const address = editorUrl && new URL(editorUrl)
const room = address?.pathname.match(/^\/admin\/collaboration-poc\/([a-f0-9]{16})$/)?.[1]
if (!room || !line || !token) {
  throw new Error('Set PUSHWORD_TOKEN and pass the demo editor URL and the line to add.')
}

const url = new URL('/api/collaboration-poc/' + room + '/sync', address)
async function sync(after, update = null) {
  const response = await fetch(url, {
    method: 'POST',
    redirect: 'error',
    headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + token },
    body: JSON.stringify({ after, update }),
  })
  if (!response.ok) throw new Error('Sync returned HTTP ' + response.status)
  return response.json()
}

const doc = new Y.Doc()
const text = doc.getText('body')
const current = await sync(0)
for (const update of current.updates) Y.applyUpdate(doc, Buffer.from(update, 'base64'))
const vector = Y.encodeStateVector(doc)
text.insert(text.length, (text.length ? '\n' : '') + line)
const update = Buffer.from(Y.encodeStateAsUpdate(doc, vector)).toString('base64')
const saved = await sync(current.after, update)
for (const incoming of saved.updates) Y.applyUpdate(doc, Buffer.from(incoming, 'base64'))
console.log(text.toString())
doc.destroy()
