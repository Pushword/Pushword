import { readFileSync } from 'node:fs'
import DOMPurify from 'dompurify'
import { expect, it } from 'vitest'

it('ships the installed DOMPurify instead of the copy vendored by Monaco', () => {
  const licenses = readFileSync(new URL('../admin/src/Resources/public/monaco/app.js.LICENSE.txt', import.meta.url), 'utf8')
  const versions = [...licenses.matchAll(/DOMPurify ([\d.]+)/g)].map(match => match[1])

  expect(new Set(versions)).toEqual(new Set([DOMPurify.version]))
})
