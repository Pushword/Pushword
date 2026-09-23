interface BlockSource {
  markdown: string
  /** The block's export right after the parse, set by the first export. */
  baseline?: string
}

/**
 * The markdown each block was parsed from, so saving writes a block the author
 * did not touch back byte for byte instead of the editor's normalisation of it
 * (prettier, list numbering, typography, image paths): one edit rewrites its
 * own block, not the page.
 *
 * "Untouched" is judged on the export. The first export after a parse — the
 * onChange the parse itself triggers, the same point the undo baseline waits
 * for — is each block's baseline, and a later export equal to it means the
 * block still holds what it was parsed into.
 */
export class BlockSources {
  private static readonly byEditor = new WeakMap<object, BlockSources>()

  private readonly sources = new Map<string, BlockSource>()

  /** Fresh sources for a new parse of `editor`: the blocks it replaces are gone. */
  static reset(editor: object): BlockSources {
    const sources = new BlockSources()
    BlockSources.byEditor.set(editor, sources)

    return sources
  }

  static of(editor: object): BlockSources | undefined {
    return BlockSources.byEditor.get(editor)
  }

  record(blockId: string, markdown: string): void {
    this.sources.set(blockId, { markdown })
  }

  /** The markdown to write for a block whose export is `exported`. */
  resolve(blockId: string | undefined, exported: string): string {
    const source = blockId === undefined ? undefined : this.sources.get(blockId)
    if (source === undefined) return exported

    source.baseline ??= exported

    return exported === source.baseline ? source.markdown : exported
  }
}
