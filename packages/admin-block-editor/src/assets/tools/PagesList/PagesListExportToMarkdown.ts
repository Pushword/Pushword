import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import { PagesListData } from './PagesList'

/**
 * Export PagesList block data to Markdown
 * Cette fonction est extraite pour être utilisée sans dépendances au navigateur
 */
export function exportPagesListToMarkdown(
  data: PagesListData,
  tunes: BlockTuneData,
): string {
  if (!data || !data.kw) {
    return ''
  }

  const max = (data.max || '9').trim()
  const maxPages = (data.maxPages || '0').trim()
  const order = data.order || 'publishedAt,priority'
  const display = data.display || 'list'

  let markdown = `{{ pages_list('${data.kw}', '${max}', '${order}', '${display}'`
  markdown += maxPages !== '0' || tunes.class || tunes.anchor ? `, '${maxPages}'` : ''
  markdown += tunes.class || tunes.anchor ? `, '${tunes.class || ''}'` : ''
  markdown += tunes.anchor ? `, '${tunes.anchor}'` : ''
  markdown += `) }}`

  return markdown
}

