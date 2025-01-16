const WatchExternalFilesPlugin = require('webpack-watch-files-plugin').default
const tailwindcss = require('tailwindcss')
const postcssImport = require('postcss-import')
const autoprefixer = require('autoprefixer')
const Encore = require('@symfony/webpack-encore')

function getFilesToWatch(basePath = './..') {
  return [
    basePath + '/vendor/pushword/core/src/templates/**/*.html.twig',
    basePath + '/vendor/pushword/conversation/src/templates/**/*.html.twig',
    basePath + '/vendor/pushword/admin-block-editor/src/templates/**/*.html.twig',
    basePath + '/vendor/pushword/advanced-main-image/src/templates/**/*.html.twig',
    basePath + '/templates/**/*.html.twig',
    basePath + '/var/TailwindGeneratorCache/*',
    basePath + '/src/Twig/AppExtension.php',
  ]
}

function getTailwindConfig(watchFiles = null) {
  if (watchFiles === null) watchFiles = getFilesToWatch()
  var tailwindConfig = require('./tailwind.config.js')
  tailwindConfig.content = watchFiles
  return tailwindConfig
}

/**
 *
 * @param {Encore} Encore
 */
function getEncore(
  Encore,
  watchFiles = null, // default: getFilesToWatch()
  tailwindConfig = null, // default : getTailwindConfig()
  outputPath = null, // default : './../public/assets/'
  publicPath = null, // default: '/assets'
  manifestKeyPrefix = null, // default: null
  filesToCopy = null, // default :: ... from: /favicons. ...
  entries = null, // [{ name: 'app', file: '/node_modules/@pushword/js-helper/src/app.js' }];
  styleEntries = null, // [{ name: 'style', file: '/node_modules/@pushword/js-helper/src/app.css' }];
  isLegacy = false,
) {
  if (watchFiles === null) {
    watchFiles = getFilesToWatch()
  }

  if (tailwindConfig === null) {
    tailwindConfig = getTailwindConfig(watchFiles)
  }

  const jsAppName = 'app' + (isLegacy ? '-legacy' : '')
  if (entries === null) {
    entries = [{ name: jsAppName, file: __dirname + '/app.js' }]
  } else if (typeof entries === 'string') {
    entries = [{ name: jsAppName, file: entries }]
  }

  if (styleEntries === null && !isLegacy) {
    styleEntries = [{ name: 'style', file: __dirname + '/app.css' }]
  } else if (typeof styleEntries === 'string') {
    styleEntries = [{ name: 'style', file: styleEntries }]
  }

  outputPath = outputPath ? outputPath : './../public/assets/'
  publicPath = publicPath ? publicPath : '/assets'

  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev')
    .setOutputPath(outputPath)
    .setPublicPath(publicPath)
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
        plugins: [postcssImport, tailwindcss(tailwindConfig), autoprefixer],
      }
    })
    .disableSingleRuntimeChunk()

  if (filesToCopy === null && !isLegacy) {
    filesToCopy = [
      {
        from: './favicons',
        to: '[name].[ext]',
      },
    ]
  }

  if (filesToCopy)
    filesToCopy.forEach(function (toCopy) {
      Encore.copyFiles(toCopy)
    })

  if (manifestKeyPrefix !== null) Encore.setManifestKeyPrefix(manifestKeyPrefix)

  entries.forEach(function (entry) {
    Encore.addEntry(entry.name, entry.file)
  })

  if (styleEntries)
    styleEntries.forEach(function (entry) {
      Encore.addStyleEntry(entry.name, entry.file)
    })

  //if (!isLegacy) Encore.cleanupOutputBeforeBuild()

  if (!isLegacy) {
    Encore.configureBabelPresetEnv((config) => {
      config.targets = {
        browsers: ['Chrome >= 60', 'Safari >= 10.1', 'iOS >= 10.3', 'Firefox >= 54', 'Edge >= 15'],
      }
    })
  } else {
    Encore.configureBabelPresetEnv((config) => {
      config.targets = {
        browsers: ['> 1%', 'last 2 versions', 'Firefox ESR'],
      }
    })
  }

  return Encore
}

module.exports = {
  getFilesToWatch: getFilesToWatch,
  getTailwindConfig: getTailwindConfig,
  getEncore: getEncore,
}
