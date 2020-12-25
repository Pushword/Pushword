require('webpack');

const webpack = require('webpack');
const path = require('path');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const CleanWebpackPlugin = require('clean-webpack-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin')
const FaviconsWebpackPlugin = require('favicons-webpack-plugin')
const WebpackPwaManifest = require('webpack-pwa-manifest')

const config = {
    mode: "development",
    entry: [
        './src/js/app.js',
    ],
    output: {
        path: path.resolve(__dirname, 'dist'),
        filename: 'app.js',
        libraryTarget: 'umd'
    },
    module: {
        rules: [{
                test: /\.s?[ac]ss$/,
                use: [
                    MiniCssExtractPlugin.loader,
                    {
                        loader: 'css-loader', // translates CSS into CommonJS modules
                        options: {
                            sourceMap: true,
                            minimize: true
                        }
                    },
                    {
                        loader: 'postcss-loader', // Run post css actions
                        options: {
                            sourceMap: true,
                            plugins: function() {
                                return [
                                    require('postcss-flexbugs-fixes'),
                                    require('autoprefixer')
                                ];
                            }
                        }
                    },
                    {
                        loader: 'sass-loader', // compiles SASS to CSS
                        options: {
                            sourceMap: true
                        }
                    }
                ]
            },
            {
                test: /\.(png|jpg|gif|svg|ttf|woff2|woff|eot)$/,
                use: 'file-loader'
            },
            {
                test: /.html$/,
                use: {
                    loader: 'html-loader',
                    options: {
                        interpolate: true
                    }
                }
            },
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: [{
                    loader: 'babel-loader', // transpile to ES5
                    options: {
                        presets: ['es2015']
                    }
                }]
            }
        ]
    },
    plugins: [
        new CleanWebpackPlugin(['dist']),
        //new FaviconsWebpackPlugin('./src/img/logo_title.png'),
        //new CopyWebpackPlugin([{from:'./src/demo/html/img', to: 'img'}]),
        new MiniCssExtractPlugin({
            filename: "app.css",
        }),
        new HtmlWebpackPlugin({
            filename: 'index.html',
            template: './src/demo/html/index.html'
        }),
        new HtmlWebpackPlugin({
            filename: 'elements.html',
            template: './src/demo/html/elements.html'
        }),
    ]
};




var devConfig = Object.assign({}, config, {
    mode: 'development',
    devServer: {
        contentBase: path.join(__dirname, 'dist'),
        open: true,
        openPage: 'index.html',
        port: 3000
    },
});

var prodConfig = Object.assign({}, config, {
    mode: 'production',
    output: Object.assign({}, config.output, {
        filename: 'app.min.js'
    }),
});

module.exports = [
    devConfig, prodConfig
];
