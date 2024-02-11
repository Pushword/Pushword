const EncoreHelper = require('@pushword/js-helper/src/encore.js')

const watchFiles = [
  './src/templates/**/*.html.twig',
  './src/templates/*.html.twig',
  './../conversation/src/templates/conversation/*.html.twig',
  './../admin-block-editor/src/templates/block/*.html.twig',
  './../advanced-main-image/src/templates/page/*.html.twig',
  './../js-helper/src/helpers.js',
]

const tailwindConfig = EncoreHelper.getTailwindConfig(watchFiles)

// const Encore = EncoreHelper.getEncore(watchFiles, tailwindConfig, './src/Resources/public/', '/bundles/pushwordcore', 'bundles/pushwordcore', [
//{
// from: './src/Resources/assets/favicons',
//  to: 'favicons/[name].[ext]',
//}
// ])

const WatchExternalFilesPlugin = require('webpack-watch-files-plugin').default
const Encore = require('@symfony/webpack-encore')
const tailwindcss = require('tailwindcss')

const filesToCopy = {
  from: './src/Resources/assets/favicons',
  to: 'favicons/[name].[ext]',
}

const entries = [{ name: 'app', file: '/home/robin/localhost/Pushword/packages/js-helper/src/app.js' }]
const styleEntries = [{ name: 'style', file: '/home/robin/localhost/Pushword/packages/js-helper/src/app.css' }]

outputPath = './src/Resources/public/'
publicPath = '/bundles/pushwordcore'

Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev')
  .setOutputPath(outputPath)
  .setPublicPath(publicPath)
  .cleanupOutputBeforeBuild()
  .enableSassLoader()
  .enableSourceMaps(false)
  .enableVersioning(false)
  .addPlugin(
    new WatchExternalFilesPlugin({
      files: watchFiles,
    }),
  )
  .enablePostCssLoader((options) => {
    options.postcssOptions = {
      plugins: [require('postcss-import'), tailwindcss(tailwindConfig), require('autoprefixer')],
    }
  })
  .disableSingleRuntimeChunk()

// filesToCopy.forEach(function (toCopy) {
//   Encore.copyFiles(toCopy)
// })

Encore.setManifestKeyPrefix('bundles/pushwordcore')

entries.forEach(function (entry) {
  Encore.addEntry(entry.name, entry.file)
})

styleEntries.forEach(function (entry) {
  Encore.addStyleEntry(entry.name, entry.file)
})

module.exports = Encore.getWebpackConfig()
