module.exports = {
  performance: {
    hints: false,
  },
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
        test: /\.s?css$/,
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
