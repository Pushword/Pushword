var Encore = require("@symfony/webpack-encore");
const tailwindcss = require("tailwindcss");

const purgecss = require("@fullhuman/postcss-purgecss")({
    mode: "all",
    content: [
        "./src/templates/**/*.html.twig",
        "./src/templates/*.html.twig",
        "./../conversation/src/templates/*.html.twig",
        "./../admin-block-editor/src/templates/block/*.html.twig",
    ],
    defaultExtractor: (content) => content.match(/[\w-/:]+(?<!:)/g) || [],
});

Encore.setOutputPath("./src/Resources/public/")
    .setPublicPath("./")
    .setManifestKeyPrefix("bundles/pushwordcore")

    .cleanupOutputBeforeBuild()
    .enableSassLoader()
    .enableSourceMaps(false)
    .enableVersioning(false)
    .enablePostCssLoader((options) => {
        options.postcssOptions = {
            plugins: [
                require("postcss-import"),
                tailwindcss("./tailwind.config.js"),
                require("autoprefixer"),
            ],
        };
        if (Encore.isProduction()) {
            options.postcssOptions.plugins.push(purgecss);
        }
    })
    .disableSingleRuntimeChunk()
    .copyFiles({
        from: "./src/Resources/assets/favicons",
        to: "favicons/[name].[ext]",
    })
    .addEntry("page", "./src/Resources/assets/page.js") // {{ asset('bundles/pushwordcore/page.js') }}
    .addStyleEntry("tailwind", "./src/Resources/assets/tailwind.css");

module.exports = Encore.getWebpackConfig();
