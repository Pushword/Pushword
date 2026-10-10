import { afterEach, describe, expect, it } from 'vitest'
import type { API } from '@editorjs/editorjs'
import { StateBlock, StateBlockToolInterface } from './StateBlock'

function stateBlockTool(): StateBlockToolInterface {
  return {
    nodes: {},
    api: { styles: { block: 'cdx-block' } } as unknown as API,
    createInputs: () => document.createElement('div'),
    validate: () => true,
    save: () => ({}),
    updatePreview: () => {},
  }
}

describe('StateBlock edit toggle', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('binds each block label to its own toggle, so clicking one leaves the others alone', () => {
    const first = stateBlockTool()
    const second = stateBlockTool()
    document.body.append(StateBlock.render(first), StateBlock.render(second))

    const [firstInput, secondInput] = [first.nodes.editInput!, second.nodes.editInput!]
    expect(firstInput.id).not.toBe(secondInput.id)
    for (const input of [firstInput, secondInput]) {
      expect((input.nextElementSibling as HTMLLabelElement).htmlFor).toBe(input.id)
    }

    // A valid block opens in view mode, toggle checked; its label flips it to edit.
    ;(secondInput.nextElementSibling as HTMLElement).click()
    expect(secondInput.checked).toBe(false)
    expect(firstInput.checked).toBe(true)
  })
})
