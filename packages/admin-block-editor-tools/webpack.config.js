const path = require('path')

module.exports = {
  entry: {
    Image: './src/Image/Image.js',
    Attaches: './src/Attaches/Attaches.js',
    Gallery: './src/Gallery/Gallery.js',
    Embed: './src/Embed/Embed.js',
    PagesList: './src/PagesList/PagesList.js',
    Hyperlink: './src/Hyperlink/Hyperlink.js',
    Raw: './src/Raw/Raw.js',
    Anchor: './src/Anchor/Anchor.js',
    Class: './src/Class/Class.js',
    AlignementTune: './src/AlignementTune/AlignementTune.js',
    PasteLink: './src/PasteLink/PasteLink.js',
    HyperlinkTune: './src/HyperlinkTune/HyperlinkTune.js',
    CodeBlock: './src/CodeBlock/CodeBlock.js',
    Header: './src/Header/Header.js',
    Small: './src/Small/Small.ts',
  },
  module: {
    rules: [
      {
        test: /\.ts$/,
        use: 'ts-loader',
      },
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
      {
        test: /\.(svg)$/,
        use: [
          {
            loader: 'raw-loader',
          },
        ],
      },
    ],
  },
  output: {
    path: path.join(__dirname, '/dist'),
    publicPath: '/',
    filename: '[name].js',
    libraryExport: 'default',
    libraryTarget: 'umd',
  },
}
