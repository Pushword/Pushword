const Encore = require('@symfony/webpack-encore')
const WatchExternalFilesPlugin = require('webpack-watch-files-plugin').default

const watchFiles = [
  './../../core/src/templates/**/*.html.twig',
  './../../core/src/templates/*.html.twig',
  './../../admin/src/templates/*.html.twig',
  './../../skeleton/templates/pushword.piedweb.com/*.html.twig',
  './../../skeleton/templates/pushword.piedweb.com/**/*.html.twig',
  './../content/*.md',
  './*.js',
  './../content/**/*.md',
  './../../../docs/*.html',
  './../../../docs/**/*.html',
]

Encore.setOutputPath('./../../skeleton/public/assets/')
  .setPublicPath('/assets')
  .cleanupOutputBeforeBuild()
  .enableSassLoader()
  .enablePostCssLoader((options) => {
    options.postcssOptions = {
      plugins: [require('@tailwindcss/postcss')],
    }
  })
  .enableSourceMaps(false)
  .enableVersioning(false)
  .addPlugin(
    new WatchExternalFilesPlugin({
      files: watchFiles,
    }),
  )
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
  .addEntry('app.min', './app.js')
  .addStyleEntry('tw.min', './app.css')

module.exports = Encore.getWebpackConfig()
