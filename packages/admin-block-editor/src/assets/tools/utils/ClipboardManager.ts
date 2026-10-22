import EditorJS, { API, SanitizerConfig } from '@editorjs/editorjs'
import DOMPurify from 'dompurify'
import { MarkdownUtils } from './MarkdownUtils'
import { BlockToolAdapterWithConstructable, chunkTool } from '../../EditorJsParseMarkdown'
import EditorJsExportMarkdown from '../../EditorJsExportMarkdown'
import { GroupNesting } from '../Group/GroupNesting'
import { reportEditorError } from '../../EditorDiagnostics'
import type { PastedItem } from '../List/List'

/** A Word list paragraph: `mso-list:l0 level2 lfo1` in its style. */
const WORD_LIST_LEVEL = /mso-list:\s*l\d+\s+level(\d+)/i

/** List items, nested or legacy (a string, a checklist `{text}`), in document order. */
function flattenListItems(items: any[], depth: number): PastedItem[] {
    return items.flatMap(item => [
        { content: typeof item === 'string' ? item : (item.content ?? item.text ?? ''), depth },
        ...flattenListItems(Array.isArray(item.items) ? item.items : [], depth + 1),
    ])
}

/**
 * Word writes a list as paragraphs, each with its level in its style and its
 * bullet or number in a `mso-list:Ignore` span. Rebuild each run of them as the
 * nested lists they stand for, the glyphs dropped, before the conversion.
 */
function convertWordLists(container: HTMLElement): void {
    const paragraphs = Array.from(container.querySelectorAll('p')).filter(p => WORD_LIST_LEVEL.test(p.getAttribute('style') ?? ''))
    const handled = new Set<Element>()
    for (const first of paragraphs) {
        if (handled.has(first)) continue
        const levels: { level: number; list: HTMLElement }[] = []
        for (let p: Element | null = first, next: Element | null; p && paragraphs.includes(p as HTMLParagraphElement); p = next) {
            next = nextElementInRun(p)
            handled.add(p)
            const level = Number(WORD_LIST_LEVEL.exec(p.getAttribute('style')!)![1])
            const glyphs = Array.from(p.querySelectorAll('[style*="mso-list"]')).filter(span => /mso-list:\s*Ignore/i.test(span.getAttribute('style')!))
            const ordered = glyphs.some(span => /^[\dA-Za-z]{1,4}[.)]$/.test(span.textContent!.trim()))
            glyphs.forEach(span => span.remove())
            while (levels.length > 1 && levels[levels.length - 1]!.level > level) levels.pop()
            let parent = levels[levels.length - 1]
            if (!parent || parent.level < level) {
                const list = document.createElement(ordered ? 'ol' : 'ul')
                if (parent) parent.list.lastElementChild!.append(list)
                else first.before(list)
                parent = { level, list }
                levels.push(parent)
            }
            const item = document.createElement('li')
            item.append(...Array.from(p.childNodes))
            parent.list.append(item)
            if (p !== first) p.remove()
        }
        first.remove()
    }
}

/** The next element after `node`, past the whitespace and comments Word writes between paragraphs. */
function nextElementInRun(node: Node): Element | null {
    let next = node.nextSibling
    while (next && (next.nodeType === Node.COMMENT_NODE || (next.nodeType === Node.TEXT_NODE && !next.textContent!.trim()))) {
        next = next.nextSibling
    }
    return next?.nodeType === Node.ELEMENT_NODE ? next as Element : null
}

/** One line per field of a selected block; the level picker of a heading is not text. */
function blockPlainText(block: Element): string {
    const fields = Array.from(block.querySelectorAll('[contenteditable="true"]'))
        .filter(field => !field.parentElement?.closest('[contenteditable="true"]'))
    const sources = fields.length > 0 ? fields : [block.querySelector('.ce-block__content') ?? block]
    return sources.map(source => {
        const copy = source.cloneNode(true) as Element
        copy.querySelectorAll('select, .ce-header-level-wrapper').forEach(element => element.remove())
        copy.querySelectorAll('br').forEach(br => br.replaceWith('\n'))
        return (copy.textContent ?? '').trim()
    }).filter(Boolean).join('\n')
}

