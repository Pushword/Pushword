const WatchExternalFilesPlugin = require("webpack-watch-files-plugin").default;
const Encore = require("@symfony/webpack-encore");
const tailwindcss = require("tailwindcss");

const watchFiles = [
    "./../vendor/pushword/core/src/templates/**/*.html.twig",
    "./../vendor/pushword/core/src/templates/*.html.twig",
    "./../templates/*/*.html.twig",
    "./../templates/*/*/*.html.twig",
    "./../templates/*.html.twig",
    "./../templates/*/*.html.twig",
    "./*.js",
];

var tailwindConfig = require("./../vendor/pushword/core/src/Resources/assets/tailwind.config.js");
tailwindConfig.purge = watchFiles;

Encore.setOutputPath("./../public/assets/")
    .setPublicPath("/assets")
    .cleanupOutputBeforeBuild()
    .enableSassLoader()
    .enableSourceMaps(false)
    .enableVersioning(false)
    .addPlugin(
        new WatchExternalFilesPlugin({
            files: watchFiles,
        })
    )
    .enablePostCssLoader((options) => {
        options.postcssOptions = {
            plugins: [require("postcss-import"), tailwindcss("./tailwind.config.js"), require("autoprefixer")],
        };
    })
    .disableSingleRuntimeChunk()
    .copyFiles({
        from: "./media/", // todo explain
        to: "[name].[ext]",
        pattern: /svg$/,
    })
    .copyFiles({
        from: "./img/",
        to: "[name].[ext]",
        pattern: /header.jpg$/,
    })
    .copyFiles({
        from: "./favicons",
        to: "[name].[ext]",
    })
    .addEntry("app.min", "./app.js")
    .addStyleEntry("tw.min", "./app.css");

module.exports = Encore.getWebpackConfig();
