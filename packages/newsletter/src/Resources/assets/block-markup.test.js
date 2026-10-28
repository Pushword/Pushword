import test from 'node:test'
import assert from 'node:assert/strict'
import { decodeBlock, encodeBlock } from './block-markup.js'

test('saved references, parameters, manual edits and Unicode round trip', () => {
  const block = { provider: 'catalogue', reference: '42', label: 'Été', parameters: { locale: 'fr' }, markdown: '## Edited\n\n[Book](https://example.test)\n\n```js\ncode\n```' }
  assert.deepEqual(decodeBlock(encodeBlock(block)), block)
  assert.equal(encodeBlock(decodeBlock(encodeBlock(block))), encodeBlock(block))
})

test('malformed and incomplete saved blocks are rejected instead of losing content', () => {
  for (const input of ['ordinary text', '```newsletter-block\n{}\n```', '```newsletter-block\nnull\n```']) {
    assert.throws(() => decodeBlock(input))
  }
})