/**
 * ClipboardManager handles copy/paste operations for EditorJS
 * - Copy: Always converts selected content to markdown
 * - Paste: Smart detection of markdown patterns, converts to EditorJS blocks
 */
export default class ClipboardManager {
    private editor: EditorJS
    private _editorjsTools: BlockToolAdapterWithConstructable[] | null = null

    constructor({ editor }: { editor: EditorJS }) {
        this.editor = editor
        this.initialize()
    }

    /**
     * Lazy-load editor tools when needed
     */
    private get editorjsTools(): BlockToolAdapterWithConstructable[] {
        if (this._editorjsTools === null) {
            // @ts-ignore - accessing internal API
            this._editorjsTools = (this.editor as API).tools?.getBlockTools() || []
        }
        return this._editorjsTools
    }

    private initialize(): void {
        this.initializeCopyShortcut()
        this.initializeCopyListener()
        this.initializeBlockCopyText()
        this.initializePasteListener()
    }

    /**
     * Where this manager leaves a copy of selected blocks to Editor.js (no
     * Clipboard API, a holder not named `editorjs_…`), Editor.js writes each
     * block's text content as plain text: list items run together and a
     * heading starts with its level picker ("H2H3H4"). Its HTML and its own
     * block data are fine for pasting back, so only the plain text is redone,
     * one line per field. It is read before the event reaches Editor.js — a
     * cut removes the blocks while the event is still being dispatched — and
     * written after Editor.js wrote its own.
     */
    private initializeBlockCopyText(): void {
        let text: string | null = null
        for (const type of ['copy', 'cut']) {
            window.addEventListener(type, () => {
                const blocks = document.querySelectorAll('.ce-block--selected')
                text = blocks.length > 0 ? Array.from(blocks, blockPlainText).filter(Boolean).join('\n\n') : null
            }, true)
            window.addEventListener(type, (event) => {
                const clipboard = (event as ClipboardEvent).clipboardData
                if (text !== null && event.defaultPrevented && clipboard) clipboard.setData('text/plain', text)
                text = null
            })
        }
    }

    private initializeCopyShortcut(): void {
        // When blocks are selected (e.g. via the block-settings button), the
        // block-tunes popover consumes the Ctrl/Cmd+C keystroke — it closes and
        // clears the selection before the native `copy` event (or any document
        // handler) fires, so handleCopy finds nothing. Catch the shortcut at
        // keydown on `window`: a window-capture listener runs before Editor.js'
        // own document-level handlers, while the selection is still intact.
        window.addEventListener(
            'keydown',
            (event: KeyboardEvent) => this.handleCopyShortcut(event),
            true, // capture phase — run before the popover handles the key
        )
    }

    private initializeCopyListener(): void {
        // Listen in capture phase to intercept before EditorJS/browser
        document.addEventListener(
            'copy',
            (event: ClipboardEvent) => this.handleCopy(event),
            true, // capture phase
        )
    }

    /**
     * Copy block-selected content via the Ctrl/Cmd+C shortcut, before the
     * block-tunes popover clears the selection. Only acts on a pure block
     * selection (no text range) — inline/text copy is left to the copy event.
     */
    private handleCopyShortcut(event: KeyboardEvent): void {
        const isCopy = (event.ctrlKey || event.metaKey) && (event.key === 'c' || event.key === 'C')
        if (!isCopy) return

        // A real text selection is handled by the native copy event.
        const selection = window.getSelection()
        if (selection && selection.rangeCount > 0 && !selection.isCollapsed) return

        const target = event.target as Element
        if (target?.closest('.monaco-editor') || target?.closest('[data-editor]')) return

        const editorHolder = target?.closest('[id^="editorjs_"]') || document.querySelector('[id^="editorjs_"]')
        if (!editorHolder) return

        const selectedBlocks = editorHolder.querySelectorAll('.ce-block--selected')
        if (selectedBlocks.length === 0) return

        // Owning the shortcut (preventDefault) stops the browser firing the
        // (now useless) copy event, so we don't double-handle a cleared selection.
        this.copyBlocks(event, selectedBlocks)
    }

    private initializePasteListener(): void {
        document.addEventListener(
            'paste',
            (event: ClipboardEvent) => this.handlePaste(event),
            true, // capture phase
        )
    }

