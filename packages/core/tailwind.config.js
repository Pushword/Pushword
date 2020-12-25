module.exports = {
  purge: {}, // directly in webpack
  theme: {
    extend: {
      typography: {
        DEFAULT: {
          css: {
            color: '#333',
            a: {
              color: 'var(--primary)',
              '&:hover': {
                color: 'var(--primary-light)',
              },
            },
          },
        },
      },
      colors: {
        primary: 'var(--primary)',
        'primary-light': 'var(--primary-light)',
        bg: 'var(--secondary)',
      },
    },
  },
  variants: {},
  plugins: [
    require('@tailwindcss/typography'),
    require('@tailwindcss/aspect-ratio'),
    require('@tailwindcss/forms'),
  ],
};
