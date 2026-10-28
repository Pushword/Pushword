import type * as monaco from 'monaco-editor'
import type MonacoHelper from './../../../../../admin-monaco-editor/MonacoHelper'
import type { BlockToolAdapter } from '@editorjs/editorjs/types/tools/adapters/block-tool-adapter'
import type { EditorJSConfig } from '../../editor'

declare global {
  /**
   * Write seam a rich editor attaches to the form field it owns, so code outside
   * can set the value and see the rendered editor follow. Read by the unsaved
   * changes recovery in pushword/admin; MonacoHelper implements it too.
   */
  interface HTMLElement {
    pwEditor?: { setValue: (value: string) => void }
  }

  interface Window {
    initContextHelp?: (root?: ParentNode) => void
    pagesUriList?: string[]
    monaco?: typeof monaco
    pageHost?: string // set in ./packages/admin/Resources/assets/admin.js on page init
    monacoHelper?: typeof MonacoHelper
    pwMonacoUrl?: string // set in Pushword\Admin\Controller\DashboardController
    pwMonacoLoading?: Promise<boolean> | null // shared with admin.monacoLoader.js
    editorjsTools?: BlockToolAdapter[]
    editorjsConfig?: Record<string, any>
    editorjsConfigs?: Record<string, EditorJSConfig>
  }
}

export {}
