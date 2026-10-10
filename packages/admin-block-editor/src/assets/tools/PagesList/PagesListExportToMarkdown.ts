import { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'
import { PagesListData } from './PagesList'
import { MarkdownUtils, e } from '../utils/MarkdownUtils'

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
  const order = data.order || 'publishedAt,weight'
  const display = data.display || 'list'

  const tuneArguments = MarkdownUtils.tuneArguments(tunes)

  let markdown = `{{ pages_list(${e(data.kw)}, ${e(max)}, ${e(order)}, ${e(display)}`
  markdown += maxPages !== '0' || tuneArguments !== '' ? `, ${e(maxPages)}` : ''
  markdown += tuneArguments
  markdown += `) }}`

  return markdown
}
