import { BlockToolAdapter } from '@editorjs/editorjs/types/tools/adapters/block-tool-adapter'
import { API } from '@editorjs/editorjs'
import { ToolInterface } from './tools/Abstract/ToolInterface'
import { MarkdownUtils } from './tools/utils/MarkdownUtils'

// Extended BlockToolAdapter to access the constructable property
export interface BlockToolAdapterWithConstructable extends BlockToolAdapter {
  constructable?: ToolInterface
}

/**
 * The tool a markdown chunk belongs to, probed in the parser's own order —
 * shared with the outline panel so both always classify a chunk identically.
 */
export function chunkTool(
  blockTools: BlockToolAdapterWithConstructable[],
  markdownBlock: string,
): BlockToolAdapterWithConstructable | null {
  const stripped = MarkdownUtils.retrieveMarkdownWithoutTunes(markdownBlock)
  const claims = (adapter: BlockToolAdapterWithConstructable): boolean =>
    typeof adapter.constructable?.isItMarkdownExported === 'function' &&
    adapter.constructable.isItMarkdownExported(stripped)

  for (const adapter of blockTools) {
    if (['paragraph', 'raw', 'stub'].includes(adapter.name)) continue
    if (claims(adapter)) return adapter
  }
  for (const fallback of ['paragraph', 'raw']) {
    const adapter = blockTools.find((tool) => tool.name === fallback)
    if (adapter !== undefined && claims(adapter)) return adapter
  }

  return null
}

export class EditorJsParseMarkdown {
  private editorjsTools: BlockToolAdapterWithConstructable[]
  private editorJsInstance: API
  private markdown: string

  constructor(editorJsInstance: API, markdown: string) {
    this.editorJsInstance = editorJsInstance
    // @ts-ignore because
    this.editorjsTools = editorJsInstance.tools.getBlockTools() || []
    this.markdown = markdown
  }

  parseMarkdown(): void {
    this.editorJsInstance.blocks.clear()

    for (const chunk of MarkdownUtils.chunkMarkdown(this.markdown)) {
      const adapter = chunkTool(this.editorjsTools, chunk.text)
      adapter?.constructable?.importFromMarkdown(this.editorJsInstance, chunk.text)
    }
  }
}

export default EditorJsParseMarkdown
