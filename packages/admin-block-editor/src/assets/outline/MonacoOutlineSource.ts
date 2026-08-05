import { API } from '@editorjs/editorjs'
import { GroupNesting } from '../tools/Group/GroupNesting'
import { GroupRegistry } from '../tools/Group/GroupRegistry'
import { MarkdownUtils } from '../tools/utils/MarkdownUtils'
import {
  BlockToolAdapterWithConstructable,
  chunkTool,
} from '../EditorJsParseMarkdown'
import { OutlineEntry, OutlineSource } from './OutlineModel'

/**
 * How the Monaco-bound sources reach the field: the editor instance once the
 * lazy loader built it, and the plain form field carrying the text meanwhile.
 */
export interface MonacoContext {
  monaco(): any
  input(): HTMLTextAreaElement | HTMLInputElement | null
}

const BIND_RETRY_MS = 300
const BIND_MAX_TRIES = 50

/**
 * Outline sources over the block editor's Monaco views. The unit is the text
 * itself — a markdown chunk or a JSON block entry — so panel edits rewrite the
 * text and the next mode switch parses exactly what the panel showed.
 */
abstract class MonacoSourceBase implements OutlineSource {
  private changeSubscription: { dispose(): void } | null = null
  private bindTimer: number | null = null

  constructor(protected readonly context: MonacoContext) {}

  abstract entries(): OutlineEntry[]
  abstract navigateTo(entry: OutlineEntry): void
  abstract deleteSpan(start: number, end: number): void
  abstract moveSpan(start: number, end: number, to: number): void

  /** Push change notifications once Monaco is up; it loads lazily, so poll for it. */
  bind(onChange: () => void): void {
    let tries = 0
    const tryBind = (): void => {
      const monaco = this.context.monaco()
      if (!monaco) {
        this.bindTimer =
          ++tries < BIND_MAX_TRIES ? window.setTimeout(tryBind, BIND_RETRY_MS) : null
        return
      }
      this.bindTimer = null
      this.changeSubscription = monaco.onDidChangeModelContent(() => onChange())
    }
    tryBind()
  }

  dispose(): void {
    if (this.bindTimer !== null) window.clearTimeout(this.bindTimer)
    this.bindTimer = null
    this.changeSubscription?.dispose()
    this.changeSubscription = null
  }

  protected text(): string {
    const monaco = this.context.monaco()
    if (monaco) return monaco.getModel().getValue()

    return this.context.input()?.value ?? ''
  }

  /** Replace the whole text as ONE edit, so Monaco keeps it a single undo step. */
  protected replaceText(next: string): void {
    const monaco = this.context.monaco()
    if (!monaco) {
      const input = this.context.input()
      if (input !== null) input.value = next
      return
    }

    const model = monaco.getModel()
    model.pushEditOperations(
      [],
      [{ range: model.getFullModelRange(), text: next }],
      () => null,
    )
  }

  protected goToLine(line: number): void {
    const monaco = this.context.monaco()
    if (!monaco) return

    monaco.revealLineInCenter(line + 1)
    monaco.setPosition({ lineNumber: line + 1, column: 1 })
    monaco.focus()
  }

  protected collapse(text: string): string {
    return text.replace(/\s+/g, ' ').trim()
  }
}

export class MarkdownMonacoSource extends MonacoSourceBase {
  constructor(
    context: MonacoContext,
    private readonly api: API,
  ) {
    super(context)
  }

  entries(): OutlineEntry[] {
    const tools = this.tools()
    const nesting = new GroupNesting()

    return MarkdownUtils.chunkMarkdown(this.text()).map((chunk, index) => {
      const type = chunkTool(tools, chunk.text, nesting)?.name ?? 'raw'
      const stripped = MarkdownUtils.retrieveMarkdownWithoutTunes(chunk.text)
      return { index, type, ...this.levelAndLabel(type, stripped) }
    })
  }

  navigateTo(entry: OutlineEntry): void {
    const chunk = MarkdownUtils.chunkMarkdown(this.text())[entry.index]
    if (chunk !== undefined) this.goToLine(chunk.startLine)
  }

