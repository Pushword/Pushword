import EditorJS, { API } from '@editorjs/editorjs'
import { MarkdownUtils } from './MarkdownUtils'
import { BlockToolAdapter } from '@editorjs/editorjs/types/tools/adapters/block-tool-adapter'
import { ToolInterface } from '../Abstract/ToolInterface'

interface BlockToolAdapterWithConstructable extends BlockToolAdapter {
    constructable?: ToolInterface
}

/**
 * ClipboardManager handles copy/paste operations for EditorJS
 * - Copy: Always converts selected content to markdown
 * - Paste: Smart detection of markdown patterns, converts to EditorJS blocks
 */
export default class ClipboardManager {
    private editor: EditorJS
    private _editorjsTools: ToolInterface[] | null = null

    constructor({ editor }: { editor: EditorJS }) {
        this.editor = editor
        this.initialize()
    }

    /**
     * Lazy-load editor tools when needed
     */
    private get editorjsTools(): ToolInterface[] {
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
        const markdown = MarkdownUtils.convertInlineHtmlToMarkdown(html).replace(/  +/g, ' ').trim()

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
            const markdown = MarkdownUtils.convertInlineHtmlToMarkdown(html).trim()
            return markdown ? { markdown, html } : null
        }

        // Header
        const header = block.querySelector('.ce-header')
        if (header) {
            const level = parseInt(header.tagName.substring(1)) || 2
            const text = MarkdownUtils.convertInlineHtmlToMarkdown(header.innerHTML).trim()
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
                const text = MarkdownUtils.convertInlineHtmlToMarkdown(item.innerHTML).trim()
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
                const text = MarkdownUtils.convertInlineHtmlToMarkdown(textEl.innerHTML).trim()
                if (text) {
                    return {
                        markdown: '> ' + text,
                        html: '<blockquote>' + textEl.innerHTML + '</blockquote>',
                    }
                }
            }
            return null
        }

        // Code block
        const code = block.querySelector('.ce-code__textarea, .cdx-code')
        if (code) {
            const text = code.textContent || ''
            if (text.trim()) {
                return {
                    markdown: '```\n' + text + '\n```',
                    html: '<pre><code>' + text + '</code></pre>',
                }
            }
            return null
        }

        // Raw HTML block
        const raw = block.querySelector('[data-editor]')
        if (raw) {
            const text = raw.textContent || ''
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
        const galleryContainer = block.querySelector('.cdxcarousel')
        if (galleryContainer) {
            const items: { media: string; caption: string }[] = []
            galleryContainer.querySelectorAll('.cdxcarousel-item').forEach(item => {
                const img = item.querySelector('img') as HTMLImageElement
                const captionEl = item.querySelector('.cdxcarousel-item-caption, [data-placeholder]')
                if (img && img.src) {
                    // Extract media name from URL (last part of path)
                    const src = img.src
                    const mediaMatch = src.match(/\/media\/[^/]+\/([^/]+)$/) || src.match(/\/([^/]+)$/)
                    const media = mediaMatch ? mediaMatch[1] : src
                    const caption = captionEl?.textContent?.trim() || ''
                    items.push({ media, caption })
                }
            })
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

        // Embed block (YouTube, Vimeo, etc.)
        const embedContainer = block.querySelector('.embed-tool')
        if (embedContainer) {
            const iframe = embedContainer.querySelector('iframe') as HTMLIFrameElement
            const captionEl = embedContainer.querySelector('.embed-tool__caption')
            if (iframe && iframe.src) {
                const caption = captionEl?.textContent?.trim() || ''
                // Try to extract original URL from embed URL
                let url = iframe.src
                // YouTube embed to watch URL
                const ytMatch = url.match(/youtube\.com\/embed\/([^?]+)/)
                if (ytMatch) {
                    url = `https://www.youtube.com/watch?v=${ytMatch[1]}`
                }
                // Vimeo embed to regular URL
                const vimeoMatch = url.match(/player\.vimeo\.com\/video\/([^?]+)/)
                if (vimeoMatch) {
                    url = `https://vimeo.com/${vimeoMatch[1]}`
                }
                const markdown = caption ? `{{ embed('${url}', '${caption}') }}` : `{{ embed('${url}') }}`
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
     * Handle paste events - detect markdown and convert to blocks
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
            element?.closest('[data-editor]') ||
            element?.closest('.cdx-card-list')) return

        // Get clipboard text
        const text = event.clipboardData?.getData('text/plain') || ''
        if (!text) return

        // Let PasteLink handle URL paste over selected text
        const selectedText = selection?.toString() || ''
        if (selectedText && (this.isValidURL(text) || this.isValidRelativeURI(text))) {
            return // PasteLink will handle this
        }

        // Check if text contains markdown patterns
        if (!this.detectMarkdownPatterns(text)) {
            return // Let default paste handle plain text
        }

        // Prevent default and insert as blocks
        event.preventDefault()
        event.stopPropagation()

        this.insertMarkdownAsBlocks(text)
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

            const toolClass = (tool as BlockToolAdapterWithConstructable).constructable
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
