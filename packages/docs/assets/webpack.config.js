var Encore = require("@symfony/webpack-encore");
const tailwindcss = require("tailwindcss");
const WatchExternalFilesPlugin = require("webpack-watch-files-plugin").default;

const watchFiles = [
    "./../../core/src/templates/**/*.html.twig",
    "./../../core/src/templates/*.html.twig",
    "./../../admin/src/templates/*.html.twig",
    "./../../skeleton/templates/pushword.piedweb.com/*.html.twig",
    "./../../skeleton/templates/pushword.piedweb.com/**/*.html.twig",
    "./../content/*.md",
    "./*.js",
    "./../content/**/*.md",
    "./../../../docs/*.html",
    "./../../../docs/**/*.html",
];

var tailwindConfig = require("./tailwind.config.js");
tailwindConfig.purge = watchFiles;

Encore.setOutputPath("./../../skeleton/public/assets/")
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
            plugins: [require("postcss-import"), tailwindcss(tailwindConfig), require("autoprefixer")],
        };
    })
    .disableSingleRuntimeChunk()
    .copyFiles({
        from: "./",
        to: "[name].[ext]",
        pattern: /logo.svg$/,
    })
    .copyFiles({
        from: "./favicons",
        to: "favicons/[name].[ext]",
    })
    .addEntry("app.min", "./app.js")
    .addStyleEntry("tw.min", "./app.css");

module.exports = Encore.getWebpackConfig();
