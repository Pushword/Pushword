const colors = require('tailwindcss/colors');

module.exports = {
  purge: {}, // directly in webpack
  theme: {
    extend: {
      typography: (theme) => ({
        DEFAULT: {
          css: {
            color: '#333',
            a: {
              color: theme('colors.yellow.600'),
              '&:hover': {
                opacity: '0.75',
              },
            },
          },
        },
      }),
      colors: {
        primary: 'var(--primary)',
        secondary: 'var(--secondary)',
      },
    },
  },
  variants: {
    width: ['responsive', 'hover', 'focus'],
  },
  plugins: [
    require('@tailwindcss/typography'),
    require('@tailwindcss/aspect-ratio'),
  ],
};
