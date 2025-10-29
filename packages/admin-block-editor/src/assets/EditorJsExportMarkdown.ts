import { BlockToolAdapter } from '@editorjs/editorjs/types/tools/adapters/block-tool-adapter'
import { OutputData, API } from '@editorjs/editorjs'
import { ToolInterface } from './tools/Abstract/ToolInterface'

// Extended BlockToolAdapter to access the constructable property
interface BlockToolAdapterWithConstructable extends BlockToolAdapter {
  constructable?: ToolInterface
}

export class EditorJsExportMarkdown {
  private editorjsTools: ToolInterface[]
  private editorData: OutputData

  constructor(editorJsInstance: API, editorData: OutputData) {
    // @ts-ignore TODO : to remove when ToolInterface is compatible with BlockToolConstructable
    this.editorjsTools = editorJsInstance.tools.getBlockTools()
    this.editorData = editorData
  }

  /**
   * Export EditorJS content to Markdown
   * @returns Markdown content
   */
  async exportToMarkdown(): Promise<string> {
    if (
      !this.editorData ||
      !this.editorData.blocks ||
      !Array.isArray(this.editorData.blocks)
    ) {
      return ''
    }

    const markdownBlocks = await Promise.all(
      this.editorData.blocks.map(async (block) => {
        const toolClass = this.getToolClass(block.type)
        // @ts-ignore
        return await toolClass.exportToMarkdown(block.data, block.tunes)
      }),
    ).then((blocks) => blocks.filter((content) => content !== ''))

    return markdownBlocks.join('\n\n')
  }

  private getToolClass(blockName: string): ToolInterface {
    const tool = this.editorjsTools.find((t) => t.name === blockName) as
      | BlockToolAdapterWithConstructable
      | undefined

    if (!tool || !tool.constructable) {
      throw new Error(`Tool class for block ${blockName} not found`)
    }
    return tool.constructable
  }
}

export default EditorJsExportMarkdown
