var Encore = require('@symfony/webpack-encore');
const tailwindcss = require('tailwindcss');

const purgecss = require('@fullhuman/postcss-purgecss')({
  mode: 'all',
  content: [
    './src/Resources/views/**/*.html.twig',
    './src/Resources/views/*.html.twig',
    './../conversation/src/Resources/views/*.html.twig',
  ],
  defaultExtractor: (content) => content.match(/[\w-/:]+(?<!:)/g) || [],
});

Encore.setOutputPath('./src/Resources/public/')
  .setPublicPath('./')
  .setManifestKeyPrefix('bundles/pushwordcore')

  .cleanupOutputBeforeBuild()
  .enableSassLoader()
  .enableSourceMaps(false)
  .enableVersioning(false)
  .enablePostCssLoader((options) => {
    options.postcssOptions = {
      plugins: [
        tailwindcss('./tailwind.config.js'),
        require('autoprefixer'),
        require('postcss-import'),
      ],
    };
    if (Encore.isProduction()) {
      options.postcssOptions.plugins.push(purgecss);
    }
  })
  .disableSingleRuntimeChunk()
  .copyFiles({
    from: './node_modules/ace-builds/src-min-noconflict/',
    // relative to the output dir
    to: 'ace/[name].[ext]',
    // only copy files matching this pattern
    pattern: /\.js$/,
  })
  .copyFiles({
    from: './src/Resources/assets/',
    to: '[name].[ext]',
    pattern: /logo.svg$/,
  })
  .copyFiles({
    from: './src/Resources/assets/favicons',
    to: 'favicons/[name].[ext]',
  })
  .addEntry('admin', './../admin/src/assets/admin.js') // {{ asset('bundles/pushwordcore/admin.js') }}
  .addEntry('page', './src/Resources/assets/page.js') // {{ asset('bundles/pushwordcore/page.js') }}
  .addStyleEntry('tailwind', './src/Resources/assets/tailwind.css');

module.exports = Encore.getWebpackConfig();
