const WatchExternalFilesPlugin = require("webpack-watch-files-plugin").default;
const Encore = require("@symfony/webpack-encore");
const tailwindcss = require("tailwindcss");

const watchFiles = [
    "./src/templates/**/*.html.twig",
    "./src/templates/*.html.twig",
    "./../conversation/src/templates/conversation/*.html.twig",
    "./../admin-block-editor/src/templates/block/*.html.twig",
];

var TailwindConfig = require("@pushword/js-helper/src/tailwind.config.js");
TailwindConfig.content = watchFiles;

Encore.setOutputPath("./src/Resources/public/")
    .setPublicPath("./")
    .setManifestKeyPrefix("bundles/pushwordcore")

    .cleanupOutputBeforeBuild()
    .addPlugin(
        new WatchExternalFilesPlugin({
            files: watchFiles,
        })
    )
    .enableSassLoader()
    .enableSourceMaps(false)
    .enableVersioning(false)
    .enablePostCssLoader((options) => {
        options.postcssOptions = {
            plugins: [require("postcss-import"), tailwindcss(TailwindConfig), require("autoprefixer")],
        };
    })
    .disableSingleRuntimeChunk()
    .copyFiles({
        from: "./src/Resources/assets/favicons",
        to: "favicons/[name].[ext]",
    })
    .addEntry("page.min", "./src/Resources/assets/page.js") // {{ asset('bundles/pushwordcore/page.min.js') }}
    .addStyleEntry("tailwind.min", "./src/Resources/assets/tailwind.css");

module.exports = Encore.getWebpackConfig();
