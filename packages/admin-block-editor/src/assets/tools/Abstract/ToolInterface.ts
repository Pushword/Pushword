import {
  BlockToolData,
  API,
  BlockToolConstructorOptions,
  BlockTool,
} from '@editorjs/editorjs' // BlockTool, BlockToolConstructable
import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
// import { BlockToolAdapter } from '@editorjs/editorjs/types/tools/adapters/block-tool-adapter'

export interface ToolInterface {
  new (config: BlockToolConstructorOptions<any, any>): BlockTool
  // disabled because of not compatible signature between BlockToolConstructable and BlockToolAdapter
  // extends BlockTool, BlockToolConstructable, BlockToolAdapter
  exportToMarkdown(data: BlockToolData, tunes: BlockTuneData): string
  importFromMarkdown(editor: API, markdown: string): BlockToolData
  isItMarkdownExported(markdown: string): boolean
  constructable: ToolInterface
}
