const EncoreHelper = require('@pushword/js-helper/src/encore.js')

var watchFiles = [
  './src/templates/**/*.html.twig',
  './src/templates/*.html.twig',
  './../conversation/src/templates/conversation/*.html.twig',
  './../admin-block-editor/src/templates/block/*.html.twig',
  './../advanced-main-image/src/templates/page/*.html.twig',
  './../js-helper/src/helpers.js',
]

var tailwindConfig = EncoreHelper.getTailwindConfig(watchFiles)

const Encore = EncoreHelper.getEncore(watchFiles, tailwindConfig, __dirname + '/src/Resources/public/', '/bundles/pushwordcore', 'bundles/pushwordcore', [
  {
    from: './src/Resources/assets/favicons',
    to: 'favicons/[name].[ext]',
  },
])

console.log(Encore.getWebpackConfig())

module.exports = Encore.getWebpackConfig()