    /**
     * Handle copy events - convert selection to markdown
     * In capture phase, we get the selection, create our own clipboard data, and prevent default
     */
    private handleCopy(event: ClipboardEvent): void {
        const target = event.target as Element

        // Try to find editor holder - check multiple selectors
        let editorHolder = target?.closest('[id^="editorjs_"]')

        if (!editorHolder) {
            const selection = window.getSelection()
            const anchorNode = selection?.anchorNode
            const anchorElement = anchorNode?.nodeType === Node.TEXT_NODE
                ? anchorNode.parentElement
                : anchorNode as Element
            editorHolder = anchorElement?.closest('[id^="editorjs_"]') || null
        }

        // Also try finding by .codex-editor class
        if (!editorHolder) {
            const codexEditor = target?.closest('.codex-editor')
            if (codexEditor) {
                editorHolder = codexEditor.closest('[id^="editorjs_"]') || codexEditor.parentElement?.closest('[id^="editorjs_"]') || null
            }
        }

        // Try document.querySelector as last resort (for block selection where target is BODY)
        if (!editorHolder) {
            editorHolder = document.querySelector('[id^="editorjs_"]')
        }

        if (!editorHolder) {
            return
        }

        // Skip if inside Monaco editor or Raw block
        if (target?.closest('.monaco-editor') || target?.closest('[data-editor]')) {
            return
        }

        // Check for EditorJS block selection first (multi-block selection)
        const selectedBlocks = editorHolder.querySelectorAll('.ce-block--selected')
        if (selectedBlocks.length > 0) {
            this.copyBlocks(event, selectedBlocks)
            return
        }

        // Fall back to text selection
        const selection = window.getSelection()
        if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
            return
        }

        // A selection spanning several blocks copies them whole, and so does one
        // running across the fields of a block (list items, table cells). A
        // selection within one field is inline text — a word out of a sentence,
        // a value out of a cell: fall through to the inline handling below.
        const blocksInSelection = this.getBlocksInSelection(selection, editorHolder)
        if (blocksInSelection.length > 0 && !this.isSelectionWithinOneField(selection)) {
            this.copyBlocks(event, blocksInSelection)
            return
        }

        // Fallback: inline selection - use simple markdown conversion
        let range: Range
        try {
            range = selection.getRangeAt(0)
        } catch {
            return
        }

        const container = document.createElement('div')
        container.appendChild(range.cloneContents())

        // cloneContents omits formatting ancestors when the range stays inside
        // them (for example, a word selected entirely inside <b>).
        let ancestor = range.commonAncestorContainer.nodeType === Node.ELEMENT_NODE
            ? range.commonAncestorContainer as Element
            : range.commonAncestorContainer.parentElement
        const field = ancestor?.closest('[contenteditable="true"]')
        while (field && ancestor && ancestor !== field) {
            const wrapper = ancestor.cloneNode(false)
            while (container.firstChild) wrapper.appendChild(container.firstChild)
            container.appendChild(wrapper)
            ancestor = ancestor.parentElement
        }

        // Remove non-editable elements
        container.querySelectorAll('[contenteditable="false"], .ce-header-level-wrapper, select').forEach(el => el.remove())

        const html = container.innerHTML
        const markdown = MarkdownUtils.convertInlineHtmlToMarkdown(html, false).replace(/  +/g, ' ').trim()

        if (!markdown || !event.clipboardData) {
            return
        }

