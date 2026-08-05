// import ajax from '@codexteam/ajax'
import { EditorModeManager } from './EditorModeManager'
import { beginMediaPick } from './tools/utils/media'

interface ToolWithCallbacks {
  onFileLoading?: () => void
  onUpload: (response: any) => void
  handleUploadError: (error: any) => void
}

interface ToolWithMultiCallbacks {
  onMultiUpload: (items: Array<{ media: string; name: string; url: string }>) => void
}

interface ToolWithInlineUpload {
  uploadFile: (file: File) => Promise<void>
  uploadAccept: string
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
      console.error('media picker action button not found', {
        action,
        selectId: selectElement.id,
      })
      return
    }

    const pick = beginMediaPick()

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
      pick.abort()

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
    window.addEventListener('message', messageHandler, { signal: pick.signal })

    // Open the media picker modal (iframe)
    actionButton.click()
  }

  static abstractOnMulti(
    Tool: ToolWithMultiCallbacks,
    _event: Event,
    inlineImageFieldSelector: string = '[id*="inline_image"]',
  ): void {
    const selectElement = document.querySelector(
      'select' + inlineImageFieldSelector,
    ) as HTMLSelectElement | null

    if (!selectElement) {
      console.error('select element not found with selector:', 'select' + inlineImageFieldSelector)
      return
    }

    const pickerWrapper = selectElement.closest('.pw-media-picker') as HTMLElement | null
    if (!pickerWrapper) {
      console.error('media picker wrapper not found for selector:', selectElement.id)
      return
    }

    const actionButton = pickerWrapper.querySelector(
      '[data-pw-media-picker-action="choose"]',
    ) as HTMLButtonElement | null

    if (!actionButton) {
      console.error('media picker choose button not found', { selectId: selectElement.id })
      return
    }

    const pick = beginMediaPick()

    // Listen for multi-select postMessage
    const messageHandler = (event: MessageEvent) => {
      if (event.origin !== window.location.origin) return
      const payload = event.data
      if (!payload || payload.type !== 'pw-media-picker-multi-select') return

      const { fieldId, items } = payload
      if (!fieldId || !items || fieldId !== selectElement.id) return

      pick.abort()

      const mappedItems = items.map((media: any) => ({
        media: media.fileName || String(media.id),
        name: media.alt || media.name || media.fileName || '',
        url: media.thumb || '',
      }))

      Tool.onMultiUpload(mappedItems)
    }

    window.addEventListener('message', messageHandler, { signal: pick.signal })

    // Temporarily inject pwMediaPickerMulti=1 into the base URL so the
    // existing "choose" button opens the picker in multi-select mode.
    const urlKey = selectElement.dataset.pwMediaPickerModalUrl
      ? 'pwMediaPickerModalUrl'
      : 'pwAdminPopupModalUrl'
    const originalUrl = selectElement.dataset[urlKey] || ''

    try {
      const url = new URL(originalUrl, window.location.origin)
      url.searchParams.set('pwMediaPickerMulti', '1')
      selectElement.dataset[urlKey] = url.toString()
    } catch {
      // fallback: append as query string
      selectElement.dataset[urlKey] = originalUrl +
        (originalUrl.includes('?') ? '&' : '?') + 'pwMediaPickerMulti=1'
    }

    // Click the existing choose button (opens modal via admin mediaPicker)
    actionButton.click()

    // Restore original URL
    selectElement.dataset[urlKey] = originalUrl
  }

  /**
   * Pick a file from the device and upload it right away.
   *
   * The media picker's upload button opens the media form in a modal; a block
   * carries its own caption, which becomes the media's alt on render, so that
   * form has nothing left to ask that the block does not already hold.
   */
  static uploadInline(Tool: ToolWithInlineUpload): void {
    // Left out of the document on purpose: a dialog the editor cancels fires no
    // event, so an attached input would pile up one dead node per cancel.
    const input = document.createElement('input')
    input.type = 'file'
    if (Tool.uploadAccept) input.accept = Tool.uploadAccept

    input.addEventListener('change', () => {
      const file = input.files?.[0]
      if (file) void Tool.uploadFile(file)
    })

    input.click()
  }

  onUploadInline(Tool: ToolWithInlineUpload, _event: Event): void {
    editorJsHelper.uploadInline(Tool)
  }

  onSelectImage(Tool: ToolWithCallbacks, event: Event): void {
    editorJsHelper.abstractOn(Tool, event, 'select')
  }

  onSelectFile(Tool: ToolWithCallbacks, event: Event): void {
    editorJsHelper.abstractOn(Tool, event, 'select', '[id*="inline_attaches"]')
  }

  onUploadImage(Tool: ToolWithCallbacks, event: Event): void {
    editorJsHelper.abstractOn(Tool, event, 'upload')
  }

  onMultiSelectImage(Tool: ToolWithMultiCallbacks, _event: Event): void {
    editorJsHelper.abstractOnMulti(Tool, _event)
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
