import ajax from '@codexteam/ajax'
import { EditorModeManager } from './EditorModeManager'

interface ToolWithCallbacks {
  onFileLoading?: () => void
  onUpload: (response: any) => void
  handleUploadError: (error: any) => void
}

export class editorJsHelper {
  private static modeManagers: Record<string, EditorModeManager> = {}
  public modeManagers: Record<string, EditorModeManager> = {}

  constructor() {
    this.modeManagers = editorJsHelper.modeManagers
  }

  /**
   * Récupère le gestionnaire de modes pour un éditeur
   */
  static getModeManager(editorId: string): EditorModeManager | undefined {
    return this.modeManagers[editorId]
  }

  /**
   * Enregistre un gestionnaire de modes pour un éditeur
   */
  static setModeManager(editorId: string, modeManager: EditorModeManager): void {
    this.modeManagers[editorId] = modeManager
    // Synchroniser avec l'instance globale
    if (window.editorJsHelper) {
      window.editorJsHelper.modeManagers[editorId] = modeManager
    }
  }

  /**
   * @param Tool - Tool instance with callbacks
   * @param event - DOM event
   * @param action - Action type: 'select' or 'upload'
   * @param inlineImageFieldSelector - CSS selector for inline image field
   */
  static abstractOn(
    Tool: ToolWithCallbacks,
    _event: Event,
    action: 'select' | 'upload' = 'select',
    inlineImageFieldSelector: string = '[id*="inline_image"]',
  ): void {
    console.log('abstractOn called', { action, inlineImageFieldSelector })

    //
    // const buttonClicked = _event.target as HTMLElement
    // const originalTextContent = buttonClicked.textContent
    //buttonClicked.textContent = ". . . ";

    const inlineImageField = document.querySelector(
      'div' +
        inlineImageFieldSelector +
        ' ' +
        (action === 'select' ? 'a' : 'a:nth-child(2)'),
    ) as HTMLAnchorElement

    console.log('inlineImageField found:', inlineImageField)

    if (!inlineImageField) {
      console.error(
        'inlineImageField not found with selector:',
        'div' +
          inlineImageFieldSelector +
          ' ' +
          (action === 'select' ? 'a' : 'a:nth-child(2)'),
      )
      return
    }

    inlineImageField.click()

    const inputElement = document.querySelector(
      'input' + inlineImageFieldSelector,
    ) as HTMLInputElement

    console.log('inputElement found:', inputElement)

    if (!inputElement) {
      console.error(
        'inputElement not found with selector:',
        'input' + inlineImageFieldSelector,
      )
      return
    }

    inputElement.onchange = function () {
      console.log('inputElement onchange triggered')
      const id = (window as any).jQuery(this).val()
      console.log('jQuery value:', id)

      ajax
        .post({
          url: '/admin/media/block',
          data: Object.assign({
            id: id,
          }),
          type: ajax.contentType.JSON,
        })
        .then((response: any) => {
          console.log('AJAX response:', response)
          if (Tool.onFileLoading) Tool.onFileLoading()
          Tool.onUpload(response.body)
          //buttonClicked.textContent = originalTextContent;
        })
        .catch((error: any) => {
          console.log('AJAX error:', error)

          Tool.handleUploadError(error)
        })
    }
  }

  onSelectImage(Tool: ToolWithCallbacks, event: Event): void {
    editorJsHelper.abstractOn(Tool, event, 'select')
  }

  onSelectFile(Tool: ToolWithCallbacks, event: Event): void {
    console.log('editorJsHelper.onSelectFile called')
    editorJsHelper.abstractOn(Tool, event, 'select', '[id*="inline_attaches"]')
  }

  onUploadImage(Tool: ToolWithCallbacks, event: Event): void {
    editorJsHelper.abstractOn(Tool, event, 'upload')
  }

  onUploadFile(Tool: ToolWithCallbacks, event: Event): void {
    console.log('editorJsHelper.onUploadFile called')
    editorJsHelper.abstractOn(Tool, event, 'upload', '[id*="inline_attaches"]')
  }

  toggleEditorJs(editorId: string): void {
    const editorJsInput = document.querySelector(
      'input[data-editorjs]',
    ) as HTMLInputElement | null
    const textareaInput = document.querySelector(
      'textarea[data-editorjs]',
    ) as HTMLTextAreaElement | null
    const elementToReplace = editorJsInput ? editorJsInput : textareaInput

    if (!elementToReplace) return

    const editorElement = document.getElementById(editorId)
    if (editorElement) {
      editorElement.style.display = editorJsInput ? 'none' : 'block'
    }

    const replaceElement = document.createElement(
      editorJsInput ? 'textarea' : 'input',
    ) as HTMLInputElement | HTMLTextAreaElement

    for (let i = 0, l = elementToReplace.attributes.length; i < l; ++i) {
      const nodeName = elementToReplace.attributes.item(i)?.nodeName
      const nodeValue = elementToReplace.attributes.item(i)?.nodeValue

      if (nodeName && nodeValue) {
        replaceElement.setAttribute(nodeName, nodeValue)
      }
    }

    if (editorJsInput && replaceElement instanceof HTMLTextAreaElement) {
      replaceElement.innerHTML = editorJsInput.value
      replaceElement.classList.add('form-control')
      replaceElement.style.border = '0'
    }
    //else replaceElement.setAttribute("value", replaceElement.innerHTML); // useless because editor.js doesn't listen value content

    elementToReplace.parentNode?.replaceChild(replaceElement, elementToReplace)
  }
}
