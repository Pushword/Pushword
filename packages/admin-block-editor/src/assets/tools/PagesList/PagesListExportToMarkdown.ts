import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import { PagesListData } from './PagesList'

/**
 * Export PagesList block data to Markdown
 * Cette fonction est extraite pour être utilisée sans dépendances au navigateur
 */
export function exportPagesListToMarkdown(
  data: PagesListData,
  tunes?: BlockTuneData,
): string {
  if (!data || !data.kw) {
    return ''
  }

  const max = (data.max || '9').trim()
  const maxPages = (data.maxPages || '0').trim()
  const order = data.order || 'publishedAt,priority'
  const display = data.display || 'list'

  let markdown = `{{ pages_list(${e(data.kw)}, ${e(max)}, ${e(order)}, ${e(display)}`
  markdown += maxPages !== '0' || tunes?.class || tunes?.anchor ? `, ${e(maxPages)}` : ''
  markdown += tunes?.class || tunes?.anchor ? `, ${e(tunes?.class || '')}` : ''
  markdown += tunes?.anchor ? `, ${e(tunes?.anchor)}` : ''
  markdown += `) }}`

  return markdown
}
