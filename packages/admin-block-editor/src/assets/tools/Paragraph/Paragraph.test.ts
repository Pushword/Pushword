import { describe, it, expect, beforeEach, vi } from 'vitest'
import Paragraph from './Paragraph'
import { MarkdownUtils } from '../utils/MarkdownUtils'

describe('Paragraph.exportToMarkdown', () => {
  beforeEach(() => {
    vi.spyOn(MarkdownUtils, 'formatMarkdownWithPrettier').mockImplementation(
      async (markdown) => markdown,
    )
  })

  it('writes a <br> as a markdown hard break', async () => {
    const markdown = await Paragraph.exportToMarkdown(
      { text: 'Transfert au bateau. <br>Vers 18h : cocktail' },
      {},
    )

    expect(markdown.trim()).toBe('Transfert au bateau.  \nVers 18h : cocktail')
  })

  it.each(['<br/>', '<br />', '<BR>'])('writes a %s as a hard break too', async (br) => {
    const markdown = await Paragraph.exportToMarkdown({ text: `un${br}deux` }, {})

    expect(markdown.trim()).toBe('un  \ndeux')
  })
})
