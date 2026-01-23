import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import { CardListData } from './CardList'
import { e } from '../utils/MarkdownUtils'

/**
 * Export CardList block data to Markdown
 */
export function exportCardListToMarkdown(
  data: CardListData,
  tunes?: BlockTuneData,
): string {
  if (!data || !data.items || data.items.length === 0) {
    return ''
  }

  const items = data.items.map((item) => {
    const obj: Record<string, string | boolean> = {}
    if (item.page) obj.page = item.page
    if (item.title) obj.title = item.title
    if (item.image) obj.image = item.image
    if (item.link) obj.link = item.link
    if (item.obfuscateLink) obj.obfuscateLink = item.obfuscateLink
    if (item.description) obj.description = item.description
    if (item.buttonLink) obj.buttonLink = item.buttonLink
    if (item.buttonLinkLabel) obj.buttonLinkLabel = item.buttonLinkLabel
    return obj
  })

  const itemsJson = JSON.stringify(items, null, 2)
  let markdown = `{{ card_list(${itemsJson}`
  markdown += tunes?.class || tunes?.anchor ? `, ${e(tunes?.class || '')}` : ''
  markdown += tunes?.anchor ? `, ${e(tunes?.anchor)}` : ''
  markdown += `) }}`

  return markdown
}
