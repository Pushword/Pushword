import { BlockToolAdapter } from '@editorjs/editorjs/types/tools/adapters/block-tool-adapter'
import { API } from '@editorjs/editorjs'
import { ToolInterface } from './tools/Abstract/ToolInterface'
import { MarkdownUtils } from './tools/utils/MarkdownUtils'

// Extended BlockToolAdapter to access the constructable property
interface BlockToolAdapterWithConstructable extends BlockToolAdapter {
  constructable?: ToolInterface
}

export class EditorJsParseMarkdown {
  private editorjsTools: ToolInterface[]
  private editorJsInstance: API
  private markdown: string

  constructor(editorJsInstance: API, markdown: string) {
    this.editorJsInstance = editorJsInstance
    // @ts-ignore because
    this.editorjsTools = editorJsInstance.tools.getBlockTools() || []
    this.markdown = markdown
  }

  private importBlockWithTool(markdownBlock: string, toolClass: ToolInterface): boolean {
    if (typeof toolClass.isItMarkdownExported !== 'function') {
      console.error('isItMarkdownExported is not available for ', toolClass)
      return false
    }
    const markdownBlockWithoutTunes =
      MarkdownUtils.retrieveMarkdownWithoutTunes(markdownBlock)
    if (!toolClass.isItMarkdownExported(markdownBlockWithoutTunes)) return false

    toolClass.importFromMarkdown(this.editorJsInstance, markdownBlock)
    return true
  }

  private importBlock(block: string): void {
    for (const tool of this.editorjsTools) {
      if (['paragraph', 'raw', 'stub'].includes(tool.name)) continue
      const toolClass = tool.constructable
      if (this.importBlockWithTool(block, toolClass)) return
    }

    const paragraphTool = this.getToolClass('paragraph')
    if (this.importBlockWithTool(block, paragraphTool)) return

    const rawTool = this.getToolClass('raw')
    if (this.importBlockWithTool(block, rawTool)) return

    //fallbackType.importFromMarkdown(this.editorJsInstance, block)
  }

  parseMarkdown(): void {
    this.editorJsInstance.blocks.clear()
    //const blockToDelete = this.editorJsInstance.blocks.getCurrentBlockIndex()

    if (!this.markdown || this.markdown.trim() === '') {
      return
    }

    this.markdown = this.markdown.replace(/\n\s*\n+/g, '\n\n')
    const blocks = this.markdown.split('\n\n')

    for (const block of blocks) {
      this.importBlock(block)
    }
  }

  private getToolClass(blockType: string): ToolInterface {
    const tool = this.editorjsTools.find((t) => t.name === blockType) as
      | BlockToolAdapterWithConstructable
      | undefined

    if (!tool || !tool.constructable) {
      throw new Error(`Tool class for block type ${blockType} not found`)
    }
    return tool.constructable
  }
}

export default EditorJsParseMarkdown
