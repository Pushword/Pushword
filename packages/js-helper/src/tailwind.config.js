const plugin = require('tailwindcss/plugin');
const pushwordHelper = require('./tailwind.helpers.js');

module.exports = {
    mode: 'jit',
    theme: {
        minHeight: {
            0: '0',
            'screen-1/4': '25vh',
            'screen-3/4': '75vh',
            'screen-1/3': '33vh',
            'screen-2/3': '66vh',
            'screen-1/2': '50vh',
            screen: '100vh',
            full: '100%',
        },
        extend: {
            typography: pushwordHelper.extendTailwindTypography(),
            colors: {
                primary: 'var(--primary)',
                secondary: 'var(--secondary)',
            },
        },
    },
    variants: {},
    plugins: [
        require('tailwindcss-multi-column')(),
        require('@tailwindcss/typography'),
        require('@tailwindcss/aspect-ratio'),
        require('@tailwindcss/forms'),
        require('tailwindcss-animated'),
        require('tailwindcss-hero-patterns'),
        plugin(pushwordHelper.twFirstLetterPlugin),
        plugin(pushwordHelper.twFirstChildPlugin),
        plugin(pushwordHelper.twBleedPlugin),
        plugin(pushwordHelper.justifySafeCenterPlugin),
    ],
};