        event.preventDefault()
        event.stopImmediatePropagation()
        event.clipboardData.setData('text/plain', markdown)
        event.clipboardData.setData('text/html', html)
    }

    /**
     * Copy whole blocks as the page export writes them: each block through its
     * tool's export, tunes included, and one the author left untouched as the
     * markdown it was parsed from. Saving first reads the blocks as they stand,
     * so an edit Editor.js has not reported yet is copied too.
     *
     * Those exports are async (Prettier) while a copy event must be filled
     * before it returns, so the clipboard is written through the Clipboard API
     * instead: the item is handed over within the user gesture and its content
     * is a promise, resolved once the export lands. Where that API is missing
     * (an insecure context, an older browser), Editor.js's own copy of the
     * selected blocks stands in.
     */
    private copyBlocks(event: Event, blocks: ArrayLike<Element>): void {
        if (!navigator.clipboard?.write || typeof ClipboardItem === 'undefined') return

        event.preventDefault()
        event.stopImmediatePropagation()

        const ids = new Set(Array.from(blocks, (block) => block.getAttribute('data-id')))
        const api = this.editor as unknown as API
        const markdown = api.saver.save().then((output) =>
            new EditorJsExportMarkdown(api, {
                ...output,
                blocks: output.blocks.filter((block) => ids.has(block.id ?? null)),
            }).exportToMarkdown(),
        )

        navigator.clipboard
            .write([
                new ClipboardItem({
                    'text/plain': markdown.then((text) => new Blob([text], { type: 'text/plain' })),
                }),
            ])
            .catch((error: unknown) => {
                reportEditorError(blocks[0]?.closest('.editorjs-holder') ?? null, 'copy', error)
                console.error('Unable to copy the selected blocks', error)
            })
    }

    /**
     * Whether the selection is confined to one editable field of a block: a
     * paragraph, a heading, a list item, a table cell. Such a selection is
     * plain inline text, not a copy of the block it sits in.
     */
    private isSelectionWithinOneField(selection: Selection): boolean {
        if (selection.rangeCount === 0) return false
        const commonAncestor = selection.getRangeAt(0).commonAncestorContainer
        const element = commonAncestor.nodeType === Node.TEXT_NODE
            ? commonAncestor.parentElement
            : (commonAncestor as Element)
        return !!element?.closest('[contenteditable="true"]')
    }

    /**
     * Get all blocks that are partially or fully within the current selection
     */
    private getBlocksInSelection(selection: Selection, editorHolder: Element): Element[] {
        if (selection.rangeCount === 0) return []

        try {
            const range = selection.getRangeAt(0)
            const allBlocks = Array.from(editorHolder.querySelectorAll('.ce-block'))
            const blocksInSelection: Element[] = []

            for (const block of allBlocks) {
                // Check if the block intersects with the selection range
                if (range.intersectsNode(block)) {
                    blocksInSelection.push(block)
                }
            }

            return blocksInSelection
        } catch {
            return []
        }
    }

    /**
     * Handle paste events - detect markdown/HTML and convert to blocks
     */
    private handlePaste(event: ClipboardEvent): void {
        // Check if we're in an EditorJS block
        const selection = window.getSelection()
        const anchorNode = selection?.anchorNode
        if (!anchorNode) return

        const element = anchorNode.nodeType === Node.TEXT_NODE
            ? anchorNode.parentElement
            : anchorNode as Element

        const blockContent = element?.closest('.ce-block__content')
        if (!blockContent) return

        // Skip if inside Monaco editor, Raw block, CardList contenteditable, or a
        // table cell — there, paste is inline text, never new blocks.
        if (element?.closest('.monaco-editor') ||
            element?.closest('.editorjs-monaco-wrapper') ||
            element?.closest('.cdx-card-list') ||
            element?.closest('.tc-cell')) return

        // Get clipboard content
        const plainText = event.clipboardData?.getData('text/plain') || ''
        const htmlText = event.clipboardData?.getData('text/html') || ''

        // Let PasteLink handle URL paste over selected text
        const selectedText = selection?.toString() || ''
        if (selectedText && (this.isValidURL(plainText) || this.isValidRelativeURI(plainText))) {
            return // PasteLink will handle this
        }

        // A copied inline selection belongs at the caret, even though its plain
        // text contains Markdown. Keep it inline rather than importing new blocks.
        if (htmlText) {
            const fragment = document.createElement('div')
            // Windows wraps HTML clipboard fragments in a CRLF-delimited document.
            // Context whitespace is not part of the selection and must not become Markdown paragraphs.
            const selectedHtml = /<!--StartFragment-->([\s\S]*?)<!--EndFragment-->/i.exec(htmlText)?.[1] ?? htmlText
            fragment.innerHTML = DOMPurify.sanitize(selectedHtml)
            if (fragment.querySelector('b, strong, i, em, u, s, a, code, mark, small') &&
                !fragment.querySelector('p, div, h1, h2, h3, h4, h5, h6, ul, ol, li, table, pre, blockquote')) {
                const api = this.editor as unknown as API
                const block = api.blocks.getById(blockContent.closest<HTMLElement>('.ce-block')!.dataset.id!)!
                const tool = this.editorjsTools.find(tool => tool.name === block.name)!
                const html = api.sanitizer.clean(fragment.innerHTML, tool.baseSanitizeConfig)
                event.preventDefault()
                event.stopPropagation()
                document.execCommand('insertHTML', false, html)
                return
            }
        }

        const markdown = this.clipboardMarkdown(plainText, htmlText)

        const listField = element?.closest('.cdx-list__item-content')
        if (listField && this.pasteIntoList(event, listField, markdown, plainText)) return

        if (markdown === null) return // Let default paste handle plain text

        // Prevent default and insert as blocks
        event.preventDefault()
        event.stopPropagation()

        this.insertMarkdownAsBlocks(markdown)
    }

    /**
     * The clipboard as markdown, or null for plain text without any.
     *
     * When the plain text already carries markdown, it is authoritative:
     * this editor's own copies always write markdown there (a whole-block
     * copy writes nothing else), and converting the HTML that may come
     * along would lose what only the markdown holds — a table's alignment
     * and sticky header, for one. HTML conversion is only a fallback for
     * genuine external rich text (Google Docs, Word, Sheets), whose plain
     * text has no markdown syntax — except Word's numbered list, written
     * there as "1." lines that lose its levels.
     */
    private clipboardMarkdown(plainText: string, htmlText: string): string | null {
        const plainHasMarkdown = this.detectMarkdownPatterns(MarkdownUtils.retrieveMarkdownWithoutTunes(plainText))

        // Try to convert HTML to markdown if it looks like rich text (Google Docs, Word, etc.)
        let textToProcess = plainText
        if ((!plainHasMarkdown || WORD_LIST_LEVEL.test(htmlText)) && htmlText && this.isRichTextHtml(htmlText)) {
            const convertedMarkdown = this.convertHtmlToMarkdown(htmlText)
            if (convertedMarkdown) {
                textToProcess = convertedMarkdown
            }
        }

        if (!textToProcess || !this.detectMarkdownPatterns(textToProcess)) return null
        return textToProcess
    }

    /**
     * Paste several lines into a list item as items of that list, at the caret,
     * rather than as blocks after the whole list. A single item is inline text.
     * Returns false to leave the paste to the block import or the browser.
     */
    private pasteIntoList(event: ClipboardEvent, field: Element, markdown: string | null, plainText: string): boolean {
        const api = this.editor as unknown as API
        const block = api.blocks.getById(field.closest<HTMLElement>('.ce-block')?.dataset.id ?? '')
        const tool = this.editorjsTools.find(tool => tool.name === block?.name)
        if (!block || typeof (tool?.constructable as { prototype?: { pasteItems?: unknown } })?.prototype?.pasteItems !== 'function') {
            return false
        }

        const items = this.pastedListItems(event.clipboardData?.getData('application/x-editor-js') ?? '', markdown, plainText)
        if (items === null) return false

        const rules = (tool!.sanitizeConfig as Record<string, SanitizerConfig>).items ?? tool!.baseSanitizeConfig
        const clean = items.map(item => ({ ...item, content: api.sanitizer.clean(item.content, rules) }))
        event.preventDefault()
        event.stopPropagation()
        if (clean.length === 1) {
            document.execCommand('insertHTML', false, clean[0]!.content)
        } else {
            block.call('pasteItems', { items: clean })
        }
        return true
    }

    /**
     * The clipboard as list items, or null when it holds a block no item can
     * carry (a table, an image): only lists, paragraphs and headings convert.
     * Editor.js' own copy of blocks comes first, then markdown (this editor's
     * copies, converted rich text), each block read through its tool's import;
     * plain text gives an item per line.
     */
    private pastedListItems(editorJsData: string, markdown: string | null, plainText: string): PastedItem[] | null {
        let blocks: { type: string; data: any }[]
        if (editorJsData) {
            try {
                blocks = (JSON.parse(editorJsData) as { tool: string; data: any }[]).map(({ tool, data }) => ({ type: tool, data }))
            } catch {
                return null
            }
        } else if (markdown !== null) {
            blocks = this.markdownBlocks(markdown)
        } else {
            const lines = plainText.split(/\r?\n/).map(line => line.trim()).filter(Boolean)
            if (lines.length < 2) return null // A single line pastes as the browser does.
            const text = document.createElement('span')
            return lines.map(line => {
                text.textContent = line
                return { content: text.innerHTML, depth: 0 }
            })
        }

        const items: PastedItem[] = []
        for (const { type, data } of blocks) {
            if (type === 'list' && Array.isArray(data?.items)) {
                items.push(...flattenListItems(data.items, 0))
            } else if ((type === 'paragraph' || type === 'header') && typeof data?.text === 'string') {
                // A soft line break starts an item; a hard one (<br>) stays inside it.
                items.push(...data.text.split('\n').filter((line: string) => line.trim()).map((line: string) => ({ content: line, depth: 0 })))
            } else {
                return null
            }
        }
        return items.length > 0 ? items : null
    }

    /** Markdown read into block data by each chunk's tool, without inserting anything. */
    private markdownBlocks(markdown: string): { type: string; data: any }[] {
        const blocks: { type: string; data: any }[] = []
        const recorder = {
            blocks: {
                insert: (type = 'paragraph', data: any = {}) => {
                    blocks.push({ type, data })
                    return { id: String(blocks.length - 1) }
                },
                update: (id: string, data: any) => {
                    blocks[Number(id)]!.data = data
                },
            },
        } as unknown as API
        const nesting = new GroupNesting()
        for (const { text } of MarkdownUtils.chunkMarkdown(markdown)) {
            chunkTool(this.editorjsTools, text, nesting)?.constructable?.importFromMarkdown(recorder, text)
        }
        return blocks
    }

    /**
     * Check if HTML looks like it came from a rich text source (Google Docs, Word, Sheets, etc.)
     */
    private isRichTextHtml(html: string): boolean {
        // Detect Google Docs
        if (html.includes('docs-internal-guid') || html.includes('google-docs')) return true
        // Detect Microsoft Word/Office
        if (html.includes('urn:schemas-microsoft-com:office') || html.includes('mso-')) return true
        // Detect Google Sheets
        if (html.includes('google-sheets-html-origin')) return true
        // Detect LibreOffice
        if (html.includes('LibreOffice')) return true
        // Detect general rich text with formatting tags
        if (/<(b|strong|i|em|u|s|h[1-6]|ul|ol|li|table|tr|td|th|blockquote|pre|code)[^>]*>/i.test(html)) return true
        return false
    }

    /**
     * Convert HTML from rich text sources to markdown
     */
    private convertHtmlToMarkdown(html: string): string {
        // Sanitize external clipboard HTML before parsing or preserving tables.
        const container = document.createElement('div')
        container.innerHTML = DOMPurify.sanitize(html)

        // Remove Google Docs specific wrapper elements
        container.querySelectorAll('[id^="docs-internal-guid"]').forEach(el => {
            el.replaceWith(...Array.from(el.childNodes))
        })

        // Remove style tags and scripts
        container.querySelectorAll('style, script, meta, link').forEach(el => el.remove())
        convertWordLists(container)

        // Process the HTML and convert to markdown
        return this.processNodeToMarkdown(container)
    }

    /**
     * Recursively process DOM nodes and convert to markdown
     */
    private processNodeToMarkdown(node: Node): string {
        const parts: string[] = []

        node.childNodes.forEach(child => {
            if (child.nodeType === Node.TEXT_NODE) {
                const text = child.textContent || ''
                // Replace non-breaking spaces
                parts.push(text.replace(/\u00A0/g, ' '))
            } else if (child.nodeType === Node.ELEMENT_NODE) {
                const el = child as HTMLElement
                const tagName = el.tagName.toLowerCase()
                const innerContent = this.processNodeToMarkdown(el)

                switch (tagName) {
                    case 'h1':
                        parts.push('\n\n# ' + innerContent.trim() + '\n\n')
                        break
                    case 'h2':
                        parts.push('\n\n## ' + innerContent.trim() + '\n\n')
                        break
                    case 'h3':
                        parts.push('\n\n### ' + innerContent.trim() + '\n\n')
                        break
                    case 'h4':
                        parts.push('\n\n#### ' + innerContent.trim() + '\n\n')
                        break
                    case 'h5':
                        parts.push('\n\n##### ' + innerContent.trim() + '\n\n')
                        break
                    case 'h6':
                        parts.push('\n\n###### ' + innerContent.trim() + '\n\n')
                        break
                    case 'p':
                    case 'div':
                        parts.push('\n\n' + innerContent.trim() + '\n\n')
                        break
                    case 'br':
                        parts.push('\n')
                        break
                    case 'b':
                    case 'strong':
                        if (innerContent.trim()) {
                            parts.push('**' + innerContent.trim() + '**')
                        }
                        break
                    case 'i':
                    case 'em':
                        if (innerContent.trim()) {
                            parts.push('_' + innerContent.trim() + '_')
                        }
                        break
                    case 'u':
                        if (innerContent.trim()) {
                            parts.push('<u>' + innerContent.trim() + '</u>')
                        }
                        break
                    case 's':
                    case 'strike':
                    case 'del':
                        if (innerContent.trim()) {
                            parts.push('~~' + innerContent.trim() + '~~')
                        }
                        break
                    case 'code':
                        if (innerContent.trim()) {
                            parts.push('`' + innerContent.trim() + '`')
                        }
                        break
                    case 'pre':
                        parts.push('\n\n```\n' + innerContent.trim() + '\n```\n\n')
                        break
                    case 'blockquote': {
                        const quotedLines = innerContent.trim().split('\n').map(line => '> ' + line).join('\n')
                        parts.push('\n\n' + quotedLines + '\n\n')
                        break
                    }
                    case 'a': {
                        const href = el.getAttribute('href') || ''
                        if (href && innerContent.trim()) {
                            parts.push('[' + innerContent.trim() + '](' + href + ')')
                        } else {
                            parts.push(innerContent)
                        }
                        break
                    }
                    case 'img': {
                        const src = el.getAttribute('src') || ''
                        const alt = el.getAttribute('alt') || ''
                        if (src) {
                            parts.push('![' + alt + '](' + src + ')')
                        }
                        break
                    }
                    case 'ul':
                    case 'ol': {
                        parts.push('\n\n' + this.convertListToMarkdown(el) + '\n\n')
                        break
                    }
                    case 'li':
                        // Li is handled by ul/ol
                        parts.push(innerContent)
                        break
                    case 'table':
                        // Keep the table as HTML and let the Table tool decide: a
                        // simple table becomes a Table block, a complex one (merged
                        // or nested cells, block-level content) falls back to Raw.
                        // Flatten to one line so a blank line in the source can't
                        // split it across blocks.
                        parts.push('\n\n' + el.outerHTML.replace(/[\r\n]+/g, ' ') + '\n\n')
                        break
                    case 'hr':
                        parts.push('\n\n---\n\n')
                        break
                    case 'span': {
                        // Check for inline styles
                        const style = el.getAttribute('style') || ''
                        let content = innerContent
                        if (style.includes('font-weight') && (style.includes('bold') || style.includes('700'))) {
                            content = '**' + content.trim() + '**'
                        }
                        if (style.includes('font-style') && style.includes('italic')) {
                            content = '_' + content.trim() + '_'
                        }
                        if (style.includes('text-decoration') && style.includes('underline')) {
                            content = '<u>' + content.trim() + '</u>'
                        }
                        if (style.includes('text-decoration') && style.includes('line-through')) {
                            content = '~~' + content.trim() + '~~'
                        }
                        parts.push(content)
                        break
                    }
                    default:
                        parts.push(innerContent)
                }
            }
        })

        return parts.join('')
            .replace(/\n{3,}/g, '\n\n') // Normalize multiple newlines
            .replace(/\u00A0/g, ' ')     // Replace any remaining non-breaking spaces
    }

    private convertListToMarkdown(list: Element, indentation = 0): string {
        return Array.from(list.querySelectorAll(':scope > li')).map((item, index) => {
            const content = item.cloneNode(true) as Element
            const children = Array.from(content.querySelectorAll(':scope > ul, :scope > ol'))
            children.forEach(child => child.remove())
            const prefix = ' '.repeat(indentation)
            const marker = list.tagName === 'OL' ? `${index + 1}. ` : '- '
            const textIndentation = indentation + marker.length
            const text = this.processNodeToMarkdown(content).trim().replace(/\n+/g, '\n' + ' '.repeat(textIndentation))
            const nested = children.map(child => '\n' + this.convertListToMarkdown(child, textIndentation)).join('')
            return prefix + marker + text + nested
        }).join('\n')
    }

    /**
     * Detect if text contains markdown or structured content patterns
     */
    private detectMarkdownPatterns(text: string): boolean {
        const trimmed = text.trim()

        // Skip single-line simple text (no special characters)
        if (!trimmed.includes('\n') && !/[#*_`[\]{}><-]/.test(trimmed)) {
            return false
        }

        const markdownPatterns = [
            /^#{1,6}\s/m,                    // Headers (# to ######)
            /^[-*+]\s/m,                     // Unordered lists
            /^\d+\.\s/m,                     // Ordered lists
            /^>\s/m,                         // Blockquotes
            /^```/m,                         // Code blocks
            /^\|.+\|$/m,                     // Tables
            /<table[\s>]/i,                  // HTML tables (kept verbatim → Table or Raw)
            /!\[.*\]\(.+\)/,                 // Images
            /\[.+\]\(.+\)/,                  // Links
            /\*\*[^*]+\*\*/,                 // Bold
            /__[^_]+__/,                     // Bold (alternative)
            /(?<![*_])[*_][^*_\s][^*_]*[^*_\s][*_](?![*_])/,  // Italic
            /~~[^~]+~~/,                     // Strikethrough
            /`[^`]+`/,                       // Inline code
            /^-{3,}$/m,                      // Horizontal rules
            /^<!--break-->$/m,               // Break delimiter
            /{{.+}}/,                        // Twig output blocks
            /{%.+%}/,                        // Twig control blocks
            /^{#[^}]+}$/m,                   // Block attributes {#id.class}
        ]

        return markdownPatterns.some(pattern => pattern.test(trimmed))
    }

    /**
     * Insert markdown content as EditorJS blocks
     */
    private insertMarkdownAsBlocks(markdown: string): void {
        // Normalize multiple newlines
        markdown = markdown.replace(/\n\s*\n+/g, '\n\n')
        markdown = this.rejoinTableFragments(markdown)
        const blocks = markdown.split('\n\n')

        // @ts-ignore - accessing internal API
        const api = this.editor as API
        const nesting = new GroupNesting()

        for (const block of blocks) {
            if (!block.trim()) continue
            const adapter = chunkTool(this.editorjsTools, block, nesting)
            adapter?.constructable?.importFromMarkdown(api, block)
        }
    }

    /**
     * Keep a markdown table in one block when stray blank lines would otherwise
     * split it (each fragment would become its own table, and a lone delimiter
     * row would render as data). A blank line is dropped when it sits next to a
     * delimiter row (`|---|`, which always belongs to its table) or right after a
     * block-attribute line (`{...}`, which belongs to the block it precedes).
     * Two distinct tables — separated by a blank between two non-delimiter rows —
     * are left untouched.
     */
    private rejoinTableFragments(markdown: string): string {
        const isDelimiter = (line: string): boolean => /^\|[\s:|-]*-[\s:|-]*\|$/.test(line.trim())
        const isAttribute = (line: string): boolean => /^{[^}]+}$/.test(line.trim())

        const lines = markdown.split('\n')
        const result: string[] = []

        for (let i = 0; i < lines.length; i++) {
            if (lines[i]!.trim() === '') {
                const previous = result.length > 0 ? result[result.length - 1]! : ''
                let next = i + 1
                while (next < lines.length && lines[next]!.trim() === '') {
                    next++
                }
                const following = next < lines.length ? lines[next]! : ''

                if (isDelimiter(previous) || isDelimiter(following) || isAttribute(previous)) {
                    continue
                }
            }
            result.push(lines[i]!)
        }

        return result.join('\n')
    }

    private isValidURL(str: string): boolean {
        try {
            new URL(str)
            return true
        } catch {
            return false
        }
    }

    private isValidRelativeURI(uri: string): boolean {
        return /^\/[^\s]*$/.test(uri)
    }
}
