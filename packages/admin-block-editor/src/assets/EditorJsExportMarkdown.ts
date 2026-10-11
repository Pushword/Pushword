import { BlockToolAdapter } from '@editorjs/editorjs/types/tools/adapters/block-tool-adapter'
import { OutputData, API } from '@editorjs/editorjs'
import { ToolInterface } from './tools/Abstract/ToolInterface'
import { BlockSources } from './BlockSources'

// Extended BlockToolAdapter to access the constructable property
interface BlockToolAdapterWithConstructable extends BlockToolAdapter {
  constructable?: ToolInterface
}

interface CachedExport {
  key: string
  tool: ToolInterface
  markdown: Promise<string>
}

export class EditorJsExportMarkdown {
  private static readonly caches = new WeakMap<object, Map<string, CachedExport>>()
  private readonly cache: Map<string, CachedExport>
  private editorjsTools: ToolInterface[]
  private editorData: OutputData
  private sources: BlockSources | undefined

  constructor(editorJsInstance: API, editorData: OutputData) {
    // @ts-ignore TODO : to remove when ToolInterface is compatible with BlockToolConstructable
    this.editorjsTools = editorJsInstance.tools.getBlockTools()
    this.editorData = editorData
    this.sources = BlockSources.of(editorJsInstance)
    this.cache = EditorJsExportMarkdown.caches.get(editorJsInstance) ?? new Map()
    EditorJsExportMarkdown.caches.set(editorJsInstance, this.cache)
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

    const retained = new Set(this.editorData.blocks.map((block) => block.id))
    for (const id of this.cache.keys()) {
      if (!retained.has(id)) this.cache.delete(id)
    }

    const markdownBlocks = await Promise.all(
      this.editorData.blocks.map(async (block) => {
        const toolClass = this.getToolClass(block.type)
        const key = JSON.stringify([block.type, block.data, block.tunes])
        let cached = block.id === undefined ? undefined : this.cache.get(block.id)
        if (cached?.key !== key || cached.tool !== toolClass) {
          const markdown = Promise.resolve(
            // @ts-ignore
            toolClass.exportToMarkdown(block.data, block.tunes),
          )
          cached = { key, tool: toolClass, markdown }
          if (block.id !== undefined) this.cache.set(block.id, cached)
          const entry = cached
          void markdown.catch(() => {
            if (block.id !== undefined && this.cache.get(block.id) === entry)
              this.cache.delete(block.id)
          })
        }
        const markdown = await cached.markdown
        // Resolve on every export: a new parse can replace the preserved source.
        return this.sources?.resolve(block.id, markdown) ?? markdown
      }),
    ).then((blocks) => blocks.filter((content) => content !== ''))

    return markdownBlocks.join('\n\n')
  }

  private getToolClass(blockName: string): ToolInterface {
    const tool = this.editorjsTools.find((t) => t.name === blockName) as
      BlockToolAdapterWithConstructable | undefined

    if (!tool || !tool.constructable) {
      throw new Error(`Tool class for block ${blockName} not found`)
    }
    return tool.constructable
  }
}

export default EditorJsExportMarkdown
