const EncoreHelper = require('@pushword/js-helper/src/encore.js');
const Encore = require('@symfony/webpack-encore');

var watchFiles = [
    './src/templates/**/*.html.twig',
    './src/templates/*.html.twig',
    './../conversation/src/templates/conversation/*.html.twig',
    './../admin-block-editor/src/templates/block/*.html.twig',
    './../advanced-main-image/src/templates/page/*.html.twig',
    './../js-helper/src/helpers.js',
];

var tailwindConfig = EncoreHelper.getTailwindConfig(watchFiles);

module.exports = EncoreHelper.getEncore(
    watchFiles,
    tailwindConfig,
    './src/Resources/public/',
    './',
    'bundles/pushwordcore',
    [
        {
            from: './src/Resources/assets/favicons',
            to: 'favicons/[name].[ext]',
        },
    ]
).getWebpackConfig();
