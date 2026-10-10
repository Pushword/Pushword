import EditorJS, { API } from '@editorjs/editorjs'
import DOMPurify from 'dompurify'
import { MarkdownUtils } from './MarkdownUtils'
import { BlockToolAdapterWithConstructable, chunkTool } from '../../EditorJsParseMarkdown'
import EditorJsExportMarkdown from '../../EditorJsExportMarkdown'
import { GroupNesting } from '../Group/GroupNesting'

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
        this.initializePasteListener()
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

        // Remove non-editable elements
        container.querySelectorAll('[contenteditable="false"], .ce-header-level-wrapper, select').forEach(el => el.remove())

        const html = container.innerHTML
        const markdown = MarkdownUtils.convertInlineHtmlToMarkdown(html, false).replace(/  +/g, ' ').trim()

        if (!markdown) {
            return
        }

        event.preventDefault()
        event.stopImmediatePropagation()
        if (event.clipboardData) {
            event.clipboardData.setData('text/plain', markdown)
            event.clipboardData.setData('text/html', html)
        }
        this.writeToClipboard(markdown, html)
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
            .catch((error: unknown) => console.error('Unable to copy the selected blocks', error))
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
     * Write content to clipboard with both markdown and HTML formats
     */
    private async writeToClipboard(markdown: string, html: string): Promise<void> {
        try {
            await navigator.clipboard.write([
                new ClipboardItem({
                    'text/html': new Blob([html], { type: 'text/html' }),
                    'text/plain': new Blob([markdown], { type: 'text/plain' }),
                }),
            ])
        } catch {
            // Fallback: try writeText
            try {
                await navigator.clipboard.writeText(markdown)
            } catch {
                // Silent fail - sync clipboardData.setData should have worked
            }
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

        // When the plain text already carries markdown, it is authoritative:
        // this editor's own copies always write markdown there (a whole-block
        // copy writes nothing else), and converting the HTML that may come
        // along would lose what only the markdown holds — a table's alignment
        // and sticky header, for one. HTML conversion is only a fallback for
        // genuine external rich text (Google Docs, Word, Sheets), whose plain
        // text has no markdown syntax.
        const plainHasMarkdown = this.detectMarkdownPatterns(MarkdownUtils.retrieveMarkdownWithoutTunes(plainText))

        // Try to convert HTML to markdown if it looks like rich text (Google Docs, Word, etc.)
        let textToProcess = plainText
        if (!plainHasMarkdown && htmlText && this.isRichTextHtml(htmlText)) {
            const convertedMarkdown = this.convertHtmlToMarkdown(htmlText)
            if (convertedMarkdown) {
                textToProcess = convertedMarkdown
            }
        }

        if (!textToProcess) return

        // Check if text contains markdown patterns
        if (!this.detectMarkdownPatterns(textToProcess)) {
            return // Let default paste handle plain text
        }

        // Prevent default and insert as blocks
        event.preventDefault()
        event.stopPropagation()

        this.insertMarkdownAsBlocks(textToProcess)
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
                    case 'ul': {
                        const ulItems = Array.from(el.querySelectorAll(':scope > li')).map(li => {
                            return '- ' + this.processNodeToMarkdown(li).trim()
                        }).join('\n')
                        parts.push('\n\n' + ulItems + '\n\n')
                        break
                    }
                    case 'ol': {
                        const olItems = Array.from(el.querySelectorAll(':scope > li')).map((li, idx) => {
                            return (idx + 1) + '. ' + this.processNodeToMarkdown(li).trim()
                        }).join('\n')
                        parts.push('\n\n' + olItems + '\n\n')
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
