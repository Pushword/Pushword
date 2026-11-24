import { readdirSync, readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import DOMPurify from 'dompurify'
import { expect, it } from 'vitest'

const outputDirectory = fileURLToPath(new URL('../admin/src/Resources/public/monaco/', import.meta.url))

it('ships the installed DOMPurify instead of the copy vendored by Monaco', () => {
  const licenses = readFileSync(new URL('../admin/src/Resources/public/monaco/app.js.LICENSE.txt', import.meta.url), 'utf8')
  const versions = [...licenses.matchAll(/DOMPurify ([\d.]+)/g)].map(match => match[1])

  expect(new Set(versions)).toEqual(new Set([DOMPurify.version]))
})

it('uses content-hashed names for lazy Monaco chunks', () => {
  const outputFiles = readdirSync(outputDirectory)
  const lazyChunkFiles = outputFiles.filter(file => file.endsWith('.app.js'))

  expect(outputFiles).toContain('app.js')
  expect(lazyChunkFiles.length).toBeGreaterThan(0)
  expect(lazyChunkFiles.every(file => /^\d+\.[a-f0-9]+\.app\.js$/.test(file))).toBe(true)
})
