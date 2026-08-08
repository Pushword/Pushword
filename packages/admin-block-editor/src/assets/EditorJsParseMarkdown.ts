import { BlockToolAdapter } from '@editorjs/editorjs/types/tools/adapters/block-tool-adapter'
import { API } from '@editorjs/editorjs'
import { ToolInterface } from './tools/Abstract/ToolInterface'
import { MarkdownUtils } from './tools/utils/MarkdownUtils'
import { GroupNesting } from './tools/Group/GroupNesting'
import { GroupRegistry } from './tools/Group/GroupRegistry'
import GroupStart from './tools/Group/GroupStart'
import GroupEnd from './tools/Group/GroupEnd'

// Extended BlockToolAdapter to access the constructable property
export interface BlockToolAdapterWithConstructable extends BlockToolAdapter {
  constructable?: ToolInterface
}

/** The tool claiming a chunk on its own, before group nesting has a say. */
function claimingTool(
  blockTools: BlockToolAdapterWithConstructable[],
  stripped: string,
): BlockToolAdapterWithConstructable | null {
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

/**
 * The tool a markdown chunk belongs to, probed in the parser's own order —
 * shared with the outline panel and the clipboard so all three classify a
 * chunk identically. `nesting` carries the only cross-chunk state there is:
 * it tells a group's `</div>` from one closing hand-written HTML, so chunks
 * must be probed in document order against a single instance.
 */
export function chunkTool(
  blockTools: BlockToolAdapterWithConstructable[],
  markdownBlock: string,
  nesting: GroupNesting,
): BlockToolAdapterWithConstructable | null {
  const stripped = MarkdownUtils.retrieveMarkdownWithoutTunes(markdownBlock)
  const adapter = claimingTool(blockTools, stripped)

  if (adapter?.name === GroupRegistry.START) {
    nesting.openGroup(GroupStart.kindOf(stripped) ?? 'div')
  } else if (adapter?.name === GroupRegistry.END) {
    // A `</div>` closing a div the user hand-wrote is not a marker: keep it
    // Raw, or deleting a group would cascade onto their tag instead of ours.
    // Same for a show-more closer with nothing of its kind open.
    if (!nesting.closeGroup(GroupEnd.kindOf(stripped) ?? 'div')) {
      return blockTools.find((tool) => tool.name === 'raw') ?? null
    }
  } else if (adapter?.name === 'raw') {
    nesting.trackRaw(stripped)
  }

  return adapter
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

    const nesting = new GroupNesting()
    for (const chunk of MarkdownUtils.chunkMarkdown(this.markdown)) {
      const adapter = chunkTool(this.editorjsTools, chunk.text, nesting)
      adapter?.constructable?.importFromMarkdown(this.editorJsInstance, chunk.text)
    }
  }
}

export default EditorJsParseMarkdown
