import type * as monaco from 'monaco-editor'
import type MonacoHelper from './../../../../../admin-monaco-editor/MonacoHelper'
import type { BlockToolAdapter } from '@editorjs/editorjs/types/tools/adapters/block-tool-adapter'

declare global {
  interface Window {
    pagesUriList?: string[]
    monaco?: typeof monaco
    pageMainContent?: string // set in editorjs_widget.html.twig
    pageLocale?: string // set in ./packages/admin/Resources/assets/admin.js on page init
    pageHost?: string // set in ./packages/admin/Resources/assets/admin.js on page init
    monacoHelper?: typeof MonacoHelper
    editorjsTools?: BlockToolAdapter[]
    editorjsConfig?: Record<string, any>
  }
}

export {}
