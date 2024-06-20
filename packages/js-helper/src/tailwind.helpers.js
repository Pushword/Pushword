module.exports = {
  extendTailwindTypography: function () {
    return {
      DEFAULT: {
        css: {
          'a, span[data-rot], .link': {
            textDecoration: 'none',
            color: 'var(--primary)',
            fontWeight: 500,
            borderBottom: '1px solid;',
            '&:hover': {
              opacity: '.75',
            },
          },
        },
      },
    }
  },
  twFirstLetterPlugin: function ({ addVariant, e }) {
    addVariant('first-letter', ({ modifySelectors, separator }) => {
      modifySelectors(({ className }) => {
        return `.${e(`first-letter${separator}${className}`)}:first-letter`
      })
    })
  },
  twFirstChildPlugin: function ({ addVariant, e }) {
    addVariant('first-child', ({ modifySelectors, separator }) => {
      modifySelectors(({ className }) => {
        return `.${e(`first-child${separator}${className}`)}:first-child`
      })
    })
  },
  twBleedPlugin: function ({ addUtilities }) {
    addUtilities({
      '.bleed': {
        width: '100vw',
        'margin-inline-start': '50%',
        'margin-inline-end': 'unset',
        transform: 'translateX(-50%)',
        'max-width': 'none',
      },
      '.bleed-disable': {
        width: 'inherit',
        'margin-inline-start': 'inherit',
        'margin-inline-end': 'inherit',
        transform: 'default',
      },
    })
  },
  justifySafeCenterPlugin: function ({ addUtilities }) {
    addUtilities({
      '.justify-safe-center': {
        'justify-content': 'safe center',
      },
    })
  },
}