  deleteSpan(start: number, end: number): void {
    this.rewriteChunks((texts) => {
      texts.splice(start, end - start + 1)
    })
  }

  moveSpan(start: number, end: number, to: number): void {
    if (to >= start && to <= end + 1) return

    this.rewriteChunks((texts) => {
      const span = texts.splice(start, end - start + 1)
      texts.splice(to > end ? to - span.length : to, 0, ...span)
    })
  }

  /** Rewrite the field from its chunks, keeping the blank lines the source had. */
  private rewriteChunks(mutate: (texts: string[]) => void): void {
    const chunks = MarkdownUtils.chunkMarkdown(this.text())
    const separators = chunks.slice(0, -1).map((chunk) => chunk.separatorAfter)
    const texts = chunks.map((chunk) => chunk.text)

    mutate(texts)
    this.replaceText(MarkdownUtils.joinChunks(texts, separators))
  }

  private tools(): BlockToolAdapterWithConstructable[] {
    // @ts-ignore same accessor the markdown parser goes through
    return this.api.tools.getBlockTools() || []
  }

  private levelAndLabel(
    type: string,
    stripped: string,
  ): { level: number | null; label: string } {
    if (type === 'header') {
      const heading = /^(#{2,6})\s+(.*)/.exec(stripped.trim())
      return {
        level: heading === null ? 2 : heading[1]!.length,
        label: this.collapse((heading?.[2] ?? '').replace(/\{[^}]*\}\s*$/, '')),
      }
    }
    if (type === GroupRegistry.START) {
      const anchor = /\sid="([^"]*)"/.exec(stripped)?.[1] ?? ''
      const cssClass = /\sclass="([^"]*)"/.exec(stripped)?.[1] ?? ''
      return {
        level: null,
        label: [anchor === '' ? '' : `#${anchor}`, cssClass].filter(Boolean).join(' '),
      }
    }
    if (type === GroupRegistry.END) return { level: null, label: '' }

    return { level: null, label: this.collapse(stripped) }
  }
}

export class JsonMonacoSource extends MonacoSourceBase {
  entries(): OutlineEntry[] {
    return this.blocks().map((block, index) => ({
      index,
      type: typeof block.type === 'string' ? block.type : 'raw',
      label: this.labelOf(block),
      level: block.type === 'header' ? Number(block.data?.level ?? 2) : null,
    }))
  }

  navigateTo(entry: OutlineEntry): void {
    const id = this.blocks()[entry.index]?.id
    if (typeof id !== 'string') return

    const line = this.text()
      .split('\n')
      .findIndex((textLine) => textLine.includes(`"${id}"`))
    if (line >= 0) this.goToLine(line)
  }

  deleteSpan(start: number, end: number): void {
    this.rewriteBlocks((blocks) => {
      blocks.splice(start, end - start + 1)
    })
  }

  moveSpan(start: number, end: number, to: number): void {
    if (to >= start && to <= end + 1) return

    this.rewriteBlocks((blocks) => {
      const span = blocks.splice(start, end - start + 1)
      blocks.splice(to > end ? to - span.length : to, 0, ...span)
    })
  }

  private blocks(): any[] {
    try {
      const parsed = JSON.parse(this.text())
      return Array.isArray(parsed?.blocks) ? parsed.blocks : []
    } catch {
      return []
    }
  }

  private labelOf(block: any): string {
    if (block.type === GroupRegistry.START) {
      const anchor = typeof block.data?.anchor === 'string' ? block.data.anchor : ''
      const cssClass = typeof block.data?.class === 'string' ? block.data.class : ''
      return [anchor === '' ? '' : `#${anchor}`, cssClass].filter(Boolean).join(' ')
    }

    const text = typeof block.data?.text === 'string' ? block.data.text : ''
    return this.collapse(text.replace(/<[^>]*>/g, ' '))
  }

  private rewriteBlocks(mutate: (blocks: any[]) => void): void {
    let parsed: any
    try {
      parsed = JSON.parse(this.text())
    } catch {
      return
    }
    if (!Array.isArray(parsed?.blocks)) return

    mutate(parsed.blocks)
    this.replaceText(JSON.stringify(parsed, null, 2))
  }
}
