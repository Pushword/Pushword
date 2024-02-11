const EncoreHelper = require('@pushword/js-helper/src/encore.js')
const Encore = require('@symfony/webpack-encore')

var watchFiles = [
  './src/templates/**/*.html.twig',
  './src/templates/*.html.twig',
  './../conversation/src/templates/conversation/*.html.twig',
  './../admin-block-editor/src/templates/block/*.html.twig',
  './../advanced-main-image/src/templates/page/*.html.twig',
  './../js-helper/src/helpers.js',
]

var tailwindConfig = EncoreHelper.getTailwindConfig(watchFiles)

EncoreHelper.getEncore(Encore, watchFiles, tailwindConfig, __dirname + '/src/Resources/public', '/bundles/pushwordcore', 'bundles/pushwordcore', [])

module.exports = Encore.getWebpackConfig()
