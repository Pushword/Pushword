/**
 * Inspired by
 * https://github.com/jolicode/JoliTypo/blob/main/src/JoliTypo/Fixer/SmartQuotes.php
 */

const UnicodeConstants = {
  NO_BREAK_SPACE: '\u00A0',
  LAQUO: '«',
  RAQUO: '»',
  LDQUO: '“',
  RDQUO: '”',
  BDQUO: '„',
} as const

export interface QuoteStyle {
  opening: string
  openingSuffix: string
  closing: string
  closingPrefix: string
}

export enum QuoteStyleType {
  DoubleQuotes = 'doubleQuotes',
  Guillemets = 'guillemets',
  GuillemetsFr = 'guillemetsFr',
  GermanQuotes = 'germanQuotes',
  FinnishQuotes = 'finnishQuotes',
}

export const QUOTE_STYLES: Readonly<Record<QuoteStyleType, QuoteStyle>> = {
  [QuoteStyleType.DoubleQuotes]: {
    opening: UnicodeConstants.LDQUO,
    openingSuffix: '',
    closing: UnicodeConstants.RDQUO,
    closingPrefix: '',
  },
  [QuoteStyleType.Guillemets]: {
    opening: UnicodeConstants.LAQUO,
    openingSuffix: '',
    closing: UnicodeConstants.RAQUO,
    closingPrefix: '',
  },
  [QuoteStyleType.GuillemetsFr]: {
    opening: UnicodeConstants.LAQUO,
    openingSuffix: UnicodeConstants.NO_BREAK_SPACE,
    closing: UnicodeConstants.RAQUO,
    closingPrefix: UnicodeConstants.NO_BREAK_SPACE,
  },
  [QuoteStyleType.GermanQuotes]: {
    opening: UnicodeConstants.BDQUO,
    openingSuffix: '',
    closing: UnicodeConstants.LDQUO,
    closingPrefix: '',
  },
  [QuoteStyleType.FinnishQuotes]: {
    opening: UnicodeConstants.RDQUO,
    openingSuffix: '',
    closing: UnicodeConstants.RDQUO,
    closingPrefix: '',
  },
}

const STYLE_TO_LOCALES_MAP: Readonly<Record<QuoteStyleType, string[]>> = {
  [QuoteStyleType.DoubleQuotes]: [
    'pt-br',
    'en',
    'us',
    'gb',
    'af',
    'ar',
    'eo',
    'id',
    'ga',
    'ko',
    'br',
    'th',
    'tr',
    'vi',
  ],
  [QuoteStyleType.Guillemets]: [
    'de-ch',
    'hy',
    'az',
    'hz',
    'eu',
    'be',
    'ca',
    'el',
    'it',
    'no',
    'fa',
    'lv',
    'pt',
    'ru',
    'es',
    'uk',
  ],
  [QuoteStyleType.GuillemetsFr]: ['fr'],
  [QuoteStyleType.GermanQuotes]: [
    'de',
    'ka',
    'cs',
    'et',
    'is',
    'lt',
    'mk',
    'ro',
    'sk',
    'sl',
    'wen',
  ],
  [QuoteStyleType.FinnishQuotes]: ['fi', 'sv', 'bs'],
}

const LOCALE_QUOTES = new Map<string, QuoteStyle>()
for (const [style, locales] of Object.entries(STYLE_TO_LOCALES_MAP)) {
  const quoteStyle = QUOTE_STYLES[style as QuoteStyleType]
  for (const locale of locales) {
    LOCALE_QUOTES.set(locale, quoteStyle)
  }
}

export default function SmartQuotes(content: string, locale = 'en'): string {
  if (content.includes('{{') || content.includes('{%')) {
    //skip smartQuotes enhancement for block containing twig code
    return content
  }

  const lowerCaseLocale = locale.toLowerCase()
  const localeParts = lowerCaseLocale.split('-')

  // Create a fallback chain for locales, e.g., ['en-us', 'en']
  const fallbackLocales = [
    lowerCaseLocale,
    ...(localeParts.length > 1 ? [localeParts[0]] : []),
  ]

  let config = QUOTE_STYLES[QuoteStyleType.DoubleQuotes] // default
  for (const loc of fallbackLocales) {
    const foundConfig = loc ? LOCALE_QUOTES.get(loc) : null
    if (foundConfig) {
      config = foundConfig
      break
    }
  }

  const { opening, openingSuffix, closing, closingPrefix } = config

  // Use a regular expression with a named capture group for clarity.
  const quoteRegex = /(?<prefix>^|\s|\()"(?<content>[^"]+)"/gim

  return content.replace(
    quoteRegex,
    `$<prefix>${opening}${openingSuffix}$<content>${closingPrefix}${closing}`,
  )
}
