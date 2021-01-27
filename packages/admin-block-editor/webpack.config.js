var Encore = require("@symfony/webpack-encore");

Encore.setOutputPath("./src/Resources/public/")
    .setPublicPath("./")
    .setManifestKeyPrefix("bundles/pushwordadminblockeditor")

    .cleanupOutputBeforeBuild()
    .enableSassLoader()
    .enableSourceMaps(false)
    .enableVersioning(false)
    .disableSingleRuntimeChunk()
    .addEntry("admin-block-editor", "./src/assets/admin-block-editor.js"); // {{ asset('bundles/pushwordadmin/admin.js') }}

module.exports = Encore.getWebpackConfig();
