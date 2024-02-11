// var Encore = require('@symfony/webpack-encore')

// Encore.setOutputPath('./src/Resources/public/')
//   .setPublicPath('./')
//   .setManifestKeyPrefix('bundles/pushwordadminblockeditor')
//   .cleanupOutputBeforeBuild()
//   .enableSassLoader()
//   .enableSourceMaps(false)
//   .enableVersioning(false)
//   /** Used because of nested */
//   .addLoader({
//     test: /\.pcss$/,
//     loader: 'style-loader',
//   })
//   .addLoader({
//     test: /\.pcss$/,
//     loader: 'css-loader',
//   })
//   .addLoader({
//     test: /\.pcss$/,
//     loader: 'postcss-loader',
//     options: {
//       postcssOptions: {
//         plugins: [require('postcss-nested-ancestors'), require('postcss-nested')],
//       },
//     },
//   })
//   /***/
//   .disableSingleRuntimeChunk()
//   .addEntry('admin-block-editor', './src/assets/admin-block-editor.js') // {{ asset('bundles/pushwordadmin/admin.js') }}

// module.exports = Encore.getWebpackConfig()

module.exports = {
  entry: {
    'admin-block-editor': './src/assets/admin-block-editor.js',
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: [
          {
            loader: 'babel-loader',
            options: {
              presets: ['@babel/preset-env'],
            },
          },
        ],
      },
      {
        test: /\.p?css$/,
        use: [
          'style-loader',
          'css-loader',
          {
            loader: 'postcss-loader',
            options: {
              postcssOptions: {
                plugins: [require('postcss-nested-ancestors'), require('postcss-nested')],
              },
            },
          },
        ],
      },
    ],
  },
  output: {
    path: __dirname + '/src/Resources/public',
    publicPath: '/',
    filename: '[name].js',
    libraryExport: 'default',
    libraryTarget: 'umd',
  },
}
