// import ajax from '@codexteam/ajax'
import { EditorModeManager } from './EditorModeManager'
import {
  openMediaPicker,
  type PickedMedia,
  pickedMediaName,
  pickFile,
} from './tools/utils/media'

interface ToolWithCallbacks {
  onFileLoading?: () => void
  onUpload: (response: any) => void
  handleUploadError: (error: any) => void
}

interface PickedFile {
  media: string
  name: string
  url: string
}

interface ToolWithMultiCallbacks {
  onMultiUpload: (items: PickedFile[]) => void
}

interface ToolWithInlineUpload {
  uploadFile: (file: File) => Promise<void>
  uploadAccept: string
}

/** What a block takes from a picked media, whether picked alone or among several. */
function pickedFile(media: PickedMedia): PickedFile {
  return {
    media: pickedMediaName(media),
    name: media.alt || media.name || media.fileName || '',
    url: media.thumb || '',
  }
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
    openMediaPicker({
      field: inlineImageFieldSelector,
      action: action === 'select' ? 'choose' : 'upload',
      onPick: (media) => {
        // Format response to match expected format from /admin/media/block
        // The 'media' field should be the fileName (used as identifier)
        const response = {
          success: 1,
          file: {
            ...pickedFile(media),
            fileName: pickedMediaName(media),
            alt: media.alt || '',
            width: media.width || '',
            height: media.height || '',
          },
        }

        if (Tool.onFileLoading) Tool.onFileLoading()
        Tool.onUpload(response)
      },
    })
  }

  static abstractOnMulti(
    Tool: ToolWithMultiCallbacks,
    _event: Event,
    inlineImageFieldSelector: string = '[id*="inline_image"]',
  ): void {
    openMediaPicker({
      field: inlineImageFieldSelector,
      multi: true,
      onPick: (items) => Tool.onMultiUpload(items.map(pickedFile)),
    })
  }

  /**
   * Pick a file from the device and upload it right away.
   *
   * The media picker's upload button opens the media form in a modal; a block
   * carries its own caption, which becomes the media's alt on render, so that
   * form has nothing left to ask that the block does not already hold.
   */
  static uploadInline(Tool: ToolWithInlineUpload): void {
    pickFile(Tool.uploadAccept, (file) => void Tool.uploadFile(file))
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
}
