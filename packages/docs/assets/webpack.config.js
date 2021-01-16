var Encore = require('@symfony/webpack-encore');
const tailwindcss = require('tailwindcss');

const purgecss = require('@fullhuman/postcss-purgecss')({
  mode: 'all',
  content: [
    './../../core/src/templates/**/*.html.twig',
    './../../core/src/templates/*.html.twig',
    './../../skeleton/templates/pushword.piedweb.com/*.html.twig',
    './../../skeleton/templates/pushword.piedweb.com/**/*.html.twig',
    './../content/*.md',
    './*.js',
    './../content/**/*.md',
    './../../../docs/*.html',
    './../../../docs/**/*.html',
  ],
  defaultExtractor: (content) => content.match(/[\w-/:]+(?<!:)/g) || [],
});

Encore.setOutputPath('./../../skeleton/public/assets/')
  .setPublicPath('/assets')
  .cleanupOutputBeforeBuild()
  .enableSassLoader()
  .enableSourceMaps(false)
  .enableVersioning(false)
  .enablePostCssLoader((options) => {
    options.postcssOptions = {
      plugins: [
        require('postcss-import'),
        tailwindcss('./tailwind.config.js'),
        require('autoprefixer'),
      ],
    };
    if (Encore.isProduction()) {
      options.postcssOptions.plugins.push(purgecss);
    }
  })
  .disableSingleRuntimeChunk()
  .copyFiles({
    from: './',
    to: '[name].[ext]',
    pattern: /logo.svg$/,
  })
  .copyFiles({
    from: './favicons',
    to: 'favicons/[name].[ext]',
  })
  .addEntry('app', './app.js')
  .addStyleEntry('tw', './app.css');

module.exports = Encore.getWebpackConfig();
