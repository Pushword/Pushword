import { afterEach, describe, expect, it, vi } from 'vitest'
import type { API } from '@editorjs/editorjs'
import { StateBlock, StateBlockToolInterface } from './StateBlock'
import Embed from '../Embed/Embed'
import PagesList, { PagesListConfig, PagesListData } from '../PagesList/PagesList'
import Snippet from '../Snippet/Snippet'
import { MediaToolConfig } from '../Abstract/AbstractMediaTool'

vi.mock('@codexteam/ajax', () => ({
  default: {
    post: () => Promise.resolve({ body: { content: '<p>pages</p>' } }),
    contentType: { JSON: 'application/json' },
  },
}))

function stateBlockTool(): StateBlockToolInterface {
  return {
    nodes: {},
    api: { styles: { block: 'cdx-block' } } as unknown as API,
    createInputs: () => document.createElement('div'),
    validate: () => true,
    incompleteMessage: 'Incomplete',
    save: () => ({}),
    updatePreview: () => {},
  }
}

/** Clicks the edit/preview toggle, as the editor does. */
function clickToggle(tool: StateBlockToolInterface): void {
  ;(tool.nodes.editInput!.nextElementSibling as HTMLElement).click()
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

  it('switches a previewed block back to its fields without judging it', () => {
    const notify = vi.fn()
    let valid = true
    const tool: StateBlockToolInterface = {
      ...stateBlockTool(),
      api: {
        styles: { block: 'cdx-block' },
        notifier: { show: notify },
      } as unknown as API,
      validate: () => valid,
    }
    document.body.append(StateBlock.render(tool))

    // Leaving the preview is always allowed, even for a block that is no longer complete.
    valid = false
    clickToggle(tool)

    expect(tool.nodes.preview!.classList.contains('hidden')).toBe(true)
    expect(tool.nodes.inputs!.classList.contains('hidden')).toBe(false)
    expect(notify).not.toHaveBeenCalled()
  })

  it('opens the fields when the preview itself is clicked', () => {
    const tool = stateBlockTool()
    document.body.append(StateBlock.render(tool))

    tool.nodes.preview!.click()

    expect(tool.nodes.editInput!.checked).toBe(false)
    expect(tool.nodes.preview!.classList.contains('hidden')).toBe(true)
    expect(tool.nodes.inputs!.classList.contains('hidden')).toBe(false)
  })
})

type Tool = StateBlockToolInterface & { render(): HTMLElement }

/**
 * Each tool starts empty, as a block inserted from the toolbox does; `fill` then
 * completes it through its own fields, the way an editor would.
 */
const tools: {
  name: string
  message: string
  create: (api: API) => Tool
  fill: (tool: Tool) => void
}[] = [
  {
    name: 'Snippet',
    message: 'Choose a snippet first.',
    create: (api) =>
      new Snippet({
        data: { name: '', params: {} },
        api,
        readOnly: false,
        config: { definitions: { hero: { label: 'Hero', schema: {} } } },
      }),
    fill: (tool) => {
      const select = (tool as Snippet).nodes.nameSelect!
      select.value = 'hero'
      select.dispatchEvent(new Event('change'))
    },
  },
  {
    name: 'PagesList',
    message: 'Something is missing to properly render the the pages list.',
    create: (api) =>
      new PagesList({
        data: {} as PagesListData,
        api,
        readOnly: false,
        config: { preview: '/admin/page/block/1' } as PagesListConfig,
      }),
    fill: (tool) => {
      ;(tool as PagesList).nodes.kwInput!.textContent = 'children'
    },
  },
  {
    name: 'Embed',
    message: 'Something is missing to properly render the embeded video.',
    create: (api) =>
      new Embed({
        data: {},
        api,
        readOnly: false,
        config: {
          onSelectFile: vi.fn(),
          onUploadFile: vi.fn(),
        } as unknown as MediaToolConfig,
      }),
    fill: (tool) => {
      const embed = tool as Embed
      embed.nodes.inputServiceUrl.textContent = 'https://youtu.be/x'
      embed.onUpload({ success: true, file: { media: 'thumb.jpg', name: 'A video' } })
    },
  },
]

describe.each(tools)(
  'StateBlock switch to preview – $name',
  ({ message, create, fill }) => {
    afterEach(() => {
      document.body.innerHTML = ''
    })

    function renderEmpty() {
      const notify = vi.fn()
      const api = {
        styles: {
          block: 'ce-block',
          input: 'cdx-input',
          button: 'cdx-button',
          loader: 'loader',
        },
        i18n: { t: (key: string) => `[${key}]` },
        notifier: { show: notify },
      } as unknown as API
      const tool = create(api)
      document.body.append(tool.render())

      return { tool, notify }
    }

    function inPreview(tool: Tool): boolean {
      return !tool.nodes.preview!.classList.contains('hidden')
    }

    it('keeps an incomplete block in edit mode and says what is missing', () => {
      const { tool, notify } = renderEmpty()
      expect(inPreview(tool)).toBe(false)

      clickToggle(tool)

      expect(inPreview(tool)).toBe(false)
      expect(tool.nodes.inputs!.classList.contains('hidden')).toBe(false)
      expect(tool.nodes.editInput!.checked).toBe(false)
      expect(notify).toHaveBeenCalledWith({ message: `[${message}]`, style: 'error' })
    })

    it('previews the block once it is complete', () => {
      const { tool, notify } = renderEmpty()

      fill(tool)
      clickToggle(tool)

      expect(inPreview(tool)).toBe(true)
      expect(tool.nodes.inputs!.classList.contains('hidden')).toBe(true)
      expect(tool.nodes.editInput!.checked).toBe(true)
      expect(notify).not.toHaveBeenCalled()
    })
  },
)
