import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import { HyperlinkTuneData } from '../HyperlinkTune/HyperlinkTune'
import * as prettier from 'prettier/standalone'
import SmartQuotes from './SmartQuotes'
import he from 'he'

export interface BlockTuneDataPushword extends BlockTuneData {
  anchor?: string
  textAlign?: string
  class?: string
  linkTune?: HyperlinkTuneData
}
/**
 * Utilitaires pour l'export Markdown
 */
export class MarkdownUtils {
  static wrapWithLink(markdown: string, tunes: BlockTuneDataPushword): string {
    if (!tunes.linkTune) {
      return MarkdownUtils.addAttributes(markdown, tunes)
    }

    const linkTune: HyperlinkTuneData = tunes.linkTune

    if (!linkTune.url) {
      return markdown
    }

    let link = `[${markdown}](${linkTune.url}){`
    if (linkTune.targetBlank) {
      link += `target="_blank"`
    }
    link += `}`
    link = link.replace(/{}/g, '')

    if (linkTune.hideForBot) {
      link = '#' + link
    }
    return link
  }

  static getAttributes(tunes: BlockTuneDataPushword): string {
    let result = ''

    // anchor
    const anchor = tunes?.anchor
    if (anchor && anchor !== '') {
      result += `#${anchor}`
    }

    const alignment = tunes?.textAlign
    if (alignment && alignment !== 'left') {
      const alignmentClass =
        alignment === 'center' ? 'text-center' : alignment === 'right' ? 'text-right' : ''
      if (alignmentClass) {
        result += `.${alignmentClass}`
      }
    }

    const className = tunes?.class
    if (className && className !== '') {
      result += `.${className}`
    }

    return result
  }

  static addAttributes(markdown: string, tunes: BlockTuneDataPushword): string {
    let result = MarkdownUtils.getAttributes(tunes)

    result = result.replace(/\s+/g, ' ').trim()
    if (result !== '') {
      return '{' + result + '}\n' + markdown
    }

    return markdown
  }

  static startWithAttribute(firstLine: string): boolean {
    const line = firstLine.trim()
    if (line.startsWith('{#') && (line.endsWith('#}') || !line.endsWith('}')))
      return false // it's a twig comment

    return (
      line.startsWith('{') &&
      line.endsWith('}') &&
      !line.startsWith('{{') &&
      !line.startsWith('{%')
    )
  }

