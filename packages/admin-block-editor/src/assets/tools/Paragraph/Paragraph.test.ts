import { describe, it, expect } from 'vitest'
import Paragraph from './Paragraph'

describe('Paragraph.exportToMarkdown', () => {
  it('writes a <br> as a markdown hard break', async () => {
    const markdown = await Paragraph.exportToMarkdown(
      { text: 'Transfert au bateau. <br>Vers 18h : cocktail' },
      {},
    )

    expect(markdown.trim()).toBe('Transfert au bateau.  \nVers 18h : cocktail')
  })
})
