var Encore = require('@symfony/webpack-encore');
const tailwindcss = require('tailwindcss');

const purgecss = require('@fullhuman/postcss-purgecss')({
  mode: 'all',
  content: [
    './../vendor/pushword/core/src/templates/**/*.html.twig',
    './../vendor/pushword/core/src/templates/*.html.twig',
    './../templates/*/*.html.twig',
    './../templates/*/*/*.html.twig',
    './../templates/*.html.twig',
    './../templates/*/*.html.twig',
    './*.js',
  ],
  defaultExtractor: (content) => content.match(/[\w-/:]+(?<!:)/g) || [],
});

Encore.setOutputPath('./../public/assets/')
  .setPublicPath('/assets')
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
    from: './media/',
    to: '[name].[ext]',
    pattern: /svg$/,
  })
  .copyFiles({
    from: './img/',
    to: '[name].[ext]',
    pattern: /header.jpg$/,
  })
  .copyFiles({
    from: './favicons',
    to: '[name].[ext]',
  })
  .addEntry('app', './app.js')
  .addStyleEntry('tw', './app.css');

module.exports = Encore.getWebpackConfig();
