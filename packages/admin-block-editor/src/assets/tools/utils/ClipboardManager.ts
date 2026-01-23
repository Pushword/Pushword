import EditorJS, { API } from '@editorjs/editorjs'
import { MarkdownUtils } from './MarkdownUtils'
import { BlockToolAdapter } from '@editorjs/editorjs/types/tools/adapters/block-tool-adapter'
import { ToolInterface } from '../Abstract/ToolInterface'

interface BlockToolAdapterWithConstructable extends BlockToolAdapter {
    constructable?: ToolInterface
    name: string
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
        this.initializeCopyListener()
        this.initializePasteListener()
    }

    private initializeCopyListener(): void {
        // Listen in capture phase to intercept before EditorJS/browser
        document.addEventListener(
            'copy',
            (event: ClipboardEvent) => this.handleCopy(event),
            true, // capture phase
        )
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
            this.handleBlockSelection(event, selectedBlocks)
            return
        }

        // Fall back to text selection
        const selection = window.getSelection()
        if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
            return
        }

        // Check if selection spans multiple blocks
        const blocksInSelection = this.getBlocksInSelection(selection, editorHolder)
        if (blocksInSelection.length > 1) {
            // Multi-block text selection - extract each block synchronously
            const markdownParts: string[] = []
            const htmlParts: string[] = []

            blocksInSelection.forEach(block => {
                const result = this.extractBlockContent(block)
                if (result) {
                    markdownParts.push(result.markdown)
                    htmlParts.push(result.html)
                }
            })

            if (markdownParts.length > 0) {
                const markdown = markdownParts.join('\n\n')
                const html = htmlParts.join('<br><br>')

                event.preventDefault()
                event.stopImmediatePropagation()
                if (event.clipboardData) {
                    event.clipboardData.setData('text/plain', markdown)
                    event.clipboardData.setData('text/html', html)
                }
                this.writeToClipboard(markdown, html)
            }
            return
        }

        // Single block selection - still need to extract full block formatting
        if (blocksInSelection.length === 1) {
            const block = blocksInSelection[0]
            if (block) {
                const result = this.extractBlockContent(block)
                if (result && result.markdown) {
                    event.preventDefault()
                    event.stopImmediatePropagation()
                    if (event.clipboardData) {
                        event.clipboardData.setData('text/plain', result.markdown)
                        event.clipboardData.setData('text/html', result.html)
                    }
                    this.writeToClipboard(result.markdown, result.html)
                    return
                }
            }
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
     * Handle EditorJS block selection (when multiple blocks are selected)
     * Synchronously extracts and converts content to markdown
     */
    private handleBlockSelection(event: ClipboardEvent, selectedBlocks: NodeListOf<Element>): void {
        const markdownParts: string[] = []
        const htmlParts: string[] = []

        selectedBlocks.forEach((block) => {
            const result = this.extractBlockContent(block)
            if (result) {
                markdownParts.push(result.markdown)
                htmlParts.push(result.html)
            }
        })

        if (markdownParts.length === 0) {
            return
        }

        const markdown = markdownParts.join('\n\n')
        const html = htmlParts.join('<br><br>')

        // Prevent default and stop immediate propagation to prevent any other handlers
        event.preventDefault()
        event.stopImmediatePropagation()

        if (event.clipboardData) {
            event.clipboardData.setData('text/plain', markdown)
            event.clipboardData.setData('text/html', html)
        }

        // Also use async clipboard API as backup
        this.writeToClipboard(markdown, html)
    }

    /**
     * Extract content from a block element and convert to markdown
     */
    private extractBlockContent(block: Element): { markdown: string; html: string } | null {
        // Paragraph
        const paragraph = block.querySelector('.ce-paragraph')
        if (paragraph) {
            const html = paragraph.innerHTML
            const markdown = MarkdownUtils.convertInlineHtmlToMarkdown(html, false).trim()
            return markdown ? { markdown, html } : null
        }

        // Header
        const header = block.querySelector('.ce-header')
        if (header) {
            const level = parseInt(header.tagName.substring(1)) || 2
            const text = MarkdownUtils.convertInlineHtmlToMarkdown(header.innerHTML, false).trim()
            if (text) {
                return {
                    markdown: '#'.repeat(level) + ' ' + text,
                    html: `<${header.tagName.toLowerCase()}>${header.innerHTML}</${header.tagName.toLowerCase()}>`,
                }
            }
            return null
        }

        // Delimiter
        const delimiter = block.querySelector('.ce-delimiter')
        if (delimiter) {
            return { markdown: '<!--break-->', html: '<hr>' }
        }

        // List
        const list = block.querySelector('.cdx-list')
        if (list) {
            const items: string[] = []
            const htmlItems: string[] = []
            list.querySelectorAll('.cdx-list__item').forEach(item => {
                const text = MarkdownUtils.convertInlineHtmlToMarkdown(item.innerHTML, false).trim()
                if (text) {
                    items.push('- ' + text)
                    htmlItems.push('<li>' + item.innerHTML + '</li>')
                }
            })
            if (items.length > 0) {
                return { markdown: items.join('\n'), html: '<ul>' + htmlItems.join('') + '</ul>' }
            }
            return null
        }

        // Quote
        const quote = block.querySelector('.cdx-quote')
        if (quote) {
            const textEl = quote.querySelector('.cdx-quote__text')
            if (textEl) {
                const text = MarkdownUtils.convertInlineHtmlToMarkdown(textEl.innerHTML, false).trim()
                if (text) {
                    return {
                        markdown: '> ' + text,
                        html: '<blockquote>' + textEl.innerHTML + '</blockquote>',
                    }
                }
            }
            return null
        }

        // CodeBlock (Monaco editor with language selector)
        const monacoWrapper = block.querySelector('.monaco-codeblock-wrapper')
        if (monacoWrapper) {
            const languageSelect = monacoWrapper.querySelector('select') as HTMLSelectElement
            const language = languageSelect?.value || ''
            // Monaco editor stores content in hidden elements or we need to get from view-lines
            const monacoLines = monacoWrapper.querySelectorAll('.view-line')
            let code = ''
            if (monacoLines.length > 0) {
                code = Array.from(monacoLines).map(line => line.textContent || '').join('\n')
            }
            // Replace non-breaking spaces with regular spaces
            code = code.replace(/\u00A0/g, ' ')
            if (code.trim()) {
                return {
                    markdown: '```' + language + '\n' + code + '\n```',
                    html: '<pre><code class="language-' + language + '">' + code + '</code></pre>',
                }
            }
            return null
        }

        // Code block (simple textarea-based)
        const code = block.querySelector('.ce-code__textarea, .cdx-code')
        if (code) {
            let text = code.textContent || ''
            text = text.replace(/\u00A0/g, ' ')
            if (text.trim()) {
                return {
                    markdown: '```\n' + text + '\n```',
                    html: '<pre><code>' + text + '</code></pre>',
                }
            }
            return null
        }

        // Raw HTML block (Monaco editor)
        const rawMonaco = block.querySelector('.editorjs-monaco-wrapper')
        if (rawMonaco) {
            const monacoLines = rawMonaco.querySelectorAll('.view-line')
            let text = ''
            if (monacoLines.length > 0) {
                text = Array.from(monacoLines).map(line => line.textContent || '').join('\n')
            }
            // Replace non-breaking spaces with regular spaces
            text = text.replace(/\u00A0/g, ' ')
            if (text.trim()) {
                return { markdown: text.trim(), html: text.trim() }
            }
            return null
        }

        // Raw HTML block (textarea-based fallback)
        const raw = block.querySelector('[data-editor], textarea')
        if (raw) {
            let text = (raw as HTMLTextAreaElement).value || raw.textContent || ''
            text = text.replace(/\u00A0/g, ' ')
            if (text.trim()) {
                return { markdown: text.trim(), html: text.trim() }
            }
            return null
        }

        // Image block
        const imageContainer = block.querySelector('.image-tool__image')
        if (imageContainer) {
            const img = imageContainer.querySelector('img') as HTMLImageElement
            const captionEl = block.querySelector('.image-tool__caption')
            if (img && img.src) {
                const caption = captionEl?.textContent?.trim() || ''
                const src = img.src
                return {
                    markdown: `![${caption}](${src})`,
                    html: `<img src="${src}" alt="${caption}">`,
                }
            }
            return null
        }

        // Gallery block
        const galleryWrapper = block.querySelector('.cdxcarousel-wrapper')
        if (galleryWrapper) {
            const items: { media: string; caption: string }[] = []
            const galleryList = galleryWrapper.querySelector('.cdxcarousel-list')
            if (galleryList) {
                galleryList.querySelectorAll('.cdxcarousel-item').forEach(item => {
                    const img = item.querySelector('img') as HTMLImageElement
                    const captionEl = item.querySelector('.image-tool__caption')
                    if (img && img.src && !item.classList.contains('cdxcarousel-item--empty')) {
                        // Extract media name from URL (last part of path)
                        const src = img.src
                        const mediaMatch = src.match(/\/media\/[^/]+\/([^/]+)$/) || src.match(/\/([^/]+)$/)
                        const media = mediaMatch?.[1] ?? src
                        const caption = captionEl?.textContent?.trim() || ''
                        items.push({ media, caption })
                    }
                })
            }
            if (items.length > 0) {
                const imagesObject: Record<string, string> = {}
                items.forEach(item => {
                    imagesObject[item.media] = item.caption
                })
                const markdown = `{{ gallery(${JSON.stringify(imagesObject)}) }}`
                return { markdown, html: markdown }
            }
            return null
        }

        // Attaches block
        const attachesContainer = block.querySelector('.cdx-attaches')
        if (attachesContainer) {
            const link = attachesContainer.querySelector('a') as HTMLAnchorElement
            const titleEl = attachesContainer.querySelector('.cdx-attaches__title')
            const sizeEl = attachesContainer.querySelector('.cdx-attaches__size')
            if (link && link.href) {
                const title = titleEl?.textContent?.trim() || ''
                const size = sizeEl?.textContent?.trim() || '0'
                const markdown = `{{ attaches('${title}', '${link.href}', '${size}') }}`
                return { markdown, html: markdown }
            }
            return null
        }

        // Embed/Video block
        const embedContainer = block.querySelector('.cdx-embed')
        if (embedContainer) {
            const urlInput = block.querySelector('.cdx-input-labeled-embed-service-url') as HTMLElement
            const captionEl = block.querySelector('.image-tool__caption') as HTMLElement
            const img = block.querySelector('img') as HTMLImageElement
            const serviceUrl = urlInput?.textContent?.trim() || ''
            const caption = captionEl?.textContent?.trim() || ''
            // Extract media name from image src
            let media = ''
            if (img && img.src) {
                const mediaMatch = img.src.match(/\/media\/[^/]+\/([^/]+)$/) || img.src.match(/\/([^/]+)$/)
                media = mediaMatch?.[1] ?? ''
            }
            if (serviceUrl) {
                const markdown = `{{ video('${serviceUrl}', '${media}', '${caption}') }}`
                return { markdown, html: markdown }
            }
            return null
        }

        // Fallback: get text content
        const content = block.querySelector('.ce-block__content')
        if (content) {
            const clone = content.cloneNode(true) as Element
            clone.querySelectorAll('[contenteditable="false"], .ce-header-level-wrapper').forEach(el => el.remove())
            const text = clone.textContent?.trim()
            if (text) {
                return { markdown: text, html: clone.innerHTML }
            }
        }

        return null
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

        // Skip if inside Monaco editor, Raw block, or CardList contenteditable
        if (element?.closest('.monaco-editor') ||
            element?.closest('.editorjs-monaco-wrapper') ||
            element?.closest('.cdx-card-list')) return

        // Get clipboard content
        const plainText = event.clipboardData?.getData('text/plain') || ''
        const htmlText = event.clipboardData?.getData('text/html') || ''

        // Let PasteLink handle URL paste over selected text
        const selectedText = selection?.toString() || ''
        if (selectedText && (this.isValidURL(plainText) || this.isValidRelativeURI(plainText))) {
            return // PasteLink will handle this
        }

        // Try to convert HTML to markdown if it looks like rich text (Google Docs, Word, etc.)
        let textToProcess = plainText
        if (htmlText && this.isRichTextHtml(htmlText)) {
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
        // Create a temporary container to parse HTML
        const container = document.createElement('div')
        container.innerHTML = html

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
                    case 'blockquote':
                        const quotedLines = innerContent.trim().split('\n').map(line => '> ' + line).join('\n')
                        parts.push('\n\n' + quotedLines + '\n\n')
                        break
                    case 'a':
                        const href = el.getAttribute('href') || ''
                        if (href && innerContent.trim()) {
                            parts.push('[' + innerContent.trim() + '](' + href + ')')
                        } else {
                            parts.push(innerContent)
                        }
                        break
                    case 'img':
                        const src = el.getAttribute('src') || ''
                        const alt = el.getAttribute('alt') || ''
                        if (src) {
                            parts.push('![' + alt + '](' + src + ')')
                        }
                        break
                    case 'ul':
                        const ulItems = Array.from(el.querySelectorAll(':scope > li')).map(li => {
                            return '- ' + this.processNodeToMarkdown(li).trim()
                        }).join('\n')
                        parts.push('\n\n' + ulItems + '\n\n')
                        break
                    case 'ol':
                        const olItems = Array.from(el.querySelectorAll(':scope > li')).map((li, idx) => {
                            return (idx + 1) + '. ' + this.processNodeToMarkdown(li).trim()
                        }).join('\n')
                        parts.push('\n\n' + olItems + '\n\n')
                        break
                    case 'li':
                        // Li is handled by ul/ol
                        parts.push(innerContent)
                        break
                    case 'table':
                        parts.push('\n\n' + this.convertTableToMarkdown(el) + '\n\n')
                        break
                    case 'hr':
                        parts.push('\n\n---\n\n')
                        break
                    case 'span':
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
     * Convert HTML table to markdown table
     */
    private convertTableToMarkdown(table: HTMLElement): string {
        const rows: string[][] = []

        table.querySelectorAll('tr').forEach(tr => {
            const cells: string[] = []
            tr.querySelectorAll('th, td').forEach(cell => {
                cells.push(this.processNodeToMarkdown(cell).trim().replace(/\|/g, '\\|'))
            })
            if (cells.length > 0) {
                rows.push(cells)
            }
        })

        if (rows.length === 0) return ''

        const maxCols = Math.max(...rows.map(r => r.length))

        // Normalize all rows to have the same number of columns
        rows.forEach(row => {
            while (row.length < maxCols) {
                row.push('')
            }
        })

        const lines: string[] = []
        rows.forEach((row, idx) => {
            lines.push('| ' + row.join(' | ') + ' |')
            // Add separator after first row (header)
            if (idx === 0) {
                lines.push('| ' + row.map(() => '---').join(' | ') + ' |')
            }
        })

        return lines.join('\n')
    }

    /**
     * Detect if text contains markdown or structured content patterns
     */
    private detectMarkdownPatterns(text: string): boolean {
        const trimmed = text.trim()

        // Skip single-line simple text (no special characters)
        if (!trimmed.includes('\n') && !/[#*_`\[\]{}><-]/.test(trimmed)) {
            return false
        }

        const markdownPatterns = [
            /^#{1,6}\s/m,                    // Headers (# to ######)
            /^[-*+]\s/m,                     // Unordered lists
            /^\d+\.\s/m,                     // Ordered lists
            /^>\s/m,                         // Blockquotes
            /^```/m,                         // Code blocks
            /^\|.+\|$/m,                     // Tables
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
        const blocks = markdown.split('\n\n')

        for (const block of blocks) {
            if (!block.trim()) continue
            this.importBlock(block)
        }
    }

    /**
     * Import a single markdown block using the appropriate tool
     * Logic adapted from EditorJsParseMarkdown
     */
    private importBlock(block: string): void {
        // @ts-ignore - accessing internal API
        const api = this.editor as API

        // Try each tool (except paragraph and raw) first
        for (const tool of this.editorjsTools) {
            if (['paragraph', 'raw', 'stub'].includes(tool.name)) continue

            const toolClass = tool.constructable
            if (!toolClass) continue

            if (this.importBlockWithTool(block, toolClass, api)) return
        }

        // Try paragraph
        const paragraphTool = this.getToolClass('paragraph')
        if (paragraphTool && this.importBlockWithTool(block, paragraphTool, api)) return

        // Try raw as fallback
        const rawTool = this.getToolClass('raw')
        if (rawTool) this.importBlockWithTool(block, rawTool, api)
    }

    private importBlockWithTool(markdownBlock: string, toolClass: ToolInterface, api: API): boolean {
        if (typeof toolClass.isItMarkdownExported !== 'function') {
            return false
        }

        const markdownBlockWithoutTunes = MarkdownUtils.retrieveMarkdownWithoutTunes(markdownBlock)
        if (!toolClass.isItMarkdownExported(markdownBlockWithoutTunes)) return false

        toolClass.importFromMarkdown(api, markdownBlock)
        return true
    }

    private getToolClass(blockType: string): ToolInterface | null {
        const tool = this.editorjsTools.find((t) => t.name === blockType) as
            | BlockToolAdapterWithConstructable
            | undefined

        return tool?.constructable || null
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
