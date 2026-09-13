import { describe, expect, it } from 'vitest'
import type { API } from '@editorjs/editorjs'
import Snippet, { type SnippetConfig } from './Snippet'

describe('Snippet field IDs', () => {
  it('keeps labels unique across blocks with the same schema', () => {
    const api = { i18n: { t: (key: string) => key } } as API
    const config: SnippetConfig = {
      definitions: { hero: { label: 'Hero', schema: { title: { type: 'string' } } } },
    }
    const create = () =>
      new Snippet({
        data: { name: 'hero', params: {} },
        api,
        readOnly: false,
        config,
      })

    const firstId = create().createInputs().querySelector('input')?.id
    const secondId = create().createInputs().querySelector('input')?.id

    expect(firstId).toBeTruthy()
    expect(secondId).toBeTruthy()
    expect(firstId).not.toBe(secondId)
  })
})
