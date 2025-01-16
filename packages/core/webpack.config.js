const EncoreHelper = require('@pushword/js-helper/src/encore.js')
const Encore = require('@symfony/webpack-encore')

var watchFiles = [
  './src/templates/**/*.html.twig',
  './src/templates/*.html.twig',
  './../conversation/src/templates/conversation/*.html.twig',
  './../admin/src/templates/*.html.twig',
  './../admin-block-editor/src/templates/block/*.html.twig',
  './../advanced-main-image/src/templates/page/*.html.twig',
  './../js-helper/src/helpers.js',
]

var tailwindConfig = EncoreHelper.getTailwindConfig(watchFiles)

const isDev = process.env.NODE_ENV !== 'production'

const modernConfig = EncoreHelper.getEncore(Encore, watchFiles, tailwindConfig, __dirname + '/src/Resources/public', '/bundles/pushwordcore', 'bundles/pushwordcore', [
  {
    from: __dirname + '/src/Resources/assets/favicons',
    to: '[name].[ext]',
  },
]).getWebpackConfig()

Encore.reset()
const legacyConfig = isDev
  ? null
  : EncoreHelper.getEncore(
      Encore,
      watchFiles,
      tailwindConfig,
      __dirname + '/src/Resources/public',
      '/bundles/pushwordcore',
      'bundles/pushwordcore',
      null,
      null,
      null,
      true,
    ).getWebpackConfig()

module.exports = isDev ? [modernConfig] : [modernConfig, legacyConfig]