  static parseAttributes(attributeLine: string): BlockTuneDataPushword {
    const tunes: BlockTuneData = {}

    const anchorMatch = attributeLine.match(/#([a-zA-Z0-9_-]+)/)
    if (anchorMatch) {
      tunes.anchor = anchorMatch[1]
    }

    const alignmentMatch = attributeLine.match(/\.text-(left|center|right)/)
    if (alignmentMatch) {
      tunes.textAlign = alignmentMatch[1]
      attributeLine = attributeLine.replace(alignmentMatch[0], '')
    }

    const classMatch = attributeLine.match(/\.([a-zA-Z0-9_-]+)/g)
    if (classMatch) {
      tunes.class = classMatch.join(' ')
    }

    return tunes
  }

  static retrieveMarkdownWithoutTunes(markdown: string): string {
    markdown = markdown.trim()
    let lines = markdown.split('\n')
    const firstLine = lines[0] ?? ''

    // Parse attributes if present
    if (MarkdownUtils.startWithAttribute(firstLine)) {
      lines[0] = ''
      return lines.join('\n').trim()
    }

    return markdown
  }

  static parseTunesFromMarkdown(markdown: string): {
    tunes: BlockTuneData
    markdown: string
  } {
    markdown = markdown.trim()
    let lines = markdown.split('\n')
    const firstLine = lines[0] ?? ''
    let tunes: BlockTuneData = {}

    // Parse attributes if present
    if (MarkdownUtils.startWithAttribute(firstLine)) {
      tunes = MarkdownUtils.parseAttributes(firstLine)
      lines[0] = ''
      markdown = lines.join('\n').trim()
    }

    return {
      tunes,
      markdown,
    }
  }

  // TODO : manage "ex" ~ "ample" or variable ?
  public static extractTwigFunctionProperties(
    funcName: string,
    markdown: string,
  ): string[] | null {
    const match = markdown.matchAll(/{{\s*([A-Za-z_]+)\((.*?)\)/g)
    if (!match) return null

    const matches = [...match]
    if (matches[0]?.[1] !== funcName) return null

    const argsString = matches[0]?.[0]?.substring(matches[0]?.[0]?.indexOf('(') + 1)
    return MarkdownUtils.extractTwigProperties(argsString)
  }

  static extractTwigProperties(argsString: string): null | string[] {
    const properties: string[] = []
    let current = ''
    let inQuote = false
    let quoteChar = ''
    let escaped = false

    for (const char of argsString) {
      // Arrêter si on trouve ')' en dehors des quotes
      if (char === ')' && !inQuote) {
        break
      }

      // - managed escaped quotes
      if (escaped) {
        // Ajouter le caractère échappé tel quel
        current += char === quoteChar ? char : '\\' + char
        escaped = false
        continue
      }

      if (char === '\\') {
        escaped = true
        continue
      }
      // - / managed escaped quotes

      if (['"', "'"].includes(char) && !inQuote) {
        // Début d'une chaîne quotée
        inQuote = true
        quoteChar = char
        continue
      }

      // Fin d'une chaîne quotée
      if (char === quoteChar && inQuote) {
        inQuote = false
        quoteChar = ''
        continue
      }

      if (!inQuote && ![' ', ','].includes(char)) {
        return null
        // throw new Error('Invalid twig function properties')
      }

      if (!inQuote && char === ',') {
        properties.push(current.trim())
        current = ''
        continue
      }

      current += char
    }

    properties.push(current.trim())

    return properties
  }

  /**
   * Parse HTML attributes from a string and return them as a typed record
   */
  private static parseHtmlAttributes(attrString: string): Record<string, string> {
    const attrs: Record<string, string> = {}

    attrString.replace(
      /(\w+)\s*=\s*"([^"]*?)"/gi,
      (_match: string, key: string, value: string) => {
        attrs[key.toLowerCase()] = value
        return '' // required for .replace callback
      },
    )

    return attrs
  }

  private static convertAnchorToMarkdown(attrString: string, text: string): string {
    const attrs = MarkdownUtils.parseHtmlAttributes(attrString)
    const href = attrs.href || '#'
    const extras: string[] = []
    let obfuscate = false
    if (attrs.rel && attrs.rel === 'obfuscate') {
      obfuscate = true
    } else if (attrs.rel) extras.push(`rel="${attrs.rel}"`)
    if (attrs.target) extras.push(`target="${attrs.target}"`)
    if (attrs.class) extras.push(`class="${attrs.class}"`)

    return (
      (obfuscate ? '#' : '') +
      (extras.length ? `[${text}](${href}){${extras.join(' ')}}` : `[${text}](${href})`)
    )
  }

  static fixDash(text: string) {
    // Replace hyphens between numbers or spaces with en-dash
    text = text.replace(/(?<=[0-9 ])-(?=[0-9 ]|$)/g, '—')

    // Replace double hyphens with em-dash
    return text.replace(/ ?-- ?([^-]|$)/gs, '—' + '$1')
  }

  static makeUrlRelative(text: string): string {
    const host = window.pageHost
    const baseUrl = window.location.origin
    if (host === '') return text

    const toReplace = [
      `"${baseUrl}/${host}/`,
      `"${baseUrl}/`,
      `"https://${host}/`,
      `"http://${host}/`,
      `"://${host}/`,
    ]

    toReplace.forEach((replaceStr) => {
      text = text.split(replaceStr).join('"/')
    })

    return text
  }

  static fixer(text: string): string {
    const noBreakSpace = '\u00A0'
    const spaces = '\xE2\x80\xAF|\xC2\xAD|\xC2\xA0|\u00A0|\\s'

    text = MarkdownUtils.fixDash(text)
    text = MarkdownUtils.makeUrlRelative(text)

    if (window.pageLocale) {
      text = SmartQuotes(text, window.pageLocale)
    }

    text = text
      .replace(
        new RegExp(`([\\dº])(${spaces})+([º°%Ω฿₵¢₡$₫֏€ƒ₲₴₭£₤₺₦₨₱៛₹$₪৳₸₮₩¥]{1})`, 'g'), // \\w
        `$1${noBreakSpace}$3`,
      )
      .replace(/&nbsp;/gi, ' ')
      // CurlyQuote
      .replace(/([a-z])'([a-z])/gim, `$1’$2`)
      // Remove useless last space from inline tag
      .replace(/ <\/([a-z]+)>/gi, '</$1> ')
      // remove empty inline tag
      .replace(/ ?<(b|i|strong|em|span)> ?<\/(b|i|strong|em|span)> ?/gi, ' ')
      // NoSpaceBeforeComma
      .replace(new RegExp(`([^\\d\\s]+)[${spaces}]{1,},[${spaces}]{1,}`, 'gmu'), '$1, ')
      // NoSpaceBeforeDot
      .replace(new RegExp(`([^\\d\\s]+)[${spaces}]{1,}\\.[${spaces}]{1,}`, 'gmu'), '$1. ')

      // Ellipsis
      .replace(/\.{3,}/g, '…')
      // Ampersand
      .replace(/ &amp; /gi, ' & ')
      // Remove soft hyphens
      .replace(/&shy;/g, '')
      // Remove double spaces
      .replace(new RegExp(`[${spaces}]{2,}`, 'gmu'), ' ')

      // Dimension, replace 'x' between numbers with multiplication sign
      .replace(
        new RegExp(`(\\d+["']?)([${spaces}])?x([${spaces}])?(?=\\d)`, 'g'),
        '$1$2×$2',
      )
      .replace(/\(tm\)/gi, '™')
      .replace(/\(r\)/gi, '®')
      .replace(/\(c\)/gi, '©')

    return text
  }

  static convertInlineHtmlToMarkdown(html: string): string {
    html = MarkdownUtils.fixer(html)
    // Decode HTML entities first (including numeric ones like &#10140;)
    html = he.decode(html)

    return html
      .replace(/<(b|strong|em|i|a[^>]*)> /gi, ' <$1>')
      .replace(/ <\/(b|strong|em|i|a[^>]*)>/gi, '<$1> ')
      .replace(/<b>(.*?)<\/b>/gi, '**$1**')
      .replace(/<i>(.*?)<\/i>/gi, '_$1_')
      .replace(/<code( class="inline-code")?>(.*?)<\/code>/gi, '`$2`')
      .replace(/<s( class="cdx-strikethrough")?>(.*?)<\/s>/gi, '~~$2~~')
      .replace(/ class="cdx-marker"/gi, '')
      .replace(/<a\s+([^>]+)>(.*?)<\/a>/gi, (_match, attrString, text) =>
        MarkdownUtils.convertAnchorToMarkdown(attrString, text),
      )
  }

  private static convertMarkdownToAnchor(markdown: string): string {
    const isObfuscated = markdown.startsWith('#')
    const linkText = isObfuscated ? markdown.substring(1) : markdown

    // Match markdown link with optional attributes: [text](url{attrs})
    const linkWithAttrsRegex = /\[([^\]]+)\]\(([^){]+)\)\{([^}]+)\}/
    const simpleLinkRegex = /\[([^\]]+)\]\(([^)]+)\)/

    let match = linkText.match(linkWithAttrsRegex)
    let text: string
    let href: string
    let attrsString = ''

    if (match) {
      text = match[1] ?? ''
      href = match[2] ?? ''
      attrsString = match[3] ?? ''
    } else {
      match = linkText.match(simpleLinkRegex)
      if (!match) return markdown
      text = match[1] ?? ''
      href = match[2] ?? ''
    }

    if (isObfuscated) {
      attrsString = attrsString ? `rel="obfuscate" ${attrsString}` : 'rel="obfuscate"'
    }

    const attrs = attrsString ? ' ' + attrsString : ''
    return `<a href="${href}"${attrs}>${text}</a>`
  }

  static convertInlineMarkdownToHtml(markdown: string): string {
    return markdown
      .replace(/\*\*(.+?)\*\*/g, '<b>$1</b>')
      .replace(/_(.+?)_/g, '<i>$1</i>')
      .replace(/`(.+?)`/g, '<code class="inline-code">$1</code>')
      .replace(/~~(.+?)~~/g, '<s class="cdx-strikethrough">$1</s>')
      .replace(/#?\[([^\]]+)\]\(([^)]+?)(?:\{([^}]+)\})?\)/g, (match) =>
        MarkdownUtils.convertMarkdownToAnchor(match),
      )
  }

  /**
   * Formate le contenu Markdown avec Prettier
   */
  public static async formatMarkdownWithPrettier(
    markdownContent: string,
  ): Promise<string> {
    try {
      // Charger le plugin Markdown dynamiquement
      const prettierMarkdown = await import('prettier/plugins/markdown')

      const formatted = await prettier.format(markdownContent, {
        parser: 'markdown',
        plugins: [prettierMarkdown],
        //printWidth: 80,
        proseWrap: 'preserve',
        tabWidth: 2,
        useTabs: false,
      })

      return formatted.trim()
    } catch (error) {
      console.log('Erreur lors du formatage Prettier du Markdown', {
        content: markdownContent,
      })

      return markdownContent
    }
  }
}
