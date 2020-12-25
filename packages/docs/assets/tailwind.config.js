module.exports = {
  purge: {}, // directly in webpack
  theme: {
    flex: {
      1: '1 1 0%',
      auto: '1 1 auto',
      initial: '0 1 auto',
      inherit: 'inherit',
      none: 'none',
      full: '1 0 100%;',
      'half-50': '0 1 45%',
      'half-30': '0 1 25%',
      'half-70': '0 1 65%',
      'half-25': '0 1 20%',
      'half-75': '0 1 70%',
    },
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
  variants: {
    width: ['responsive', 'hover', 'focus'],
  },
  plugins: [
    require('@tailwindcss/typography'),
    require('@tailwindcss/aspect-ratio'),
  ],
};
