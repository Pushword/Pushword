/**
 * Script pour convertir du JSON EditorJS en Markdown
 * Utilise les méthodes exportToMarkdown des outils existants
 *
 * Usage:
 * node convert-json-to-markdown.mjs '<json_content>'
 * echo '<json_content>' | node convert-json-to-markdown.mjs
 *
 * Variables d'environnement optionnelles:
 * - PAGE_HOST: domaine de la page (défaut: '')
 * - PAGE_LOCALE: locale pour les guillemets intelligents (défaut: 'en')
 */

import type { BlockTuneData } from '@editorjs/editorjs/types/block-tunes/block-tune-data'

// Import des outils existants
import Header from '../assets/tools/Header/Header'
import Paragraph from '../assets/tools/Paragraph/Paragraph'
import List from '../assets/tools/List/List'
import Quote from '../assets/tools/Quote/Quote'
import CodeBlock from '../assets/tools/CodeBlock/CodeBlock'
import Image from '../assets/tools/Image/Image'
import Gallery from '../assets/tools/Gallery/Gallery'
import Table from '../assets/tools/Table/Table'
import Delimiter from '../assets/tools/Delimiter/Delimiter'
import Raw from '../assets/tools/Raw/Raw'
import Embed from '../assets/tools/Embed/Embed'
import Attaches from '../assets/tools/Attaches/Attaches'
import { exportPagesListToMarkdown } from '../assets/tools/PagesList/PagesListExportToMarkdown'
import { exportCardListToMarkdown } from '../assets/tools/CardList/CardListExportToMarkdown'

// Mock de window et document pour Node.js
// Les valeurs peuvent être transmises via variables d'environnement
declare global {
  var window: {
    pageHost: string
    pageLocale: string
    location: {
      origin: string
    }
    pagesUriList?: string[]
  }
  var document: {
    querySelector: (selector: string) => null
  }
}

// Initialiser window global avec les valeurs de l'environnement ou par défaut
// @ts-ignore - Mock complet pour Node.js
globalThis.window = {
  pageHost: process.env.PAGE_HOST || '',
  pageLocale: process.env.PAGE_LOCALE || 'en',
  // @ts-ignore
  location: {
    origin: process.env.PAGE_ORIGIN || '',
  },
  pagesUriList: [],
  // @ts-ignore
  Promise: Promise,
  // Ajouter d'autres propriétés globales nécessaires
  ...globalThis,
}

// Mock minimal de document pour Node.js
// @ts-ignore
globalThis.document = {
  querySelector: () => null,
  // @ts-ignore
  createElement: () => ({}),
}

// Map des types de blocs vers leurs classes
const TOOL_MAP: Record<string, any> = {
  header: Header,
  paragraph: Paragraph,
  list: List,
  quote: Quote,
  code: CodeBlock,
  codeBlock: CodeBlock,
  image: Image,
  gallery: Gallery,
  table: Table,
  delimiter: Delimiter,
  raw: Raw,
  embed: Embed,
  attaches: Attaches,
  pages_list: { exportToMarkdown: exportPagesListToMarkdown },
  card_list: { exportToMarkdown: exportCardListToMarkdown },
}

interface BlockData {
  type: string
  data?: any
  tunes?: BlockTuneData
}

interface EditorJsData {
  blocks: BlockData[]
}

/**
 * Convertit un bloc EditorJS en Markdown en utilisant la méthode exportToMarkdown du tool correspondant
 */
async function convertBlock(block: BlockData): Promise<string> {
  const ToolClass = TOOL_MAP[block.type]

  if (!ToolClass) {
    console.error(`Warning: Block type "${block.type}" not supported`)
    return ''
  }

  if (typeof ToolClass.exportToMarkdown !== 'function') {
    console.error(`Warning: Tool "${block.type}" does not have exportToMarkdown method`)
    return ''
  }

  try {
    // Appeler la méthode exportToMarkdown de l'outil
    const markdown = await ToolClass.exportToMarkdown(block.data || {}, block.tunes)
    return markdown || ''
  } catch (error) {
    console.error(`Error converting block type "${block.type}":`, error)
    return ''
  }
}

/**
 * Fonction principale
 */
async function main() {
  let jsonContent = ''

  // Lire depuis les arguments ou stdin
  if (process.argv[2]) {
    jsonContent = process.argv[2]
  } else {
    // Lire depuis stdin
    const chunks: Buffer[] = []
    for await (const chunk of process.stdin) {
      chunks.push(chunk as Buffer)
    }
    jsonContent = Buffer.concat(chunks).toString('utf-8')
  }

  if (!jsonContent || jsonContent.trim() === '') {
    console.error('Erreur: Aucun contenu JSON fourni')
    process.exit(1)
  }

  try {
    // Parser le JSON
    const editorData: EditorJsData = JSON.parse(jsonContent)

    // Vérifier qu'il s'agit bien d'un format EditorJS
    if (!editorData.blocks || !Array.isArray(editorData.blocks)) {
      console.error('Erreur: Format JSON invalide - blocks manquants ou invalides')
      process.exit(1)
    }

    // Convertir chaque bloc en Markdown en utilisant les méthodes des outils
    const markdownBlocks = await Promise.all(
      editorData.blocks.map((block) => convertBlock(block)),
    )
    const filteredBlocks = markdownBlocks.filter((content) => content !== '')

    // Joindre les blocs avec des doubles retours à la ligne
    const markdown = filteredBlocks.join('\n\n')

    // Afficher le résultat sur stdout
    console.log(markdown)
    process.exit(0)
  } catch (error) {
    console.error('Erreur lors de la conversion:', (error as Error).message)
    if ((error as Error).stack) {
      console.error((error as Error).stack)
    }
    process.exit(1)
  }
}

main()
