import ajax from '@codexteam/ajax'
import { EditorModeManager } from './EditorModeManager'

interface ToolWithCallbacks {
  onFileLoading?: () => void
  onUpload: (response: any) => void
  handleUploadError: (error: any) => void
}

export class editorJsHelper {
  private static modeManagers: Record<string, EditorModeManager> = {}
  private static pickerChangeHandlers = new WeakMap<Element, EventListener>()
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

  private static registerPickerChangeHandler(
    select: HTMLSelectElement,
    handler: EventListener,
  ): void {
    const previousHandler = this.pickerChangeHandlers.get(select)
    if (previousHandler) {
      select.removeEventListener('change', previousHandler)
    }

    const wrappedHandler: EventListener = (event) => {
      handler(event)
      select.removeEventListener('change', wrappedHandler)
      this.pickerChangeHandlers.delete(select)
    }

    select.addEventListener('change', wrappedHandler)
    this.pickerChangeHandlers.set(select, wrappedHandler)
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

    const selectElement = document.querySelector(
      'select' + inlineImageFieldSelector,
    ) as HTMLSelectElement | null

    if (!selectElement) {
      console.error(
        'select element not found with selector:',
        'select' + inlineImageFieldSelector,
      )
      return
    }

    const pickerWrapper = selectElement.closest('.pw-media-picker') as HTMLElement | null

    if (!pickerWrapper) {
      console.error('media picker wrapper not found for selector:', selectElement.id)
      return
    }

    const actionButton = pickerWrapper.querySelector(
      action === 'select'
        ? '[data-pw-media-picker-action="choose"]'
        : '[data-pw-media-picker-action="upload"]',
    ) as HTMLButtonElement | null

    if (!actionButton) {
      console.error('media picker action button not found', { action, selectId: selectElement.id })
      return
    }

    // Listen for postMessage from iframe instead of select change
    const messageHandler = (event: MessageEvent) => {
      if (event.origin !== window.location.origin) return
      const payload = event.data
      if (!payload || payload.type !== 'pw-media-picker-select') {
        return
      }

      const { fieldId, media } = payload
      if (!fieldId || !media || fieldId !== selectElement.id) {
        return
      }

      // Remove listener after receiving message
      window.removeEventListener('message', messageHandler)

      // Format response to match expected format from /admin/media/block
      // The 'media' field should be the fileName (used as identifier)
      const response = {
        success: 1,
        file: {
          media: media.fileName || String(media.id),
          name: media.alt || media.name || media.fileName || '',
          url: media.thumb || '',
          fileName: media.fileName || String(media.id),
          alt: media.alt || '',
          width: media.width || '',
          height: media.height || '',
        },
      }

      if (Tool.onFileLoading) Tool.onFileLoading()
      Tool.onUpload(response)
    }

    // Register message listener before opening modal
    window.addEventListener('message', messageHandler, { once: false })

    // Open the media picker modal (iframe)
    actionButton.click()
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
