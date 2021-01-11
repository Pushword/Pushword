var Encore = require('@symfony/webpack-encore');

Encore.setOutputPath('./src/Resources/public/')
  .setPublicPath('./')
  .setManifestKeyPrefix('bundles/pushwordadmin')

  .cleanupOutputBeforeBuild()
  .enableSassLoader()
  .enableSourceMaps(false)
  .enableVersioning(false)
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
  .addEntry('admin', './src/Resources/assets/admin.js'); // {{ asset('bundles/pushwordadmin/admin.js') }}

module.exports = Encore.getWebpackConfig();
