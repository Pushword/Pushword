import { API, BlockToolData } from '@editorjs/editorjs'
import make from './make'

const BLOCK_STATE = {
  EDIT: 0,
  VIEW: 1,
}

export interface StateBlockToolInterface {
  nodes: {
    preview?: HTMLElement
    inputs?: HTMLElement
    editBtn?: HTMLElement
    editInput?: HTMLInputElement
    wrapper?: HTMLElement
  }
  createInputs(): HTMLElement
  api: API
  validate(): boolean
  /** Shown, through `api.i18n.t()`, when validate() refuses the switch to preview. */
  incompleteMessage: string
  save(): BlockToolData
  updatePreview(): void
}

export class StateBlock {
  private static showEditBtn(
    BlockTool: StateBlockToolInterface,
    state: (typeof BLOCK_STATE)[keyof typeof BLOCK_STATE] = BLOCK_STATE.VIEW,
  ): void {
    if (BlockTool.nodes.editBtn === undefined) {
      //StateBlock.createEditBtn(BlockTool)
      throw new Error('must createEditBtn before')
    }

    BlockTool.nodes.editInput!.checked = state === BLOCK_STATE.VIEW ? true : false
  }

  private static createEditBtn(BlockTool: StateBlockToolInterface): HTMLElement {
    const toggleId = make.uniqueId('toggle')
    BlockTool.nodes.editBtn = make.element('div', 'toggle-wrapper')
    BlockTool.nodes.editInput = make.element('input', ['toggle-input'], {
      type: 'checkbox',
      id: toggleId,
    }) as HTMLInputElement
    const label = make.element('label', ['toggle-label'], {
      for: toggleId,
    })

    BlockTool.nodes.editBtn!.appendChild(BlockTool.nodes.editInput!)
    BlockTool.nodes.editBtn!.appendChild(label)

    return BlockTool.nodes.editBtn!
  }

  private static show(BlockTool: StateBlockToolInterface, state: number): void {
    if (!BlockTool.nodes.preview) {
      BlockTool.nodes.preview = this.createPreview(BlockTool)
      if (BlockTool.validate()) BlockTool.updatePreview()
    }

    if (state === BLOCK_STATE.VIEW) {
      BlockTool.updatePreview()
      BlockTool.nodes.preview!.classList.remove('hidden')
      BlockTool.nodes.inputs!.classList.add('hidden')
      return this.showEditBtn(BlockTool)
    }

    BlockTool.nodes.preview!.classList.add('hidden')
    BlockTool.nodes.inputs!.classList.remove('hidden')
    this.showEditBtn(BlockTool, BLOCK_STATE.EDIT)
  }

  public static render(BlockTool: StateBlockToolInterface): HTMLElement {
    this.createEditBtn(BlockTool)
    BlockTool.nodes.wrapper = make.element('div', BlockTool.api.styles.block)
    BlockTool.nodes.preview = StateBlock.createPreview(BlockTool)
    BlockTool.updatePreview()
    BlockTool.nodes.wrapper.appendChild(BlockTool.nodes.preview!)

    BlockTool.nodes.wrapper.appendChild(BlockTool.nodes.editBtn!)

    BlockTool.nodes.inputs = BlockTool.createInputs()
    // Vérifier si createInputs() retourne le wrapper lui-même pour éviter la boucle DOM
    if (BlockTool.nodes.inputs !== BlockTool.nodes.wrapper) {
      BlockTool.nodes.wrapper.appendChild(BlockTool.nodes.inputs!)
    }

    if (BlockTool.validate()) {
      BlockTool.save()
      StateBlock.show(BlockTool, BLOCK_STATE.VIEW)
    } else {
      StateBlock.show(BlockTool, BLOCK_STATE.EDIT)
    }

    BlockTool.nodes.editInput!.addEventListener('change', () =>
      StateBlock.onEditInputChange(BlockTool),
    )

    return BlockTool.nodes.wrapper
  }

  private static onEditInputChange(BlockTool: StateBlockToolInterface): void {
    if (BlockTool.nodes.editInput!.checked) {
      BlockTool.save()
      if (BlockTool.validate()) return StateBlock.show(BlockTool, BLOCK_STATE.VIEW)
      // Nothing to preview yet: stay in edit mode and say what is missing.
      BlockTool.api.notifier.show({
        message: BlockTool.api.i18n.t(BlockTool.incompleteMessage),
        style: 'error',
      })
    }
    StateBlock.show(BlockTool, BLOCK_STATE.EDIT)
  }

  private static createPreview(BlockTool: StateBlockToolInterface): HTMLElement {
    const previewWrapper = make.element('div', ['hidden', 'preview-wrapper'])

    previewWrapper.onclick = () => {
      BlockTool.nodes.editInput!.checked = false
      StateBlock.show(BlockTool, BLOCK_STATE.EDIT)
    }

    return previewWrapper
  }
}
