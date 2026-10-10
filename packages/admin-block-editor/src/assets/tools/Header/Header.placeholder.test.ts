import { expect, it } from 'vitest'
import type { API } from '@editorjs/editorjs'
import Header from './Header'

it('renders an empty heading without requiring a placeholder configuration', () => {
  const root = new Header({
    data: { text: '', level: 2 },
    api: {} as API,
    config: {},
    readOnly: false,
  }).render()

  expect(root.querySelector('h2')?.dataset.placeholder).toBe('')
})
