// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest'

/** The candidate list each suggester was built with, in construction order. */
const { lists } = vi.hoisted(() => ({ lists: [] }))

vi.mock('../../src/Resources/assets/suggest.js', () => ({
  Suggest: {
    LocalMulti: class {
      constructor(input, suggester, list) {
        lists.push(list)
      }
    },
  },
}))

const { suggestTags } = await import('../../src/Resources/assets/admin.tagsField.js')

/** One tag input, with the suggester sibling suggestTags() looks for. */
function tagInput(attributes) {
  return `<div class="pw-m-tags"><input ${attributes}><div class="textSuggester"></div></div>`
}

beforeEach(() => {
  lists.length = 0
  document.body.innerHTML = ''
})

describe('suggestTags', () => {
  it('takes the candidate list from the nearest container when the input carries none', () => {
    document.body.innerHTML = `<div data-all-tags='["alpha","beta"]'>
      ${tagInput('data-tags')}${tagInput('data-tags')}
    </div>`

    suggestTags()

    // The point of the container attribute: two inputs, one copy of the list.
    expect(lists).toEqual([
      ['alpha', 'beta'],
      ['alpha', 'beta'],
    ])
  })

  it('keeps a list the input carries itself, container or not', () => {
    document.body.innerHTML = `<div data-all-tags='["shared"]'>
      ${tagInput(`data-tags='["own"]'`)}
    </div>`

    suggestTags()

    expect(lists).toEqual([['own']])
  })

  it('suggests nothing when neither the input nor an ancestor has a list', () => {
    document.body.innerHTML = tagInput('data-tags')

    suggestTags()

    expect(lists).toHaveLength(0)
  })
})
