var Encore = require('@symfony/webpack-encore')

Encore.configureRuntimeEnvironment('production')

Encore.setOutputPath('./src/Resources/public/')
  .setPublicPath('/bundles/pushwordadmin')
  .setManifestKeyPrefix('/bundles/pushwordadmin/')

  .cleanupOutputBeforeBuild()
  .enableSassLoader()
  .enableSourceMaps(false)
  .enableVersioning(false)
  .disableSingleRuntimeChunk()
  .copyFiles({
    from: './src/Resources/assets/',
    to: '[name].[ext]',
    pattern: /logo.svg$/,
  })
  .addEntry('admin.min', './src/Resources/assets/admin.js') // {{ asset('bundles/pushwordadmin/admin.js') }}

module.exports = Encore.getWebpackConfig()
